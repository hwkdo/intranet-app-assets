<div>
    <x-intranet-app-assets::assets-layout
        heading="Übergabe per Passwort bestätigen"
        subheading="{{ $handover->asset?->display_name ?? 'Asset' }}"
    >
        <div class="mx-auto max-w-lg space-y-6">
            <flux:callout variant="warning" icon="key">
                <flux:callout.heading>Empfänger am Gerät</flux:callout.heading>
                <flux:callout.text>
                    Bitte <strong>{{ $handover->recipient?->name ?? 'den Empfänger' }}</strong> das
                    Windows-/Active-Directory-Passwort eingeben lassen. Sie bleiben als Admin angemeldet —
                    es wird nur die Identität des Empfängers geprüft.
                </flux:callout.text>
            </flux:callout>

            <x-intranet-app-assets::handover-asset-summary :handover="$handover" />

            <form wire:submit="submit" class="space-y-4">
                <flux:input
                    wire:model="password"
                    type="password"
                    label="Windows-Passwort des Empfängers"
                    autocomplete="off"
                    required
                    placeholder="Active Directory Passwort"
                />
                <flux:error name="password" />

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        Übergabe bestätigen
                    </flux:button>
                    <flux:button
                        :href="route('apps.assets.admin.handover.start', $handover->asset)"
                        variant="ghost"
                        wire:navigate
                    >
                        Zurück
                    </flux:button>
                </div>
            </form>

            @if(app()->isLocal() && auth()->user()?->hasRole('Super Admin'))
                <flux:callout variant="warning" icon="beaker">
                    <flux:callout.heading>Entwicklung (nur local)</flux:callout.heading>
                    <flux:callout.text>
                        LDAP-Prüfung überspringen — nur bei <code class="rounded bg-zinc-200 px-1 dark:bg-zinc-700">APP_ENV=local</code>
                        und Rolle „Super Admin“.
                    </flux:callout.text>
                    <flux:button
                        type="button"
                        variant="ghost"
                        class="mt-3"
                        wire:click="confirmWithoutPasswordForLocalDev"
                        wire:confirm="Ohne Empfänger-Passwort bestätigen? Nur für lokale Tests."
                    >
                        Ohne Passwort bestätigen (Dev)
                    </flux:button>
                </flux:callout>
            @endif
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
