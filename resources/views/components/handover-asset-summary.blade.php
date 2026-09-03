@props([
    'handover',
])

<flux:card>
    <flux:heading size="lg" class="mb-4 dark:text-white">Asset</flux:heading>
    @if($handover->asset)
        @if($handover->asset->trashed())
            <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
                <flux:callout.text>Dieses Asset ist soft-gelöscht; die Angaben dienen nur der Zuordnung.</flux:callout.text>
            </flux:callout>
        @endif
        <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
            <dt class="font-semibold text-zinc-500 dark:text-white">Anzeigename</dt>
            <dd class="text-zinc-900 dark:text-white">{{ $handover->asset->display_name }}</dd>

            <dt class="font-semibold text-zinc-500 dark:text-white">Name</dt>
            <dd class="text-zinc-900 dark:text-white">{{ filled($handover->asset->name) ? $handover->asset->name : '—' }}</dd>

            <dt class="font-semibold text-zinc-500 dark:text-white">Hersteller</dt>
            <dd class="text-zinc-900 dark:text-white">{{ $handover->asset->vendor?->name ?? '—' }}</dd>

            <dt class="font-semibold text-zinc-500 dark:text-white">Modell</dt>
            <dd class="text-zinc-900 dark:text-white">{{ filled($handover->asset->model) ? $handover->asset->model : '—' }}</dd>

            <dt class="font-semibold text-zinc-500 dark:text-white">Typ</dt>
            <dd class="text-zinc-900 dark:text-white">{{ $handover->asset->type?->name ?? '—' }}</dd>

            <dt class="font-semibold text-zinc-500 dark:text-white">Besitzer (laut System)</dt>
            <dd class="text-zinc-900 dark:text-white">{{ $handover->asset->owner?->name ?? '—' }}</dd>

            @php
                $locationDisplay = \Hwkdo\IntranetAppAssets\Services\AssetLocationDisplayResolver::resolve($handover->asset);
            @endphp
            <dt class="font-semibold text-zinc-500 dark:text-white">{{ $locationDisplay['label'] }}</dt>
            <x-intranet-app-assets::asset-location-display :asset="$handover->asset" class="text-zinc-900 dark:text-white" />

            <dt class="font-semibold text-zinc-500 dark:text-white">Seriennummer</dt>
            <dd class="font-mono text-zinc-900 dark:text-white">{{ filled($handover->asset->serial_number) ? $handover->asset->serial_number : '—' }}</dd>

            <dt class="font-semibold text-zinc-500 dark:text-white">Itexia-ID</dt>
            <dd class="font-mono text-zinc-900 dark:text-white">{{ filled($handover->asset->itexia_id) ? $handover->asset->itexia_id : '—' }}</dd>
        </dl>
        @unless($handover->asset->trashed())
            <div class="mt-4">
                <flux:button
                    href="{{ route('apps.assets.show', $handover->asset) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    variant="ghost"
                    icon="eye"
                    size="sm"
                >
                    Asset-Detail öffnen
                </flux:button>
            </div>
        @endunless
    @else
        <flux:text class="text-zinc-500 dark:text-white">
            Kein Asset mit dieser Übergabe verknüpft (asset_id fehlt oder Datensatz existiert nicht).
        </flux:text>
    @endif
</flux:card>
