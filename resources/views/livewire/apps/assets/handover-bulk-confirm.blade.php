<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\BulkRecipientHandoverSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Übergaben bestätigen')] class extends Component
{
    public bool $showCanvas = false;

    public function mount(): void
    {
        $payload = BulkRecipientHandoverSession::getConfirmPayload();
        if ($payload === null || (int) $payload['recipient_user_id'] !== (int) auth()->id()) {
            BulkRecipientHandoverSession::forgetMyAssetsBulkSelection();
            $this->js(BulkSelectionUi::livewireClearSelectionJs());
            session()->flash('error', 'Ungültige oder abgelaufene Mehrfachaktion. Bitte erneut auswählen.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);

            return;
        }

        $eligible = Handover::query()
            ->whereIn('id', $payload['handover_ids'])
            ->where('recipient_user_id', auth()->id())
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->count();

        if ($eligible !== count($payload['handover_ids'])) {
            BulkRecipientHandoverSession::forgetConfirmPending();
            BulkRecipientHandoverSession::forgetMyAssetsBulkSelection();
            $this->js(BulkSelectionUi::livewireClearSelectionJs());
            session()->flash('error', 'Die Auswahl ist nicht mehr gültig. Bitte erneut auswählen.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);

            return;
        }
    }

    #[Computed]
    public function handovers(): \Illuminate\Database\Eloquent\Collection
    {
        $payload = BulkRecipientHandoverSession::getConfirmPayload();
        if ($payload === null || (int) $payload['recipient_user_id'] !== (int) auth()->id()) {
            return collect();
        }

        return Handover::query()
            ->whereIn('id', $payload['handover_ids'])
            ->where('recipient_user_id', auth()->id())
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
            ->with(['recipient', 'issuer'])
            ->get()
            ->each(function (Handover $handover): void {
                $asset = null;
                if ($handover->asset_id !== null) {
                    $asset = Asset::query()
                        ->withTrashed()
                        ->with(['type', 'vendor'])
                        ->find($handover->asset_id);
                }
                $handover->setRelation('asset', $asset);
            });
    }

    public function getFormwerkHandoverUrl(Handover $handover): ?string
    {
        $baseUrl = config('intranet-app-assets.formwerk_handover_url');
        if ($baseUrl === null || $baseUrl === '') {
            return null;
        }
        $sep = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$sep.http_build_query([
            'handover_id' => $handover->id,
            'asset_id' => $handover->asset_id,
            'recipient_id' => $handover->recipient_user_id,
        ]);
    }

    public function confirmBulkByTouchscreen(string $signatureBase64): void
    {
        $payload = BulkRecipientHandoverSession::getConfirmPayload();
        if ($payload === null || (int) $payload['recipient_user_id'] !== (int) auth()->id()) {
            abort(403);
        }

        $service = app(\Hwkdo\IntranetAppAssets\Services\RecipientHandoverConfirmationService::class);
        $userId = (int) auth()->id();
        $processed = 0;
        $failed = 0;

        foreach ($this->handovers as $handover) {
            try {
                $service->confirmForRecipient(
                    $handover,
                    $userId,
                    \Hwkdo\IntranetAppAssets\Services\RecipientHandoverConfirmationService::METHOD_TOUCHSCREEN,
                    $signatureBase64,
                );
                $processed++;
            } catch (\InvalidArgumentException) {
                $failed++;
            }
        }

        BulkRecipientHandoverSession::forgetConfirmPending();
        BulkRecipientHandoverSession::forgetMyAssetsBulkSelection();
        $this->js(BulkSelectionUi::livewireClearSelectionJs());
        session()->flash('message', "Übergaben per Touchscreen bestätigt: {$processed} erfolgreich".($failed > 0 ? ", {$failed} übersprungen." : '.'));
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout
        heading="Übergaben bestätigen"
        subheading="{{ $this->handovers->count() }} ausgewählte Übergabe(n)"
    >
        <div class="space-y-6">
            <flux:card class="space-y-3">
                <flux:heading size="sm">Ausgewählte Assets</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Asset</flux:table.column>
                        <flux:table.column>Seriennummer</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->handovers as $h)
                            <flux:table.row wire:key="bulk-ho-{{ $h->id }}">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $h->asset?->display_name ?? '—' }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $h->asset?->serial_number ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>

            <flux:heading size="md" class="dark:text-white">Bestätigungsart wählen</flux:heading>
            <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                Die gewählte Methode gilt für alle oben gelisteten Übergaben.
            </flux:text>

            <div class="grid gap-4 sm:grid-cols-2">
                @if(config('intranet-app-assets.formwerk_handover_url'))
                    <flux:card class="flex flex-col">
                        <flux:heading size="sm" class="mb-2 dark:text-white">Per Formwerk-Formular</flux:heading>
                        <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">
                            Pro Übergabe ein Formular öffnen und unterschreiben.
                        </flux:text>
                        <div class="flex flex-col gap-2">
                            @foreach($this->handovers as $h)
                                @if($this->getFormwerkHandoverUrl($h))
                                    <flux:button
                                        href="{{ $this->getFormwerkHandoverUrl($h) }}"
                                        target="_blank"
                                        rel="noopener"
                                        variant="ghost"
                                        size="sm"
                                        icon="document-text"
                                    >
                                        Formwerk: {{ \Illuminate\Support\Str::limit($h->asset?->display_name ?? 'Übergabe #'.$h->id, 40) }}
                                    </flux:button>
                                @endif
                            @endforeach
                        </div>
                    </flux:card>
                @endif

                <flux:card class="flex flex-col">
                    <flux:heading size="sm" class="mb-2 dark:text-white">Mit Passwort bestätigen</flux:heading>
                    <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">Erhalt durch Eingabe Ihres LDAP-Passworts bestätigen.</flux:text>
                    <flux:button href="{{ route('apps.assets.handover.bulk.confirm-by-password') }}" variant="primary" icon="key">
                        Mit Passwort bestätigen
                    </flux:button>
                </flux:card>

                <flux:card class="flex flex-col">
                    <flux:heading size="sm" class="mb-2 dark:text-white">Mit Signopad</flux:heading>
                    <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">Eine Unterschrift für alle Übergaben.</flux:text>
                    <flux:button href="{{ route('apps.assets.handover.bulk.confirm-by-signopad') }}" variant="primary" icon="pencil-square">
                        Signopad öffnen
                    </flux:button>
                </flux:card>

                <flux:card class="flex flex-col">
                    <flux:heading size="sm" class="mb-2 dark:text-white">Touchscreen-Unterschrift</flux:heading>
                    <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">Eine Unterschrift für alle Übergaben.</flux:text>
                    @if(!$showCanvas)
                        <flux:button wire:click="$set('showCanvas', true)" variant="primary" icon="device-phone-mobile">
                            Unterschrift zeichnen
                        </flux:button>
                    @else
                        <div class="space-y-3" x-data="{
                            canvas: null,
                            ctx: null,
                            drawing: false,
                            init() {
                                this.$nextTick(() => {
                                    const el = this.$refs.canvas;
                                    if (!el) return;
                                    this.canvas = el;
                                    this.ctx = el.getContext('2d');
                                    el.width = el.offsetWidth;
                                    el.height = 200;
                                    this.ctx.strokeStyle = '#000';
                                    this.ctx.lineWidth = 2;
                                    this.ctx.lineCap = 'round';
                                });
                            },
                            start(e) {
                                this.drawing = true;
                                const rect = this.canvas.getBoundingClientRect();
                                this.ctx.beginPath();
                                this.ctx.moveTo((e.touches ? e.touches[0].clientX : e.clientX) - rect.left, (e.touches ? e.touches[0].clientY : e.clientY) - rect.top);
                            },
                            move(e) {
                                if (!this.drawing) return;
                                e.preventDefault();
                                const rect = this.canvas.getBoundingClientRect();
                                const x = e.touches ? e.touches[0].clientX - rect.left : e.clientX - rect.left;
                                const y = e.touches ? e.touches[0].clientY - rect.top : e.clientY - rect.top;
                                this.ctx.lineTo(x, y);
                                this.ctx.stroke();
                            },
                            end() { this.drawing = false; },
                            clear() {
                                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                            },
                            submit() {
                                const data = this.canvas.toDataURL('image/png');
                                $wire.confirmBulkByTouchscreen(data);
                            }
                        }" x-init="init()">
                            <canvas
                                x-ref="canvas"
                                class="w-full rounded border border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-800"
                                style="touch-action: none;"
                                @mousedown="start($event)"
                                @mousemove="move($event)"
                                @mouseup="end()"
                                @mouseleave="end()"
                                @touchstart.prevent="start($event)"
                                @touchmove.prevent="move($event)"
                                @touchend="end()"
                            ></canvas>
                            <div class="flex gap-2">
                                <flux:button wire:click="$set('showCanvas', false)" variant="ghost" size="sm">Abbrechen</flux:button>
                                <flux:button @click="clear()" variant="ghost" size="sm">Löschen</flux:button>
                                <flux:button @click="submit()" variant="primary" size="sm">Alle bestätigen</flux:button>
                            </div>
                        </div>
                    @endif
                </flux:card>
            </div>

            <flux:button href="{{ route('apps.assets.meine-assets') }}" variant="ghost" icon="arrow-left" wire:navigate>
                Zurück zu Meine Assets
            </flux:button>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
