<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Contracts\InvoiceMailboxClientInterface;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailImportStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMailboxAction;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMatchStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailSource;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\ImportInvoiceMailsJob;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailImport;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailSubscriptionService;
use Hwkdo\IntranetAppAssets\Support\InvoiceMailImportFolders;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Rechnungs-Mails')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $relevance = '';

    #[Url]
    public string $match = '';

    #[Url]
    public bool $onlyAttention = false;

    public bool $reportMode = true;

    public string $relevanceThreshold = '0.85';

    public string $autoUploadMinConfidence = '0.9';

    public string $mailboxAction = 'none';

    public string $moveFolderId = '';

    public string $senderBlocklist = '';

    /** @var list<array{id: string, name: string}> */
    public array $folders = [];

    public bool $showSettingsModal = false;

    public bool $showImportModal = false;

    public string $importFrom = '';

    public string $importTo = '';

    /** @var list<array{id: string, name: string, path: string, depth: int}> */
    public array $importFolders = [];

    /** @var list<string> */
    public array $importFolderIds = [];

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
        $this->fillSettings();
        $this->importFrom = now()->startOfYear()->format('Y-m-d');
        $this->importTo = now()->format('Y-m-d');
    }

    private function fillSettings(): void
    {
        $settings = IntranetAppAssetsSettings::resolvedAppSettings();
        $this->reportMode = $settings->invoiceMailReportMode;
        $this->relevanceThreshold = (string) $settings->invoiceMailRelevanceThreshold;
        $this->autoUploadMinConfidence = (string) $settings->invoiceMailAutoUploadMinConfidence;
        $this->mailboxAction = $settings->invoiceMailMailboxAction->value;
        $this->moveFolderId = $settings->invoiceMailMoveFolderId;
        $this->senderBlocklist = implode("\n", $settings->invoiceMailSenderBlocklist);
    }

    #[Computed]
    public function settings(): AppSettings
    {
        return IntranetAppAssetsSettings::resolvedAppSettings();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function persist(array $changes): void
    {
        $current = IntranetAppAssetsSettings::resolvedAppSettings();
        IntranetAppAssetsSettings::persistAppSettings(AppSettings::from(array_merge($current->toArray(), $changes)));
        unset($this->settings);
    }

    public function updatedReportMode(bool $value): void
    {
        $this->authorize('manage-app-assets');
        $this->persist(['invoiceMailReportMode' => $value]);

        Flux::toast(
            $value
                ? 'Report-Modus an: Es wird nichts automatisch nach D3 hochgeladen.'
                : 'Report-Modus aus: Eindeutige, sichere Zuordnungen neuer Mails werden automatisch hochgeladen.',
            variant: $value ? 'success' : 'warning',
        );
    }

    public function saveSettings(): void
    {
        $this->authorize('manage-app-assets');

        $this->validate([
            'relevanceThreshold' => ['required', 'numeric', 'between:0,1'],
            'autoUploadMinConfidence' => ['required', 'numeric', 'between:0,1'],
            'mailboxAction' => ['required', Rule::enum(InvoiceMailMailboxAction::class)],
            'moveFolderId' => [Rule::requiredIf($this->mailboxAction === InvoiceMailMailboxAction::MoveToFolder->value), 'nullable', 'string', 'max:512'],
            'senderBlocklist' => ['nullable', 'string', 'max:5000'],
        ], [
            'moveFolderId.required' => 'Bitte einen Zielordner wählen.',
        ]);

        $this->persist([
            'invoiceMailRelevanceThreshold' => (float) $this->relevanceThreshold,
            'invoiceMailAutoUploadMinConfidence' => (float) $this->autoUploadMinConfidence,
            'invoiceMailMailboxAction' => $this->mailboxAction,
            'invoiceMailMoveFolderId' => trim($this->moveFolderId),
            'invoiceMailSenderBlocklist' => collect(preg_split('/[\s,;]+/', $this->senderBlocklist) ?: [])
                ->map(fn (string $entry): string => mb_strtolower(trim($entry)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);

        $this->showSettingsModal = false;
        Flux::toast('Einstellungen gespeichert.', variant: 'success');
    }

    public function loadFolders(InvoiceMailboxClientInterface $mailbox): void
    {
        $this->authorize('manage-app-assets');

        try {
            $this->folders = $mailbox->folders($this->settings->invoiceMailMailboxUpn);
        } catch (\Throwable $e) {
            Flux::toast('Ordner konnten nicht geladen werden: '.$e->getMessage(), variant: 'danger');
        }
    }

    public function ensureSubscription(InvoiceMailSubscriptionService $subscriptions): void
    {
        $this->authorize('manage-app-assets');

        try {
            $result = $subscriptions->ensure($this->settings->invoiceMailMailboxUpn);
            Flux::toast($result['message'], variant: $result['ok'] ? 'success' : 'danger');
        } catch (\Throwable $e) {
            Flux::toast('Subscription fehlgeschlagen: '.$e->getMessage(), variant: 'danger');
        }

        unset($this->subscriptionStatus);
    }

    public function updatedShowImportModal(bool $open): void
    {
        if ($open && $this->importFolders === []) {
            $this->loadImportFolders();
        }
    }

    public function loadImportFolders(): void
    {
        $this->authorize('manage-app-assets');
        $mailbox = app(InvoiceMailboxClientInterface::class);

        try {
            $this->importFolders = $mailbox->folders($this->settings->invoiceMailMailboxUpn);
        } catch (\Throwable $e) {
            Flux::toast('Ordner konnten nicht geladen werden: '.$e->getMessage(), variant: 'danger');

            return;
        }

        $this->importFolderIds = collect($this->importFolders)
            ->filter(fn (array $folder): bool => InvoiceMailImportFolders::shouldPreselect($folder['name'], $folder['depth']))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function missingImportFolderNames(): array
    {
        $normalized = collect($this->importFolders)
            ->map(fn (array $folder): string => InvoiceMailImportFolders::normalize($folder['name']));

        $missing = [];
        if ($this->importFolders !== [] && ! $normalized->contains('einvoice')) {
            $missing[] = 'eInvoice';
        }
        if ($this->importFolders !== [] && ! $normalized->contains(fn (string $name): bool => str_starts_with($name, 'geloeschte'))) {
            $missing[] = 'Gelöschte Objekte';
        }

        return $missing;
    }

    public function startImport(): void
    {
        $this->authorize('manage-app-assets');

        $this->validate([
            'importFrom' => ['required', 'date'],
            'importTo' => ['required', 'date', 'after_or_equal:importFrom', 'before_or_equal:today'],
            'importFolderIds' => ['required', 'array', 'min:1'],
            'importFolderIds.*' => ['string', 'max:512'],
        ], [
            'importTo.after_or_equal' => 'Das Ende muss nach dem Beginn liegen.',
            'importFolderIds.required' => 'Bitte mindestens einen Ordner wählen.',
            'importFolderIds.min' => 'Bitte mindestens einen Ordner wählen.',
        ]);

        if (InvoiceMailImport::query()->whereIn('status', [InvoiceMailImportStatus::Queued, InvoiceMailImportStatus::Running])->exists()) {
            Flux::toast('Es läuft bereits ein Import.', variant: 'warning');

            return;
        }

        $timezone = config('app.timezone');
        $known = collect($this->importFolders)->keyBy('id');
        $selected = collect($this->importFolderIds)
            ->filter(fn (string $id): bool => $known->has($id))
            ->map(fn (string $id): array => ['id' => $id, 'path' => $known[$id]['path']])
            ->values()
            ->all();

        if ($selected === []) {
            Flux::toast('Bitte mindestens einen geladenen Ordner wählen.', variant: 'warning');

            return;
        }

        $import = InvoiceMailImport::query()->create([
            'mailbox_upn' => $this->settings->invoiceMailMailboxUpn,
            'folders' => $selected,
            'received_from' => Carbon::parse($this->importFrom, $timezone)->startOfDay(),
            'received_to' => Carbon::parse($this->importTo, $timezone)->endOfDay(),
            'status' => InvoiceMailImportStatus::Queued,
            'started_by' => auth()->id(),
        ]);

        ImportInvoiceMailsJob::dispatch($import->id);

        $this->showImportModal = false;
        unset($this->imports);
        Flux::toast('Import gestartet. Die Mails laufen danach durch die normale Verarbeitung.', variant: 'success');
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'relevance', 'match', 'onlyAttention'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, InvoiceMail>
     */
    #[Computed]
    public function mails(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return InvoiceMail::query()
            ->with(['attachments.suggestedUser', 'attachments.correctedUser'])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('subject', 'like', '%'.$search.'%')
                ->orWhere('from_address', 'like', '%'.$search.'%')
                ->orWhere('from_name', 'like', '%'.$search.'%')))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->relevance === 'yes', fn (Builder $q) => $q->where('is_relevant', true))
            ->when($this->relevance === 'no', fn (Builder $q) => $q->where('is_relevant', false))
            ->when($this->match !== '', fn (Builder $q) => $q->whereHas('attachments', fn (Builder $a) => $a->where('match_status', $this->match)))
            ->when($this->onlyAttention, fn (Builder $q) => $q->needsAttention())
            ->orderByDesc('received_at')
            ->paginate(25);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function kpis(): array
    {
        $since = now()->subDays(7);

        return [
            'week' => InvoiceMail::query()->where('received_at', '>=', $since)->count(),
            'relevant' => InvoiceMail::query()->where('received_at', '>=', $since)->where('is_relevant', true)->count(),
            'running' => InvoiceMail::query()->whereIn('status', [InvoiceMailStatus::Received, InvoiceMailStatus::Classifying, InvoiceMailStatus::Analyzing])->count(),
            'failed' => InvoiceMail::query()->where('status', InvoiceMailStatus::Failed)->count(),
            'attention' => InvoiceMail::query()->needsAttention()->count(),
            'unique' => InvoiceMailAttachment::query()->where('created_at', '>=', $since)->where('match_status', InvoiceMailMatchStatus::Unique)->count(),
            'corrected' => InvoiceMailAttachment::query()->where('created_at', '>=', $since)->whereNotNull('corrected_user_id')->count(),
            'uploaded' => InvoiceMailAttachment::query()->where('d3_status', InvoiceMailD3Status::Uploaded)->count(),
        ];
    }

    /**
     * @return Collection<int, InvoiceMailImport>
     */
    #[Computed]
    public function imports(): Collection
    {
        return InvoiceMailImport::query()->with('starter')->latest()->limit(5)->get();
    }

    /**
     * @return array{active: bool, mapping: bool, resource_matches: bool, expires_at: ?string}
     */
    #[Computed]
    public function subscriptionStatus(): array
    {
        try {
            return app(InvoiceMailSubscriptionService::class)->status($this->settings->invoiceMailMailboxUpn);
        } catch (\Throwable) {
            return ['active' => false, 'mapping' => false, 'resource_matches' => false, 'expires_at' => null];
        }
    }

    public function hasRunningWork(): bool
    {
        return $this->kpis['running'] > 0
            || $this->imports->contains(fn (InvoiceMailImport $i): bool => in_array($i->status, [InvoiceMailImportStatus::Queued, InvoiceMailImportStatus::Running], true));
    }

    public function primaryAttachment(InvoiceMail $mail): ?InvoiceMailAttachment
    {
        return $mail->attachments->first(fn (InvoiceMailAttachment $a): bool => $a->agent_result !== null)
            ?? $mail->attachments->first(fn (InvoiceMailAttachment $a): bool => $a->isCandidate());
    }

    public function percent(?float $value): string
    {
        return $value === null ? '–' : number_format($value * 100, 1, ',', '').' %';
    }
};
?>

<div @if($this->hasRunningWork()) wire:poll.10s @endif>
    <x-intranet-app-assets::assets-layout
        heading="Rechnungs-Mails"
        :subheading="'Sammelpostfach '.$this->settings->invoiceMailMailboxUpn.': Relevanz, ermittelter Empfänger und D3-Upload'"
        :render-app-index-auto="false"
    >
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:switch wire:model.live="reportMode" label="Report-Modus" />
                    @if($reportMode)
                        <flux:badge color="sky" icon="eye">Nur Bericht, kein automatischer D3-Upload</flux:badge>
                    @else
                        <flux:badge color="amber" icon="bolt">Automatischer D3-Upload aktiv</flux:badge>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="$set('showImportModal', true)" icon="arrow-down-tray" size="sm">Import</flux:button>
                    <flux:button wire:click="$set('showSettingsModal', true)" icon="cog-6-tooth" size="sm">Einstellungen</flux:button>
                </div>
            </div>

            @php($subscription = $this->subscriptionStatus)
            @if(! $subscription['active'] || ! $subscription['resource_matches'])
                <flux:callout variant="warning" icon="signal-slash">
                    <flux:callout.heading>Keine aktive Subscription für {{ $this->settings->invoiceMailMailboxUpn }}</flux:callout.heading>
                    <flux:callout.text>Ohne Subscription kommen keine neuen Mails an. Der Import funktioniert trotzdem.</flux:callout.text>
                    <x-slot name="actions">
                        <flux:button wire:click="ensureSubscription" size="sm" variant="primary">Subscription einrichten</flux:button>
                    </x-slot>
                </flux:callout>
            @else
                <flux:text class="text-sm text-zinc-500">
                    Subscription aktiv{{ $subscription['expires_at'] ? ', läuft bis '.$subscription['expires_at'].' (wird automatisch erneuert)' : '' }}.
                    <flux:link wire:click="ensureSubscription" class="cursor-pointer">Jetzt prüfen</flux:link>
                </flux:text>
            @endif

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                @foreach([
                    ['Mails (7 Tage)', $this->kpis['week'], 'zinc'],
                    ['Davon relevant', $this->kpis['relevant'], 'sky'],
                    ['Eindeutig zugeordnet (7 Tage)', $this->kpis['unique'], 'green'],
                    ['Korrigiert (7 Tage)', $this->kpis['corrected'], 'amber'],
                    ['In Arbeit', $this->kpis['running'], 'sky'],
                    ['Fehler', $this->kpis['failed'], 'red'],
                    ['Braucht Prüfung', $this->kpis['attention'], 'amber'],
                    ['Nach D3 hochgeladen', $this->kpis['uploaded'], 'green'],
                ] as [$label, $value, $color])
                    <flux:card class="glass-card p-4">
                        <flux:text class="text-xs">{{ $label }}</flux:text>
                        <flux:heading size="xl" @class([
                            'mt-1',
                            'text-red-600 dark:text-red-400' => $color === 'red' && $value > 0,
                            'text-amber-600 dark:text-amber-400' => $color === 'amber' && $value > 0,
                        ])>{{ $value }}</flux:heading>
                    </flux:card>
                @endforeach
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Betreff oder Absender …" class="max-w-xs" />
                <flux:select wire:model.live="status" class="max-w-48">
                    <flux:select.option value="">Alle Status</flux:select.option>
                    @foreach(InvoiceMailStatus::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="relevance" class="max-w-40">
                    <flux:select.option value="">Relevanz: alle</flux:select.option>
                    <flux:select.option value="yes">Relevant</flux:select.option>
                    <flux:select.option value="no">Nicht relevant</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="match" class="max-w-48">
                    <flux:select.option value="">Zuordnung: alle</flux:select.option>
                    @foreach(InvoiceMailMatchStatus::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:checkbox wire:model.live="onlyAttention" label="Nur zu prüfen" />
            </div>

            <flux:table :paginate="$this->mails">
                <flux:table.columns>
                    <flux:table.column>Eingang</flux:table.column>
                    <flux:table.column>Absender / Betreff</flux:table.column>
                    <flux:table.column>JEV</flux:table.column>
                    <flux:table.column>Relevant</flux:table.column>
                    <flux:table.column>Verarbeitung</flux:table.column>
                    <flux:table.column>Ergebnis</flux:table.column>
                    <flux:table.column>D3</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->mails as $mail)
                        @php($attachment = $this->primaryAttachment($mail))
                        @php($assigned = $attachment?->effectiveUser())
                        <flux:table.row wire:key="invoice-mail-{{ $mail->id }}">
                            <flux:table.cell class="whitespace-nowrap text-sm">
                                {{ $mail->received_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                @if($mail->source === InvoiceMailSource::Import)
                                    <flux:badge size="sm" color="zinc" class="ml-1">Import</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-w-md">
                                <flux:link :href="route('apps.assets.invoice-mails.show', $mail)" wire:navigate class="block truncate font-medium">
                                    {{ $mail->subject ?: '(kein Betreff)' }}
                                </flux:link>
                                <div class="truncate text-xs text-zinc-500">{{ $mail->from_name }} &lt;{{ $mail->from_address }}&gt;</div>
                            </flux:table.cell>
                            <flux:table.cell class="text-sm tabular-nums">{{ $this->percent($mail->relevance_probability) }}</flux:table.cell>
                            <flux:table.cell>
                                @if($mail->is_relevant === true)
                                    <flux:badge size="sm" color="green">Ja</flux:badge>
                                @elseif($mail->is_relevant === false)
                                    <flux:badge size="sm" color="zinc" title="{{ $mail->irrelevant_reason }}">Nein</flux:badge>
                                @else
                                    <span class="text-zinc-400">–</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$mail->status->color()" :icon="$mail->status->isInProgress() ? 'arrow-path' : null">{{ $mail->status->label() }}</flux:badge>
                                @if($mail->last_error)
                                    <div class="mt-1 max-w-48 truncate text-xs text-red-600 dark:text-red-400" title="{{ $mail->last_error }}">{{ $mail->last_error }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-sm">
                                @if($attachment?->match_status)
                                    @if($assigned)
                                        <div class="font-medium">{{ $assigned->ldap_displayname ?: $assigned->name }}</div>
                                    @endif
                                    <div class="flex items-center gap-1">
                                        <flux:badge size="sm" :color="$attachment->match_status->color()">{{ $attachment->match_status->label() }}</flux:badge>
                                        @if($attachment->document_kind)
                                            <flux:badge size="sm" color="zinc">{{ \Hwkdo\IntranetAppAssets\Enums\InvoiceMailDocumentKind::tryFrom($attachment->document_kind)?->label() }}</flux:badge>
                                        @endif
                                        @if($attachment->wasCorrected())
                                            <flux:badge size="sm" color="amber">korrigiert</flux:badge>
                                        @elseif($attachment->suggested_confidence !== null)
                                            <span class="text-xs text-zinc-500">{{ $this->percent($attachment->suggested_confidence) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-zinc-400">–</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($attachment && $attachment->d3_status !== InvoiceMailD3Status::None)
                                    <flux:badge size="sm" :color="$attachment->d3_status->color()">{{ $attachment->d3_status->label() }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">–</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="py-8 text-center text-zinc-500">Keine Mails gefunden.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-intranet-app-assets::assets-layout>

    <flux:modal wire:model="showSettingsModal" class="w-full max-w-xl">
        <form wire:submit="saveSettings" class="space-y-5">
            <flux:heading size="lg">Einstellungen Rechnungs-Mails</flux:heading>

            <flux:input wire:model="relevanceThreshold" type="number" step="0.01" min="0" max="1" label="Relevanz-Schwelle (JEV, 0–1)" description="Ab dieser Wahrscheinlichkeit gilt eine Mail als relevant." />
            <flux:input wire:model="autoUploadMinConfidence" type="number" step="0.01" min="0" max="1" label="Mindest-Sicherheit für Auto-Upload (0–1)" description="Gilt nur bei ausgeschaltetem Report-Modus." />

            <flux:select wire:model.live="mailboxAction" label="Nach der Verarbeitung im Postfach">
                @foreach(InvoiceMailMailboxAction::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if($mailboxAction === InvoiceMailMailboxAction::MoveToFolder->value)
                <div class="flex items-end gap-2">
                    @if($folders !== [])
                        <flux:select wire:model="moveFolderId" label="Zielordner" class="flex-1">
                            <flux:select.option value="">Bitte wählen …</flux:select.option>
                            @foreach($folders as $folder)
                                <flux:select.option value="{{ $folder['id'] }}">{{ $folder['path'] ?? $folder['name'] }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input wire:model="moveFolderId" label="Zielordner-ID" class="flex-1" readonly />
                    @endif
                    <flux:button wire:click="loadFolders" icon="folder-open">Ordner laden</flux:button>
                </div>
            @endif

            <flux:textarea wire:model="senderBlocklist" rows="3" label="Absender-Sperrliste" description="Eine Adresse oder @domain pro Zeile. Diese Mails gelten ohne KI-Prüfung als irrelevant." />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Speichern</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showImportModal" class="w-full max-w-2xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Mails importieren</flux:heading>
                <flux:text class="mt-1">Holt einmalig die Mails der gewählten Ordner im Zeitraum. Bereits erfasste Mails werden übersprungen.</flux:text>
            </div>

            <form wire:submit="startImport" class="space-y-4">
                <div class="flex flex-wrap items-end gap-3">
                    <flux:input wire:model="importFrom" type="date" label="Von" />
                    <flux:input wire:model="importTo" type="date" label="Bis" />
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <flux:label>Ordner</flux:label>
                        <flux:button type="button" wire:click="loadImportFolders" size="sm" icon="folder-open" variant="ghost">Neu laden</flux:button>
                    </div>
                    @if($importFolders === [])
                        <flux:text class="text-sm">Ordner werden geladen …</flux:text>
                    @else
                        <div class="max-h-52 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            @foreach($importFolders as $folder)
                                <flux:checkbox
                                    wire:model="importFolderIds"
                                    value="{{ $folder['id'] }}"
                                    label="{{ $folder['path'] }}"
                                    wire:key="import-folder-{{ $folder['id'] }}"
                                />
                            @endforeach
                        </div>
                    @endif
                    @error('importFolderIds')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror
                    @if($this->missingImportFolderNames !== [])
                        <flux:callout variant="warning" icon="folder">
                            <flux:callout.text>Nicht gefunden: {{ implode(', ', $this->missingImportFolderNames) }}. Bitte den Namen im Postfach prüfen.</flux:callout.text>
                        </flux:callout>
                    @endif
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="play">Import starten</flux:button>
                </div>
            </form>

            @if($this->imports->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Zeitraum</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Gefunden</flux:table.column>
                        <flux:table.column>Neu</flux:table.column>
                        <flux:table.column>Doppelt</flux:table.column>
                        <flux:table.column>Fehler</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->imports as $import)
                            <flux:table.row wire:key="import-{{ $import->id }}">
                                <flux:table.cell class="text-sm">
                                    {{ $import->received_from?->timezone(config('app.timezone'))->format('d.m.Y') }} – {{ $import->received_to?->timezone(config('app.timezone'))->format('d.m.Y') }}
                                    <div class="text-xs text-zinc-500">{{ $import->starter?->name }}, {{ $import->created_at?->format('d.m.Y H:i') }}</div>
                                    @if(is_array($import->folders) && $import->folders !== [])
                                        <div class="text-xs text-zinc-500">{{ collect($import->folders)->pluck('path')->filter()->implode(', ') }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$import->status->color()">{{ $import->status->label() }}</flux:badge>
                                    @if($import->last_error)
                                        <div class="mt-1 max-w-48 truncate text-xs text-red-600" title="{{ $import->last_error }}">{{ $import->last_error }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $import->found }}</flux:table.cell>
                                <flux:table.cell>{{ $import->queued }}</flux:table.cell>
                                <flux:table.cell>{{ $import->skipped_duplicates }}</flux:table.cell>
                                <flux:table.cell>{{ $import->failed }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </flux:modal>
</div>
