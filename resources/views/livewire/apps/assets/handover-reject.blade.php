<div>
    <x-intranet-app-assets::assets-layout
        heading="Übergabe ablehnen"
        subheading="{{ $handover->asset?->display_name ?? 'Asset' }}"
    >
        <div class="mx-auto max-w-lg space-y-6">
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Übergabe ablehnen</flux:callout.heading>
                <flux:callout.text>
                    Nutzen Sie dies, wenn Sie das Asset nicht erhalten haben oder nicht mehr besitzen. Ihre Begründung wird protokolliert.
                </flux:callout.text>
            </flux:callout>

            <section class="glass-card overflow-hidden space-y-4">
                <div class="text-sm">
                    <flux:heading size="sm">Asset</flux:heading>
                    <flux:text class="text-slate-600 dark:text-white/70">
                        {{ $handover->asset?->display_name }} · {{ $handover->asset?->serial_number }}
                    </flux:text>
                </div>

                <form wire:submit="reject" class="hwk-fields glass-card space-y-4 p-5 sm:p-6" style="overflow: visible">
                    <flux:textarea
                        wire:model="rejectionReason"
                        label="Begründung"
                        placeholder="Bitte kurz beschreiben, warum Sie die Übergabe nicht bestätigen …"
                        rows="6"
                        required
                    />
                    @error('rejectionReason')
                        <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                    @enderror

                    <div class="flex flex-wrap gap-2">
                        <flux:button type="submit" variant="danger" icon="x-circle">
                            Ablehnung absenden
                        </flux:button>
                        <flux:button :href="route('apps.assets.meine-assets')" variant="ghost" wire:navigate>
                            Abbrechen
                        </flux:button>
                    </div>
                </form>
            </section>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
