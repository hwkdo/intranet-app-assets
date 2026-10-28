<?php

use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailDocumentKind;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\AnalyzeInvoiceMailAttachmentJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\ClassifyInvoiceMailJob;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailD3UploadService;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailRecipientMatcher;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Rechnungs-Mail')] class extends Component
{
    public InvoiceMail $invoiceMail;

    public ?int $correctionAttachmentId = null;

    public string $userSearch = '';

    public ?int $uploadAttachmentId = null;

    public bool $showUploadModal = false;

    public bool $showCorrectionModal = false;

    /** @var array<int, string> */
    public array $kindByAttachment = [];

    public function mount(InvoiceMail $invoiceMail): void
    {
        $this->authorize('manage-app-assets');
        $this->invoiceMail = $invoiceMail;
        $this->syncKinds();
    }

    public function updatedKindByAttachment(string $value, string $attachmentId): void
    {
        $this->setDocumentKind((int) $attachmentId, $value);
    }

    public function setDocumentKind(int $attachmentId, string $kind): void
    {
        $this->authorize('manage-app-assets');
        $documentKind = InvoiceMailDocumentKind::tryFrom($kind);
        if ($documentKind === null) {
            return;
        }

        $attachment = $this->findAttachment($attachmentId);
        if ($attachment->d3_status === InvoiceMailD3Status::Uploaded || $attachment->document_kind === $documentKind->value) {
            return;
        }

        $attachment->update(['document_kind' => $documentKind->value]);
        $this->invoiceMail->logEvent(
            'document_kind',
            'Belegart auf '.$documentKind->label().' gesetzt (D3: '.$documentKind->d3Label().') von '.auth()->user()?->name.'.',
            attachmentId: $attachment->id,
        );
        $this->kindByAttachment[$attachment->id] = $documentKind->value;
        unset($this->attachments, $this->uploadAttachment, $this->uploadPreview);
    }

    private function syncKinds(): void
    {
        $this->kindByAttachment = $this->invoiceMail->attachments()
            ->pluck('document_kind', 'id')
            ->map(fn (mixed $kind): string => (string) $kind)
            ->all();
    }

    /**
     * @return Collection<int, InvoiceMailAttachment>
     */
    #[Computed]
    public function attachments(): Collection
    {
        return $this->invoiceMail->attachments()
            ->with(['suggestedUser', 'correctedUser', 'correctedBy', 'd3UploadedBy'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, \Hwkdo\IntranetAppAssets\Models\InvoiceMailEvent>
     */
    #[Computed]
    public function events(): Collection
    {
        return $this->invoiceMail->events()->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function userResults(): Collection
    {
        $search = trim($this->userSearch);
        if (mb_strlen($search) < 2) {
            return new Collection;
        }

        return User::query()
            ->where('active', true)
            ->where(fn ($q) => $q
                ->where('nachname', 'like', '%'.$search.'%')
                ->orWhere('vorname', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('username', 'like', '%'.$search.'%'))
            ->orderBy('nachname')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function uploadAttachment(): ?InvoiceMailAttachment
    {
        return $this->uploadAttachmentId === null
            ? null
            : $this->attachments->firstWhere('id', $this->uploadAttachmentId);
    }

    /**
     * @return array{fields: array<string, mixed>, error: ?string}
     */
    #[Computed]
    public function uploadPreview(): array
    {
        $attachment = $this->uploadAttachment;
        $user = $attachment?->effectiveUser();

        if ($attachment === null || $user === null) {
            return ['fields' => [], 'error' => 'Kein Empfänger festgelegt.'];
        }

        try {
            $fields = app(InvoiceMailD3UploadService::class)->fieldsFor($attachment, $user);
            unset($fields['doc_type']);

            return ['fields' => $fields, 'error' => null];
        } catch (\Throwable $e) {
            return ['fields' => [], 'error' => $e->getMessage()];
        }
    }

    public function isRunning(): bool
    {
        return $this->invoiceMail->status->isInProgress()
            || $this->attachments->contains(fn (InvoiceMailAttachment $a): bool => $a->status->isOpen() && $a->isCandidate() && $this->invoiceMail->status === InvoiceMailStatus::Analyzing)
            || $this->attachments->contains(fn (InvoiceMailAttachment $a): bool => $a->d3_status === InvoiceMailD3Status::Queued);
    }

    public function refreshData(): void
    {
        $this->invoiceMail->refresh();
        $this->syncKinds();
        unset($this->attachments, $this->events, $this->uploadAttachment, $this->uploadPreview);
    }

    public function reclassify(): void
    {
        $this->authorize('manage-app-assets');
        $this->invoiceMail->markStatus(InvoiceMailStatus::Received);
        $this->invoiceMail->logEvent('manual', 'Klassifizierung manuell neu gestartet von '.auth()->user()?->name.'.');
        ClassifyInvoiceMailJob::dispatch($this->invoiceMail->id);
        $this->refreshData();
        Flux::toast('Klassifizierung neu gestartet.', variant: 'success');
    }

    public function reanalyze(int $attachmentId): void
    {
        $this->authorize('manage-app-assets');
        $attachment = $this->findAttachment($attachmentId);

        if ($attachment->d3_status === InvoiceMailD3Status::Uploaded) {
            Flux::toast('Der Anhang ist bereits in D3.', variant: 'warning');

            return;
        }

        $attachment->update(['status' => InvoiceMailAttachmentStatus::Pending, 'last_error' => null]);
        $this->invoiceMail->markStatus(InvoiceMailStatus::Analyzing);
        $this->invoiceMail->logEvent('manual', 'Analyse manuell neu gestartet von '.auth()->user()?->name.'.', attachmentId: $attachment->id);
        AnalyzeInvoiceMailAttachmentJob::dispatch($attachment->id);
        $this->refreshData();
        Flux::toast('Analyse neu gestartet.', variant: 'success');
    }

    public function openCorrection(int $attachmentId): void
    {
        $this->authorize('manage-app-assets');
        $this->correctionAttachmentId = $this->findAttachment($attachmentId)->id;
        $this->userSearch = '';
        $this->showCorrectionModal = true;
    }

    public function correct(int $attachmentId, int $userId): void
    {
        $this->authorize('manage-app-assets');
        $attachment = $this->findAttachment($attachmentId);

        if ($attachment->d3_status === InvoiceMailD3Status::Uploaded) {
            Flux::toast('Der Anhang ist bereits in D3. Eine Korrektur ändert dort nichts mehr.', variant: 'warning');

            return;
        }

        $user = User::query()->where('active', true)->findOrFail($userId);

        $attachment->update([
            'corrected_user_id' => $user->id,
            'corrected_by' => auth()->id(),
            'corrected_at' => now(),
        ]);

        $this->invoiceMail->logEvent(
            'correction',
            'Empfänger korrigiert auf '.InvoiceMailRecipientMatcher::displayName($user).' (Vorschlag: '.($attachment->suggestedUser ? InvoiceMailRecipientMatcher::displayName($attachment->suggestedUser) : 'keiner').') von '.auth()->user()?->name.'.',
            context: ['suggested_user_id' => $attachment->suggested_user_id, 'corrected_user_id' => $user->id],
            attachmentId: $attachment->id,
        );

        $this->showCorrectionModal = false;
        $this->refreshData();
        Flux::toast('Korrektur gespeichert.', variant: 'success');
    }

    public function resetCorrection(int $attachmentId): void
    {
        $this->authorize('manage-app-assets');
        $attachment = $this->findAttachment($attachmentId);
        $attachment->update(['corrected_user_id' => null, 'corrected_by' => null, 'corrected_at' => null]);
        $this->invoiceMail->logEvent('correction', 'Korrektur entfernt von '.auth()->user()?->name.'.', attachmentId: $attachment->id);
        $this->refreshData();
    }

    public function openUpload(int $attachmentId): void
    {
        $this->authorize('manage-app-assets');
        $this->uploadAttachmentId = $this->findAttachment($attachmentId)->id;
        unset($this->uploadAttachment, $this->uploadPreview);
        $this->showUploadModal = true;
    }

    public function upload(InvoiceMailD3UploadService $uploadService): void
    {
        $this->authorize('manage-app-assets');
        $attachment = $this->uploadAttachment;
        $user = $attachment?->effectiveUser();

        if ($attachment === null || $user === null) {
            Flux::toast('Kein Empfänger festgelegt.', variant: 'danger');

            return;
        }

        try {
            $documentId = $uploadService->upload($attachment, $user, auth()->id());
            Flux::toast('Nach D3 hochgeladen: '.$documentId, variant: 'success');
            $this->showUploadModal = false;
        } catch (\Throwable $e) {
            Flux::toast('D3-Upload fehlgeschlagen: '.$e->getMessage(), variant: 'danger');
        }

        $this->refreshData();
    }

    private function findAttachment(int $attachmentId): InvoiceMailAttachment
    {
        return InvoiceMailAttachment::query()
            ->where('invoice_mail_id', $this->invoiceMail->id)
            ->findOrFail($attachmentId);
    }

    public function percent(?float $value): string
    {
        return $value === null ? '–' : number_format($value * 100, 1, ',', '').' %';
    }

    public function kindLabel(?string $kind): string
    {
        return InvoiceMailDocumentKind::tryFrom((string) $kind)?->label() ?? '–';
    }

    public function d3Label(?string $kind): string
    {
        return InvoiceMailDocumentKind::tryFrom((string) $kind)?->d3Label() ?? 'D3-Dokument';
    }

    public function amount(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, ',', '.').' €' : '–';
    }
};
?>

<div @if($this->isRunning()) wire:poll.5s="refreshData" @endif>
    @php($mail = $invoiceMail)
    @php($tz = config('app.timezone'))
    <x-intranet-app-assets::assets-layout
        :heading="$mail->subject ?: '(kein Betreff)'"
        subheading="Rechnungs-Mail"
        :render-app-index-auto="false"
    >
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button :href="route('apps.assets.invoice-mails')" wire:navigate icon="arrow-left" variant="ghost" size="sm">Zur Liste</flux:button>
                <flux:button wire:click="reclassify" wire:confirm="Mail neu klassifizieren? Die Anhänge werden danach neu analysiert." icon="arrow-path" size="sm">Neu klassifizieren</flux:button>
            </div>

            <flux:card class="glass-card">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm md:grid-cols-3">
                    <div>
                        <dt class="text-zinc-500">Von</dt>
                        <dd class="font-medium">{{ $mail->from_name }} &lt;{{ $mail->from_address }}&gt;</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">An</dt>
                        <dd>{{ implode(', ', $mail->to_recipients ?? []) ?: '–' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Eingang</dt>
                        <dd>{{ $mail->received_at?->timezone($tz)->format('d.m.Y H:i') }} · {{ $mail->source->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Verarbeitung</dt>
                        <dd class="flex flex-wrap items-center gap-1">
                            <flux:badge size="sm" :color="$mail->status->color()">{{ $mail->status->label() }}</flux:badge>
                            @if($mail->report_mode)
                                <flux:badge size="sm" color="sky">Report-Modus</flux:badge>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">JEV-Relevanz</dt>
                        <dd>
                            {{ $this->percent($mail->relevance_probability) }}
                            @if($mail->threshold_used !== null)
                                <span class="text-zinc-500">(Schwelle {{ $this->percent($mail->threshold_used) }})</span>
                            @endif
                            · {{ $mail->is_relevant === null ? 'offen' : ($mail->is_relevant ? 'relevant' : 'nicht relevant') }}
                            @if($mail->relevance_kind)
                                · {{ $this->kindLabel($mail->relevance_kind) }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Postfach-Aktion</dt>
                        <dd>{{ $mail->mailbox_action_at ? $mail->mailbox_action?->label().' am '.$mail->mailbox_action_at->timezone($tz)->format('d.m.Y H:i') : '–' }}</dd>
                    </div>
                </dl>

                @if($mail->irrelevant_reason)
                    <flux:text class="mt-3 text-sm">Grund: {{ $mail->irrelevant_reason }}</flux:text>
                @endif
                @if($mail->last_error)
                    <flux:callout variant="danger" icon="exclamation-triangle" class="mt-3">
                        <flux:callout.text>{{ $mail->last_error }}</flux:callout.text>
                    </flux:callout>
                @endif
            </flux:card>

            @foreach($this->attachments as $attachment)
                @php($result = $attachment->agent_result ?? [])
                @php($assigned = $attachment->effectiveUser())
                <flux:card class="glass-card space-y-4" wire:key="attachment-{{ $attachment->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:link :href="route('apps.assets.invoice-mails.attachment', $attachment->id)" target="_blank" class="font-medium">
                                <flux:icon.paper-clip class="inline size-4" /> {{ $attachment->filename }}
                            </flux:link>
                            <div class="mt-1 flex flex-wrap items-center gap-1 text-xs text-zinc-500">
                                {{ $attachment->mime_type }} · {{ number_format($attachment->size / 1024, 0, ',', '.') }} KB
                                <flux:badge size="sm" :color="$attachment->status->color()">{{ $attachment->status->label() }}</flux:badge>
                                @if($attachment->d3_status !== InvoiceMailD3Status::Uploaded && $attachment->isCandidate())
                                    <flux:select wire:model.live="kindByAttachment.{{ $attachment->id }}" class="max-w-44">
                                        <flux:select.option value="">Art wählen …</flux:select.option>
                                        @foreach(InvoiceMailDocumentKind::cases() as $documentKind)
                                            <flux:select.option value="{{ $documentKind->value }}">{{ $documentKind->label() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @elseif($attachment->document_kind)
                                    <flux:badge size="sm" color="zinc">{{ $this->kindLabel($attachment->document_kind) }}</flux:badge>
                                @endif
                                @if($attachment->text_source)
                                    <span>Text: {{ $attachment->text_source === 'pdf_text' ? 'PDF-Text' : 'OCR' }}</span>
                                @endif
                            </div>
                        </div>

                        @if($attachment->isCandidate())
                            <div class="flex flex-wrap gap-2">
                                @if($attachment->d3_status !== InvoiceMailD3Status::Uploaded)
                                    <flux:button wire:click="reanalyze({{ $attachment->id }})" size="sm" variant="ghost" icon="arrow-path">Neu analysieren</flux:button>
                                    <flux:button wire:click="openCorrection({{ $attachment->id }})" size="sm" icon="user">Empfänger korrigieren</flux:button>
                                    <flux:button wire:click="openUpload({{ $attachment->id }})" size="sm" variant="primary" icon="cloud-arrow-up" :disabled="$assigned === null">Nach D3 hochladen</flux:button>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($attachment->last_error)
                        <flux:callout variant="danger" icon="exclamation-triangle">
                            <flux:callout.text>{{ $attachment->last_error }}</flux:callout.text>
                        </flux:callout>
                    @endif

                    @if($attachment->match_status)
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="space-y-2 text-sm">
                                <flux:heading size="sm">Empfänger</flux:heading>
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" :color="$attachment->match_status->color()">{{ $attachment->match_status->label() }}</flux:badge>
                                    @if($assigned)
                                        <span class="font-medium">{{ InvoiceMailRecipientMatcher::displayName($assigned) }}</span>
                                    @endif
                                    @if($attachment->wasCorrected())
                                        <flux:badge size="sm" color="amber">korrigiert von {{ $attachment->correctedBy?->name }}</flux:badge>
                                        <flux:button wire:click="resetCorrection({{ $attachment->id }})" size="xs" variant="ghost">Zurücksetzen</flux:button>
                                    @elseif($attachment->suggested_confidence !== null)
                                        <span class="text-zinc-500">Sicherheit {{ $this->percent($attachment->suggested_confidence) }}</span>
                                    @endif
                                </div>
                                @if($attachment->wasCorrected() && $attachment->suggestedUser)
                                    <div class="text-xs text-zinc-500">Vorschlag war: {{ InvoiceMailRecipientMatcher::displayName($attachment->suggestedUser) }}</div>
                                @endif

                                @if(! empty($attachment->candidates))
                                    <div class="mt-2 space-y-1">
                                        <div class="text-xs text-zinc-500">Kandidaten</div>
                                        @foreach($attachment->candidates as $candidate)
                                            <div class="flex items-center justify-between gap-2 rounded border border-zinc-200 px-2 py-1 dark:border-zinc-700">
                                                <div>
                                                    <span class="font-medium">{{ $candidate['name'] }}</span>
                                                    <span class="text-xs text-zinc-500">{{ $this->percent($candidate['score']) }} · {{ implode(', ', $candidate['reasons'] ?? []) }}</span>
                                                </div>
                                                @if($attachment->d3_status !== InvoiceMailD3Status::Uploaded && $assigned?->id !== $candidate['user_id'])
                                                    <flux:button wire:click="correct({{ $attachment->id }}, {{ $candidate['user_id'] }})" size="xs" variant="ghost">Übernehmen</flux:button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if(! empty($result['recipients']))
                                    <div class="mt-2 space-y-1">
                                        <div class="text-xs text-zinc-500">Fundstellen der KI</div>
                                        @foreach($result['recipients'] as $hint)
                                            <div class="text-xs">
                                                <span class="font-medium">{{ $hint['name'] ?? $hint['email'] ?? '?' }}</span>
                                                ({{ ($hint['source'] ?? '') === 'mail' ? 'Mail' : 'Dokument' }}, {{ $this->percent((float) ($hint['weight'] ?? 0)) }})
                                                @if(! empty($hint['evidence']))
                                                    <span class="text-zinc-500">„{{ $hint['evidence'] }}“</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if(! empty($result['reasoning']))
                                    <flux:text class="text-xs">{{ $result['reasoning'] }}</flux:text>
                                @endif
                            </div>

                            <div class="space-y-2 text-sm">
                                <flux:heading size="sm">Belegdaten</flux:heading>
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-1">
                                    <dt class="text-zinc-500">Lieferant</dt><dd>{{ $result['supplier']['name'] ?? '–' }}</dd>
                                    <dt class="text-zinc-500">Lieferantennr.</dt><dd>{{ $result['supplier']['number'] ?? '–' }}</dd>
                                    <dt class="text-zinc-500">Rechnungsnr.</dt><dd>{{ $result['invoice']['number'] ?? '–' }}</dd>
                                    <dt class="text-zinc-500">Belegdatum</dt><dd>{{ $result['invoice']['date'] ?? '–' }}</dd>
                                    <dt class="text-zinc-500">Netto</dt><dd>{{ $this->amount($result['invoice']['net'] ?? null) }}</dd>
                                    <dt class="text-zinc-500">MwSt.</dt><dd>{{ $this->amount($result['invoice']['tax'] ?? null) }}</dd>
                                    <dt class="text-zinc-500">Brutto</dt><dd>{{ $this->amount($result['invoice']['gross'] ?? null) }}</dd>
                                </dl>

                                <flux:heading size="sm" class="pt-2">D3</flux:heading>
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" :color="$attachment->d3_status->color()">{{ $attachment->d3_status->label() }}</flux:badge>
                                    @if($attachment->d3_document_id)
                                        <span class="font-mono">{{ $attachment->d3_document_id }}</span>
                                        <span class="text-xs text-zinc-500">{{ $attachment->d3_uploaded_at?->timezone($tz)->format('d.m.Y H:i') }}, {{ $attachment->d3UploadedBy?->name ?? 'automatisch' }}</span>
                                    @endif
                                </div>
                                @if($attachment->d3_error)
                                    <div class="text-xs text-red-600 dark:text-red-400">{{ $attachment->d3_error }}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach

            <flux:card class="glass-card">
                <flux:heading size="sm" class="mb-3">Mail</flux:heading>
                <div class="overflow-y-auto rounded-lg border border-[#d0e3f9]/60 bg-white/60 p-4 dark:border-white/10 dark:bg-[#04214e]/60" style="max-height: 70vh;">
                    @if($mail->body_type === 'html')
                        <iframe
                            srcdoc="{{ $mail->body }}"
                            class="w-full rounded border-0 bg-white"
                            style="min-height: 60vh;"
                            sandbox="allow-same-origin allow-popups"
                            referrerpolicy="no-referrer"
                            onload="this.style.height = (this.contentWindow.document.documentElement.scrollHeight + 20) + 'px';"
                        ></iframe>
                    @else
                        <pre class="whitespace-pre-wrap break-words font-sans text-sm">{{ $mail->body }}</pre>
                    @endif
                </div>
            </flux:card>

            <flux:card class="glass-card">
                <flux:heading size="sm" class="mb-3">Verlauf</flux:heading>
                <div class="space-y-2">
                    @forelse($this->events as $event)
                        <div class="flex gap-3 text-sm" wire:key="event-{{ $event->id }}">
                            <div class="w-32 shrink-0 text-xs text-zinc-500">{{ $event->created_at?->timezone($tz)->format('d.m.Y H:i:s') }}</div>
                            <flux:badge size="sm" :color="$event->level->color()" class="shrink-0">{{ $event->step }}</flux:badge>
                            <div class="min-w-0">
                                <div @class(['text-red-600 dark:text-red-400' => $event->level === InvoiceMailEventLevel::Error])>{{ $event->message }}</div>
                                @if($event->duration_ms !== null)
                                    <div class="text-xs text-zinc-500">{{ number_format($event->duration_ms / 1000, 1, ',', '') }} s</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <flux:text>Noch keine Einträge.</flux:text>
                    @endforelse
                </div>
            </flux:card>
        </div>
    </x-intranet-app-assets::assets-layout>

    <flux:modal wire:model="showCorrectionModal" class="w-full max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">Empfänger korrigieren</flux:heading>
            <flux:input wire:model.live.debounce.300ms="userSearch" icon="magnifying-glass" placeholder="Name oder E-Mail …" autofocus />
            <div class="max-h-80 space-y-1 overflow-y-auto">
                @foreach($this->userResults as $user)
                    <button
                        type="button"
                        wire:key="user-result-{{ $user->id }}"
                        wire:click="correct({{ (int) $correctionAttachmentId }}, {{ $user->id }})"
                        class="flex w-full items-center justify-between rounded px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >
                        <span class="font-medium">{{ InvoiceMailRecipientMatcher::displayName($user) }}</span>
                        <span class="text-xs text-zinc-500">{{ $user->email }}</span>
                    </button>
                @endforeach
                @if(mb_strlen(trim($userSearch)) >= 2 && $this->userResults->isEmpty())
                    <flux:text>Keine aktiven Mitarbeitenden gefunden.</flux:text>
                @endif
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showUploadModal" class="w-full max-w-xl">
        @if($this->uploadAttachment)
            <div class="space-y-4">
                <flux:heading size="lg">Als {{ $this->d3Label($this->uploadAttachment->document_kind) }} nach D3 hochladen</flux:heading>
                <flux:text>{{ $this->uploadAttachment->filename }} · {{ $this->kindLabel($this->uploadAttachment->document_kind) }}</flux:text>

                @if($this->uploadPreview['error'])
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        <flux:callout.text>{{ $this->uploadPreview['error'] }}</flux:callout.text>
                    </flux:callout>
                @else
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        @foreach($this->uploadPreview['fields'] as $field => $value)
                            <dt class="text-zinc-500">{{ $field }}</dt>
                            <dd>{{ is_array($value) ? implode(', ', $value) : ($value === '' || $value === null ? '–' : $value) }}</dd>
                        @endforeach
                    </dl>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button wire:click="upload" variant="primary" icon="cloud-arrow-up" :disabled="$this->uploadPreview['error'] !== null">Hochladen</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
