<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Services\AssetLoanService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Übergabe bestätigen')] class extends Component
{
    public Handover $handover;

    public bool $showCanvas = false;

    public function mount(Handover $handover): void
    {
        if ($handover->recipient_user_id !== auth()->id()) {
            abort(403);
        }
        if ($handover->isConfirmed()) {
            session()->flash('message', 'Diese Übergabe wurde bereits bestätigt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
        }
        if ($handover->isRejected()) {
            session()->flash('message', 'Diese Übergabe wurde abgelehnt.');
            $this->redirect(route('apps.assets.meine-assets'), navigate: true);
        }

        $handover->load('recipient', 'issuer');
        $asset = null;
        if ($handover->asset_id !== null) {
            $asset = Asset::query()
                ->withTrashed()
                ->with(['type', 'vendor'])
                ->find($handover->asset_id);
        }
        $handover->setRelation('asset', $asset);

        $this->handover = $handover;
    }

    public function getFormwerkHandoverUrl(): ?string
    {
        $baseUrl = config('intranet-app-assets.formwerk_handover_url');
        if ($baseUrl === null || $baseUrl === '') {
            return null;
        }
        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl.$sep.http_build_query([
            'handover_id' => $this->handover->id,
            'asset_id' => $this->handover->asset_id,
            'recipient_id' => $this->handover->recipient_user_id,
        ]);
    }

    public function confirmByTouchscreen(string $signatureBase64): void
    {
        if ($this->handover->recipient_user_id !== auth()->id() || $this->handover->isConfirmed() || $this->handover->isRejected()) {
            abort(403);
        }
        $this->handover->update([
            'signature' => $signatureBase64,
            'confirmed_at' => now(),
            'confirmation_method' => 'touchscreen',
            'pending_confirmation_channel' => null,
        ]);
        $asset = $this->handover->asset;
        if ($asset !== null) {
            $clearedFlags = [];
            if ($asset->is_clarification) {
                $clearedFlags[] = 'is_clarification';
            }
            if ($asset->is_missing) {
                $clearedFlags[] = 'is_missing';
            }

            $asset->update([
                'is_clarification' => false,
                'is_missing' => false,
            ]);

            if ($clearedFlags !== []) {
                $asset->historyEntries()->create([
                    'event' => AssetHistory::EventHandoverConfirmedStatusCleared,
                    'user_id' => auth()->id(),
                    'reason' => 'Bei Bestätigung der Übergabe wurden Status-Flags zurückgesetzt.',
                    'meta' => [
                        'handover_id' => $this->handover->id,
                        'confirmation_method' => 'touchscreen',
                        'cleared_flags' => $clearedFlags,
                    ],
                ]);
            }
        }

        app(AssetLoanService::class)->ensureScheduledReturnAfterConfirm($this->handover->fresh() ?? $this->handover);

        session()->flash('message', 'Übergabe wurde per Touchscreen-Unterschrift bestätigt.');
        $this->redirect(route('apps.assets.meine-assets'), navigate: true);
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout heading="Übergabe bestätigen" subheading="{{ $handover->asset?->display_name ?? 'Asset' }}">
        <div class="space-y-6">
            <x-intranet-app-assets::handover-asset-summary :handover="$handover" />

            <flux:heading size="md" class="dark:text-white">Bestätigungsart wählen</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                @if($this->getFormwerkHandoverUrl())
                    <flux:card class="flex flex-col">
                        <flux:heading size="sm" class="mb-2 dark:text-white">Per Formwerk-Formular</flux:heading>
                        <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">Übergabe per Formwerk-Formular unterschreiben (öffnet externen Link).</flux:text>
                        <flux:button href="{{ $this->getFormwerkHandoverUrl() }}" target="_blank" rel="noopener" variant="primary" icon="document-text">
                            Formwerk öffnen
                        </flux:button>
                    </flux:card>
                @endif

                <flux:card class="flex flex-col">
                    <flux:heading size="sm" class="mb-2 dark:text-white">Mit Passwort bestätigen</flux:heading>
                    <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">Erhalt durch Eingabe Ihres LDAP-Passworts bestätigen.</flux:text>
                    <flux:button href="{{ route('apps.assets.handover.confirm-by-password', $handover) }}" variant="primary" icon="key">
                        Mit Passwort bestätigen
                    </flux:button>
                </flux:card>

                <flux:card class="flex flex-col">
                    <flux:heading size="sm" class="mb-2 dark:text-white">Mit Signopad</flux:heading>
                    <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">Unterschrift mit Signopad-Gerät erfassen.</flux:text>
                    <flux:button href="{{ route('apps.assets.handover.confirm-by-signopad', $handover) }}" variant="primary" icon="pencil-square">
                        Signopad öffnen
                    </flux:button>
                </flux:card>

                <flux:card class="flex flex-col">
                    <flux:heading size="sm" class="mb-2 dark:text-white">Touchscreen-Unterschrift</flux:heading>
                    <flux:text class="mb-4 flex-1 text-sm text-zinc-500 dark:text-white">Hier mit der Maus oder per Touch unterschreiben.</flux:text>
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
                                $wire.confirmByTouchscreen(data);
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
                                <flux:button @click="submit()" variant="primary" size="sm">Bestätigen</flux:button>
                            </div>
                        </div>
                    @endif
                </flux:card>
            </div>

            <flux:button href="{{ route('apps.assets.meine-assets') }}" variant="ghost" icon="arrow-left">
                Zurück zu Meine Assets
            </flux:button>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
