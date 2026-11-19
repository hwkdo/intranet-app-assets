@php
    use Hwkdo\IntranetAppAssets\Services\AssetMissingAdminResolutionService;
    use Hwkdo\IntranetAppAssets\Services\AssetLocationDisplayResolver;

    $locationDisplay = AssetLocationDisplayResolver::resolve($asset);
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Vermisst-Fall bearbeiten"
        subheading="{{ $asset->display_name }}"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <section class="glass-card overflow-hidden p-5">
                <flux:heading size="lg" class="mb-4 dark:text-white">Asset</flux:heading>
                <dl class="hwk-rows">
                    <dt class="font-semibold text-slate-600 dark:text-white">Anzeigename</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $asset->display_name }}</dd>
                    <dt class="font-semibold text-slate-600 dark:text-white">Besitzer (laut System)</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $asset->owner?->name ?? '—' }}</dd>
                    <dt class="font-semibold text-slate-600 dark:text-white">{{ $locationDisplay['label'] }}</dt>
                    <x-intranet-app-assets::asset-location-display :asset="$asset" class="text-[#073070] dark:text-white" />
                    <dt class="font-semibold text-slate-600 dark:text-white">Seriennummer</dt>
                    <dd class="font-mono text-[#073070] dark:text-white">{{ $asset->serial_number }}</dd>
                </dl>
                <div class="mt-4">
                    <flux:button
                        href="{{ route('apps.assets.show', $asset) }}"
                        target="_blank"
                        rel="noopener"
                        variant="ghost"
                        icon="eye"
                        size="sm"
                    >
                        Asset-Detail öffnen
                    </flux:button>
                </div>
            </section>

            @php $lastNote = $asset->notes()->latest('id')->first(); @endphp
            @if($lastNote)
                <section class="glass-card overflow-hidden space-y-2 text-sm">
                    <flux:heading size="sm" class="dark:text-white">Letzte Notiz</flux:heading>
                    <flux:text class="whitespace-pre-wrap text-slate-600 dark:text-white/70">{{ $lastNote->note }}</flux:text>
                </section>
            @endif

            @if(! $asset->trashed())
                <section class="glass-card overflow-hidden space-y-3 border-red-200 dark:border-red-900/40">
                    <flux:heading size="sm" class="dark:text-white">Asset löschen</flux:heading>
                    <flux:text class="text-sm text-slate-600 dark:text-white/70">
                        Zur regulären Löschseite mit Löschgrund, Verlauf und LDAP-Bestätigung.
                    </flux:text>
                    <flux:button href="{{ route('apps.assets.delete', $asset) }}" variant="danger" icon="trash">
                        Zur Löschseite
                    </flux:button>
                </section>
            @endif

            <form wire:submit="submit" class="hwk-fields glass-card space-y-6 p-5 sm:p-6" style="overflow: visible">
                <section class="glass-card overflow-hidden space-y-4 dark:[&_[data-flux-label]]:text-white dark:[&_legend]:text-white">
                    <flux:checkbox
                        wire:model="acknowledgeReview"
                        label="Ich habe den Vermisst-Fall und den Verlauf geprüft."
                    />
                    @error('acknowledgeReview')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <flux:radio.group wire:model.live="resolution" label="Weiteres Vorgehen">
                        <flux:radio
                            value="{{ AssetMissingAdminResolutionService::ResolutionClearOnly }}"
                            label="Wieder aufgetaucht — unverändert"
                            description="Nur das Flag „Vermisst“ entfernen; Besitzer, Standort und Übergaben bleiben unverändert."
                        />
                        <flux:radio
                            value="{{ AssetMissingAdminResolutionService::ResolutionNewOwner }}"
                            label="Wieder aufgetaucht — neuer Besitzer"
                            description="Aktive Übergaben werden als ersetzt markiert und bleiben in der Historie; neuer Besitzer, neue Übergabe wird erzeugt."
                        />
                        <flux:radio
                            value="{{ AssetMissingAdminResolutionService::ResolutionSetLocation }}"
                            label="Wieder aufgetaucht — Besitzer entfernen, Standort setzen"
                            description="Besitzer wird entfernt; aktive Übergaben werden als ersetzt markiert und bleiben in der Historie. Standort ist Pflicht. Optional als Lager-/Pool-Gerät markieren."
                        />
                    </flux:radio.group>
                    @error('resolution')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    @if($resolution === AssetMissingAdminResolutionService::ResolutionNewOwner)
                        <flux:select
                            wire:model="newOwnerUserId"
                            variant="listbox"
                            searchable
                            label="Neuer Besitzer"
                            placeholder="Benutzer wählen…"
                            class="min-w-64"
                        >
                            @foreach($users as $user)
                                <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('newOwnerUserId')
                            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @endif

                    @if($resolution === AssetMissingAdminResolutionService::ResolutionSetLocation)
                        <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                        @error('location')
                            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                        @enderror
                        <x-intranet-app-assets::unowned-device-type-select
                            class="mt-4"
                            wire-model="deviceType"
                            error-name="deviceType"
                        />
                    @endif

                    <flux:textarea
                        wire:model="note"
                        label="Dokumentation / Begründung"
                        placeholder="z. B. wo und wann das Gerät wieder aufgetaucht ist …"
                        rows="5"
                    />
                    @error('note')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror
                </section>

                <flux:text class="text-sm text-slate-600 dark:text-white/70">
                    Beim Speichern werden Sie zur Bestätigung mit Ihrem LDAP-/Windows-Passwort weitergeleitet.
                </flux:text>

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        Speichern und abschließen
                    </flux:button>
                    <flux:button :href="route('apps.assets.admin.missing')" variant="ghost" wire:navigate>
                        Zurück zur Liste
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
