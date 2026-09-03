<div>
    <x-intranet-app-assets::assets-layout
        heading="Zentrale"
        subheading="Übergaben per Signopad bestätigen"
    >
        <div class="space-y-6">
            @if (session('message'))
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.text>{{ session('message') }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:card>
                <flux:heading size="lg" class="dark:text-white">Warteschlange Signopad</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-300">
                    Offene Übergaben, die an der Zentrale per Signopad bestätigt werden sollen.
                </flux:text>

                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>Asset</flux:table.column>
                        <flux:table.column>Empfänger</flux:table.column>
                        <flux:table.column>Ausgestellt von</flux:table.column>
                        <flux:table.column>Seit</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->pendingHandovers as $handover)
                            <flux:table.row wire:key="zentrale-handover-{{ $handover->id }}">
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        {{ $handover->asset?->display_name ?? '—' }}
                                    </div>
                                    <div class="font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $handover->asset?->serial_number ?? '' }}
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $handover->recipient?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $handover->issuer?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $handover->created_at?->format('d.m.Y H:i') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            icon="pencil-square"
                                            wire:click="openConfirm({{ $handover->id }})"
                                        >
                                            Signopad
                                        </flux:button>
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            :href="route('apps.assets.handover.show', $handover)"
                                            wire:navigate
                                        >
                                            Detail
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-zinc-500 dark:text-zinc-400">
                                    Keine Übergaben in der Zentrale-Warteschlange.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>

        <flux:modal wire:model="showConfirmModal" class="md:w-xl">
            <flux:heading size="lg">Übergabe per Signopad bestätigen</flux:heading>

            @if ($showConfirmModal && $this->selectedHandover)
                <div class="mt-4 space-y-4">
                    <dl class="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                        <dt class="font-semibold text-zinc-500 dark:text-zinc-300">Asset</dt>
                        <dd class="text-zinc-900 dark:text-white">{{ $this->selectedHandover->asset?->display_name ?? '—' }}</dd>
                        <dt class="font-semibold text-zinc-500 dark:text-zinc-300">Seriennummer</dt>
                        <dd class="font-mono text-zinc-900 dark:text-white">{{ $this->selectedHandover->asset?->serial_number ?? '—' }}</dd>
                        <dt class="font-semibold text-zinc-500 dark:text-zinc-300">Empfänger (unterschreibt)</dt>
                        <dd class="text-zinc-900 dark:text-white">{{ $this->selectedHandover->recipient?->name ?? '—' }}</dd>
                        <dt class="font-semibold text-zinc-500 dark:text-zinc-300">Ausgestellt von</dt>
                        <dd class="text-zinc-900 dark:text-white">{{ $this->selectedHandover->issuer?->name ?? '—' }}</dd>
                    </dl>

                    <div wire:key="zentrale-signopad-{{ $selectedHandoverId }}">
                        <livewire:signopad.signpad
                            :fields="[]"
                            :textOben="'Übergabe: '.($this->selectedHandover->asset?->display_name ?? 'Asset')"
                            :textUnten="$this->selectedHandover->recipient?->name ?? ''"
                            :key="'zentrale-signpad-'.$selectedHandoverId"
                        />
                    </div>

                    @if ($signatureData)
                        <flux:callout variant="success" icon="check-circle">
                            <flux:callout.text>Unterschrift erfasst. Sie können die Übergabe jetzt bestätigen.</flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:text class="text-zinc-500 dark:text-zinc-400">
                            Bitte zuerst die Unterschrift des Empfängers am Signopad erfassen.
                        </flux:text>
                    @endif

                    <flux:error name="signatureData" />

                    <div class="flex flex-wrap gap-2">
                        <flux:button
                            variant="primary"
                            icon="check-circle"
                            wire:click="confirmHandover"
                            :disabled="! $signatureData"
                        >
                            Übergabe bestätigen
                        </flux:button>
                        <flux:button variant="ghost" wire:click="closeConfirm">
                            Abbrechen
                        </flux:button>
                    </div>
                </div>
            @endif
        </flux:modal>
    </x-intranet-app-assets::assets-layout>
</div>
