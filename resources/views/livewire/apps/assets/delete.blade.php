<?php

use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Hwkdo\IntranetAppAssets\Services\AssetItexiaDeleteInventoryNotifier;
use Hwkdo\IntranetAppAssets\Support\AssetShowBackOrigin;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Asset löschen')] class extends Component {
    public Asset $asset;

    #[Url(except: null)]
    public ?string $from = null;

    #[Url(as: 'sq', except: null)]
    public ?string $searchReturnQuery = null;

    #[Validate('required|string|min:3|max:2000')]
    public string $deleteReason = '';

    #[Computed]
    public function showBackKey(): string
    {
        return AssetShowBackOrigin::resolve($this->from, auth()->user(), $this->searchReturnQuery)['key'];
    }

    public function mount(Asset $asset): void
    {
        $this->asset = $asset->load(['type', 'vendor', 'owner']);
    }

    public function delete(): void
    {
        $this->validate();

        if ($this->asset->trashed()) {
            session()->flash('error', 'Dieses Asset wurde bereits gelöscht.');
            $this->redirect(route('apps.assets.deleted'), navigate: true);

            return;
        }

        $name = $this->asset->display_name;
        $deleteReason = trim($this->deleteReason);
        $assetId = (int) $this->asset->id;
        $deletedByUserId = auth()->id();
        $snapshot = [
            'type_name' => (string) ($this->asset->type?->name ?? ''),
            'vendor_name' => (string) ($this->asset->vendor?->name ?? ''),
            'model' => (string) ($this->asset->model ?? ''),
            'itexia_id' => $this->asset->itexia_id,
            'itexia_uuid' => $this->asset->itexia_uuid,
            'display_name' => $this->asset->display_name,
        ];

        $this->asset->historyEntries()->create([
            'event' => AssetHistory::EventDeleted,
            'user_id' => $deletedByUserId,
            'reason' => $deleteReason,
        ]);
        $this->asset->delete();

        app(AssetItexiaDeleteInventoryNotifier::class)->notifyAfterSoftDelete(
            $assetId,
            $deleteReason,
            $deletedByUserId,
            $snapshot,
        );

        session()->flash('success', "Asset \"{$name}\" wurde gelöscht.");
        $this->redirect(route('apps.assets.liste'), navigate: true);
    }
};
?>
<div>
<x-intranet-app-assets::assets-layout heading="Asset löschen" subheading="Löschung bestätigen">
    <div class="space-y-6 max-w-lg">

        @if(filled($asset->itexia_id))
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>Achtung: Dieses Asset hat eine Itexia-ID</flux:callout.heading>
                <flux:callout.text>
                    Das Asset ist mit dem externen Itexia-System verknüpft (ID: <strong>{{ $asset->itexia_id }}</strong>).
                    Das Löschen wird im Verlauf protokolliert.
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-3 text-sm">
            <flux:heading size="sm">Zu löschendes Asset</flux:heading>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                <dt class="font-medium text-zinc-500">Name / Modell</dt>
                <dd class="font-medium">{{ $asset->display_name }}</dd>

                <dt class="font-medium text-zinc-500">Seriennummer</dt>
                <dd class="font-mono">{{ $asset->serial_number }}</dd>

                <dt class="font-medium text-zinc-500">Typ</dt>
                <dd>{{ $asset->type?->name ?? '—' }}</dd>

                <dt class="font-medium text-zinc-500">Hersteller</dt>
                <dd>{{ $asset->vendor?->name ?? '—' }}</dd>

                <dt class="font-medium text-zinc-500">Besitzer</dt>
                <dd>{{ $asset->owner?->name ?? '—' }}</dd>

                <dt class="font-medium text-zinc-500">Itexia-ID</dt>
                <dd class="font-mono text-red-600">{{ $asset->itexia_id }}</dd>
            </dl>
        </div>

        <div class="space-y-2">
            <flux:field>
                <flux:label>Grund für die Löschung</flux:label>
                <flux:textarea
                    wire:model="deleteReason"
                    rows="4"
                    placeholder="Bitte den Löschgrund dokumentieren..."
                />
                <flux:error name="deleteReason" />
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button
                wire:click="delete"
                wire:confirm="Asset wirklich löschen?"
                variant="danger"
                icon="trash"
            >
                Jetzt löschen
            </flux:button>
            <flux:button href="{{ route('apps.assets.show', array_filter(['asset' => $asset, 'from' => $this->showBackKey, 'sq' => $this->searchReturnQuery], fn ($v) => $v !== null && $v !== '')) }}" variant="ghost">
                Abbrechen
            </flux:button>
        </div>

    </div>
</x-intranet-app-assets::assets-layout>
</div>