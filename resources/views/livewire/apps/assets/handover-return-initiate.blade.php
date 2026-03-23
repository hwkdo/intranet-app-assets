<div>
    <x-intranet-app-assets::assets-layout
        heading="Rückgabe einleiten"
        subheading="{{ $handover->asset?->display_name ?? 'Asset' }}"
    >
        <div class="mx-auto max-w-lg space-y-6">
            <flux:callout variant="warning" icon="arrow-uturn-left">
                <flux:callout.heading>Gerät zurückgeben</flux:callout.heading>
                <flux:callout.text>
                    Sie melden die Rückgabe des Assets. Ein Admin bestätigt anschließend den <strong>physischen Empfang</strong> (mit LDAP-Passwort)
                    und legt fest, ob ein neuer Besitzer zugewiesen wird oder das Gerät ohne Besitzer mit Standort erfasst wird.
                </flux:callout.text>
            </flux:callout>
            @if($initiatedByAdmin)
                <flux:callout variant="warning" icon="shield-check">
                    <flux:callout.heading>Admin-Rückgabeeinleitung</flux:callout.heading>
                    <flux:callout.text>
                        Sie leiten diese Rückgabe als Admin ein. Der Vorgang wird entsprechend im Verlauf protokolliert.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:card class="space-y-4 text-sm">
                <flux:heading size="sm">Übergabe</flux:heading>
                <flux:text>Asset: <span class="font-medium">{{ $handover->asset?->display_name ?? '—' }}</span></flux:text>
                <flux:text>Seriennummer: <span class="font-mono">{{ $handover->asset?->serial_number ?? '—' }}</span></flux:text>
            </flux:card>

            <form wire:submit="submit" class="space-y-4">
                <flux:textarea
                    wire:model="note"
                    label="Hinweis (optional)"
                    placeholder="z. B. wann und wo Sie das Gerät abgeben …"
                    rows="5"
                />
                <flux:error name="note" />

                <div class="flex flex-wrap gap-2">
                    <flux:button type="submit" variant="primary" icon="arrow-uturn-left">
                        Rückgabe einleiten
                    </flux:button>
                    <flux:button :href="route('apps.assets.handover.show', $handover)" variant="ghost" wire:navigate>
                        Abbrechen
                    </flux:button>
                </div>
            </form>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
