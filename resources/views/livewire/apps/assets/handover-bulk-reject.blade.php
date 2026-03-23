<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\Handover;
use Hwkdo\IntranetAppAssets\Support\BulkRecipientHandoverSession;
use Hwkdo\IntranetAppAssets\Support\BulkSelectionUi;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Übergaben ablehnen')] class extends Component
{
    public function mount(): void
    {
        $payload = BulkRecipientHandoverSession::getRejectPayload();
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
            BulkRecipientHandoverSession::forgetRejectPending();
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
        $payload = BulkRecipientHandoverSession::getRejectPayload();
        if ($payload === null || (int) $payload['recipient_user_id'] !== (int) auth()->id()) {
            return collect();
        }

        return Handover::query()
            ->whereIn('id', $payload['handover_ids'])
            ->where('recipient_user_id', auth()->id())
            ->whereNull('confirmed_at')
            ->whereNull('rejected_at')
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

    #[Computed]
    public function rejectionReason(): string
    {
        $payload = BulkRecipientHandoverSession::getRejectPayload();

        return $payload['reason'] ?? '';
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout
        heading="Übergaben ablehnen"
        subheading="{{ $this->handovers->count() }} ausgewählte Übergabe(n)"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Mehrfachablehnung</flux:callout.heading>
                <flux:callout.text>
                    Die folgenden Übergaben werden mit derselben Begründung abgelehnt. Anschließend ist die Bestätigung mit Ihrem LDAP-Passwort erforderlich.
                </flux:callout.text>
            </flux:callout>

            <flux:card class="space-y-4">
                <flux:heading size="sm">Betroffene Assets</flux:heading>
                <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-200">
                    @foreach($this->handovers as $h)
                        <li class="border-b border-zinc-200 pb-2 last:border-0 dark:border-zinc-700">
                            <span class="font-medium">{{ $h->asset?->display_name ?? '—' }}</span>
                            <span class="font-mono text-zinc-500"> · {{ $h->asset?->serial_number ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            </flux:card>

            <flux:card class="space-y-2">
                <flux:heading size="sm">Ihre Begründung</flux:heading>
                <flux:text class="whitespace-pre-wrap text-sm text-zinc-600 dark:text-zinc-300">{{ $this->rejectionReason }}</flux:text>
            </flux:card>

            <div class="flex flex-wrap gap-2">
                <flux:button href="{{ route('apps.assets.handover.bulk.reject-commit') }}" variant="danger" icon="key">
                    Mit Passwort bestätigen und ablehnen
                </flux:button>
                <flux:button href="{{ route('apps.assets.meine-assets') }}" variant="ghost" wire:navigate>
                    Abbrechen
                </flux:button>
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
