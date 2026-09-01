@php
    use Hwkdo\IntranetAppAssets\Services\AssetClarificationAdminResolutionService;
@endphp
<div>
    <x-intranet-app-assets::assets-layout
        heading="Klärfall bearbeiten"
        subheading="{{ $asset->display_name }}"
    >
        <div class="mx-auto max-w-2xl space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4 dark:text-white">Asset</flux:heading>
                <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <dt class="font-semibold text-zinc-500 dark:text-white">Anzeigename</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->display_name }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Besitzer (laut System)</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ $asset->owner?->name ?? '—' }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Standort</dt>
                    <dd class="text-zinc-900 dark:text-white">{{ filled($asset->location) ? $asset->location : '—' }}</dd>
                    <dt class="font-semibold text-zinc-500 dark:text-white">Seriennummer</dt>
                    <dd class="font-mono text-zinc-900 dark:text-white">{{ $asset->serial_number }}</dd>
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
            </flux:card>

            @php $lastNote = $asset->notes()->latest('id')->first(); @endphp
            @if($lastNote)
                <flux:card class="space-y-2 text-sm">
                    <flux:heading size="sm" class="dark:text-white">Letzte Notiz</flux:heading>
                    <flux:text class="whitespace-pre-wrap text-zinc-600 dark:text-zinc-300">{{ $lastNote->note }}</flux:text>
                </flux:card>
            @endif

            @if(! $asset->trashed())
                <flux:card class="space-y-3 border-red-200 dark:border-red-900/40">
                    <flux:heading size="sm" class="dark:text-white">Asset löschen</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                        Zur regulären Löschseite mit Löschgrund, Verlauf und LDAP-Bestätigung.
                    </flux:text>
                    <flux:button href="{{ route('apps.assets.delete', $asset) }}" variant="danger" icon="trash">
                        Zur Löschseite
                    </flux:button>
                </flux:card>
            @endif

            <form wire:submit="submit" class="space-y-6">
                <flux:card class="space-y-4 dark:[&_[data-flux-label]]:text-white dark:[&_legend]:text-white">
                    <flux:checkbox
                        wire:model="acknowledgeReview"
                        label="Ich habe die Meldung des Besitzers und den Verlauf geprüft."
                    />
                    @error('acknowledgeReview')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <flux:radio.group wire:model.live="resolution" label="Weiteres Vorgehen">
                        <flux:radio
                            value="{{ AssetClarificationAdminResolutionService::ResolutionClearOnly }}"
                            label="Klärung beenden (ohne Änderung)"
                            description="Nur das Flag „In Klärung“ entfernen; Besitzer und Übergaben bleiben unverändert."
                        />
                        <flux:radio
                            value="{{ AssetClarificationAdminResolutionService::ResolutionNewOwner }}"
                            label="Neuen Besitzer zuweisen"
                            description="Alle Übergaben zu diesem Asset werden entfernt; neuer Besitzer, neue Übergabe wird erzeugt."
                        />
                        <flux:radio
                            value="{{ AssetClarificationAdminResolutionService::ResolutionSetLocation }}"
                            label="Besitzer entfernen und Standort setzen"
                            description="Alle Übergaben entfernen; Standort ist Pflicht."
                        />
                        <flux:radio
                            value="{{ AssetClarificationAdminResolutionService::ResolutionMarkMissing }}"
                            label="Als vermisst markieren"
                            description="Besitzer und Übergaben entfernen; Asset als vermisst kennzeichnen."
                        />
                    </flux:radio.group>
                    @error('resolution')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    @if($resolution === AssetClarificationAdminResolutionService::ResolutionNewOwner)
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

                    @if($resolution === AssetClarificationAdminResolutionService::ResolutionSetLocation)
                        <flux:input wire:model="location" label="Standort" placeholder="z. B. Lager IT, Raum …" />
                        @error('location')
                            <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @endif
                </flux:card>

                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                    Beim Speichern werden Sie zur Bestätigung mit Ihrem LDAP-/Windows-Passwort weitergeleitet.
                </flux:text>

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        Speichern und abschließen
                    </flux:button>
                    <flux:button :href="route('apps.assets.admin.clarifications')" variant="ghost" wire:navigate>
                        Zurück zur Liste
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
