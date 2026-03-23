<div>
    <x-intranet-app-assets::assets-layout
        heading="Klärung anfordern"
        subheading="{{ $asset->display_name }}"
    >
        <div class="mx-auto max-w-lg space-y-6">
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Bestand oder Zuordnung unklar</flux:callout.heading>
                <flux:callout.text>
                    Nutzen Sie dies, wenn das Asset laut System bei Ihnen liegt, der Sachverhalt aber nicht stimmt oder geklärt werden muss.
                    Die IT sieht Ihre Begründung und bearbeitet den Fall unter „Assets in Klärung“.
                </flux:callout.text>
            </flux:callout>

            <flux:card class="space-y-4">
                <div class="text-sm">
                    <flux:heading size="sm">Asset</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-300">
                        {{ $asset->display_name }} · {{ $asset->serial_number }}
                    </flux:text>
                </div>

                <form wire:submit="submit" class="space-y-4">
                    <flux:textarea
                        wire:model="reason"
                        label="Begründung"
                        placeholder="Bitte beschreiben Sie, was geklärt werden soll …"
                        rows="6"
                        required
                    />
                    <flux:error name="reason" />

                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="primary" icon="exclamation-triangle">
                            Klärung anfordern
                        </flux:button>
                        <flux:button :href="route('apps.assets.meine-assets')" variant="ghost" wire:navigate>
                            Abbrechen
                        </flux:button>
                    </div>
                </form>
            </flux:card>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
