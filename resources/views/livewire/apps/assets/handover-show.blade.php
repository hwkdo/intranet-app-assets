<?php

use Hwkdo\IntranetAppAssets\Models\Handover;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Übergabe')] class extends Component
{
    public Handover $handover;

    public function mount(Handover $handover): void
    {
        $this->handover = $handover->load('asset.type', 'asset.vendor', 'recipient', 'issuer');
    }

    public function confirmationMethodLabel(): ?string
    {
        return match ($this->handover->confirmation_method) {
            'signopad' => 'Signopad',
            'touchscreen' => 'Touchscreen-Unterschrift',
            'formwerk' => 'Formwerk-Formular',
            'password' => 'Passwort-Bestätigung',
            default => null,
        };
    }
}; ?>
<div>
    <x-intranet-app-assets::assets-layout
        heading="Übergabe"
        subheading="{{ $handover->asset?->display_name ?? 'Asset' }}"
    >
        <div class="space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">Status</flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    @if($handover->isConfirmed())
                        <flux:badge color="green" size="lg" icon="check-circle">Bestätigt</flux:badge>
                        @if($this->confirmationMethodLabel())
                            <flux:badge color="zinc" size="lg">{{ $this->confirmationMethodLabel() }}</flux:badge>
                        @endif
                    @else
                        <flux:badge color="amber" size="lg" icon="clock">Offen</flux:badge>
                    @endif
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">Details</flux:heading>
                <dl class="grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Asset</dt>
                    <dd>
                        @if($handover->asset)
                            <a href="{{ route('apps.assets.show', $handover->asset) }}" class="text-[var(--color-accent)] hover:underline">
                                {{ $handover->asset->display_name }}
                            </a>
                            <span class="text-zinc-500"> · {{ $handover->asset->serial_number }}</span>
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Empfänger</dt>
                    <dd>{{ $handover->recipient?->name ?? '—' }}</dd>

                    <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Ausgestellt von</dt>
                    <dd>{{ $handover->issuer?->name ?? '—' }}</dd>

                    <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Erstellt am</dt>
                    <dd>{{ $handover->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>

                    @if($handover->isConfirmed())
                        <dt class="font-semibold text-zinc-500 dark:text-zinc-400">Bestätigt am</dt>
                        <dd>{{ $handover->confirmed_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    @endif
                </dl>
            </flux:card>

            @if($handover->signature)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Unterschrift</flux:heading>
                    @php
                        $src = $handover->signature;
                        if (!str_starts_with($src, 'data:')) {
                            $src = 'data:image/png;base64,' . $src;
                        }
                    @endphp
                    <img
                        src="{{ $src }}"
                        alt="Unterschrift"
                        class="max-h-48 rounded border border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-800"
                    />
                </flux:card>
            @endif

            <div class="flex flex-wrap gap-2">
                @if(!$handover->isConfirmed() && $handover->recipient_user_id === auth()->id())
                    <flux:button href="{{ route('apps.assets.handover.confirm', $handover) }}" variant="primary" icon="check-circle">
                        Übergabe bestätigen
                    </flux:button>
                @endif
                @if($handover->asset)
                    <flux:button href="{{ route('apps.assets.show', $handover->asset) }}" variant="ghost" icon="eye">
                        Zum Asset
                    </flux:button>
                @endif
                <flux:button href="{{ route('apps.assets.meine-assets') }}" variant="ghost" icon="arrow-left">
                    Meine Assets
                </flux:button>
            </div>
        </div>
    </x-intranet-app-assets::assets-layout>
</div>
