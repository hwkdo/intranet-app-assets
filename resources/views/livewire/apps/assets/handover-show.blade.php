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
        $this->handover = $handover->load(
            'asset.type',
            'asset.vendor',
            'recipient',
            'issuer',
            'confirmedAssistedBy',
            'assetReturns',
        );
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

    public function pendingChannelLabel(): ?string
    {
        return match ($this->handover->pending_confirmation_channel) {
            'signopad_zentrale' => 'Warteschlange Zentrale (Signopad)',
            'password_now' => 'Passwort vor Ort',
            'self' => 'Empfänger bestätigt selbst',
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
            <section class="glass-card overflow-hidden p-5">
                <flux:heading size="lg" class="mb-4">Status</flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    @if($handover->isRejected())
                        <x-intranet-app-assets::hwk-badge color="red" size="lg" icon="x-circle">Abgelehnt</x-intranet-app-assets::hwk-badge>
                    @elseif($handover->isConfirmed())
                        <x-intranet-app-assets::hwk-badge color="green" size="lg" icon="check-circle">Bestätigt</x-intranet-app-assets::hwk-badge>
                        @if($this->confirmationMethodLabel())
                            <x-intranet-app-assets::hwk-badge color="zinc" size="lg">{{ $this->confirmationMethodLabel() }}</x-intranet-app-assets::hwk-badge>
                        @endif
                    @else
                        <x-intranet-app-assets::hwk-badge color="amber" size="lg" icon="clock">Offen</x-intranet-app-assets::hwk-badge>
                        @if($this->pendingChannelLabel())
                            <x-intranet-app-assets::hwk-badge color="zinc" size="lg">{{ $this->pendingChannelLabel() }}</x-intranet-app-assets::hwk-badge>
                        @endif
                    @endif
                </div>
            </section>

            @php
                $pendingReturn = $handover->assetReturns->first(fn ($r) => $r->completed_at === null);
                $hasCompletedReturn = $handover->assetReturns->contains(fn ($r) => $r->completed_at !== null);
                $isAdmin = auth()->user()?->can('manage-app-assets') ?? false;
                $canInitiateReturnAsHolder = $handover->isConfirmed()
                    && ! $handover->isRejected()
                    && $handover->recipient_user_id === auth()->id()
                    && $handover->asset
                    && (int) $handover->asset->user_id === (int) auth()->id()
                    && $pendingReturn === null
                    && ! $hasCompletedReturn;
                $canInitiateReturnAsAdmin = $isAdmin
                    && $handover->isConfirmed()
                    && ! $handover->isRejected()
                    && $pendingReturn === null
                    && ! $hasCompletedReturn;
                $canInitiateReturn = $canInitiateReturnAsHolder || $canInitiateReturnAsAdmin;
            @endphp

            @if($pendingReturn)
                @php $scheduleBadge = \Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter::scheduleBadge($pendingReturn); @endphp
                <flux:callout variant="warning" icon="clock">
                    <flux:callout.heading>
                        @if($pendingReturn->isLoan() && $pendingReturn->isScheduled())
                            Verleih – Rückgabe terminiert
                        @elseif($pendingReturn->isScheduled())
                            Geplante Rückgabe eingeleitet
                        @else
                            Rückgabe eingeleitet
                        @endif
                    </flux:callout.heading>
                    <flux:callout.text>
                        @if($pendingReturn->isScheduled())
                            Termin: <strong>{{ \Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter::formattedScheduledAt($pendingReturn->scheduled_at) }}</strong>.
                            @if($pendingReturn->isOverdue())
                                Die geplante Rückgabe ist überfällig. Bitte geben Sie das Gerät umgehend zurück.
                            @else
                                Sie erhalten Erinnerungen vor dem Termin. Die IT kann den Vorgang auch vorher bearbeiten.
                            @endif
                        @else
                            Die IT bearbeitet den Vorgang. Sie werden informiert, sobald der physische Empfang bestätigt und das Asset neu zugeordnet ist.
                        @endif
                    </flux:callout.text>
                    @if($scheduleBadge)
                        <div class="mt-2">
                            <x-intranet-app-assets::hwk-badge :color="$scheduleBadge['color']">{{ $scheduleBadge['label'] }}</x-intranet-app-assets::hwk-badge>
                        </div>
                    @endif
                </flux:callout>
            @endif

            <section class="glass-card overflow-hidden p-5">
                <flux:heading size="lg" class="mb-4 dark:text-white">Details</flux:heading>
                <dl class="hwk-rows">
                    <dt class="font-semibold text-slate-600 dark:text-white">Asset</dt>
                    <dd class="text-[#073070] dark:text-white">
                        @if($handover->asset)
                            <a
                                href="{{ route('apps.assets.show', [$handover->asset, 'from' => 'meine-assets']) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-[var(--color-accent)] hover:underline"
                            >
                                {{ $handover->asset->display_name }}
                            </a>
                            <span class="text-slate-600 dark:text-white/70"> · {{ $handover->asset->serial_number }}</span>
                        @else
                            —
                        @endif
                    </dd>

                    <dt class="font-semibold text-slate-600 dark:text-white">Empfänger</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $handover->recipient?->name ?? '—' }}</dd>

                    <dt class="font-semibold text-slate-600 dark:text-white">Ausgegeben / übergeben von</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $handover->issuer?->name ?? '—' }}</dd>

                    @if($handover->isLoan())
                        <dt class="font-semibold text-slate-600 dark:text-white">Verleih bis</dt>
                        <dd class="text-[#073070] dark:text-white">{{ $handover->loan_due_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    @endif

                    @if($handover->confirmedAssistedBy)
                        <dt class="font-semibold text-slate-600 dark:text-white">Bestätigt an der Zentrale durch</dt>
                        <dd class="text-[#073070] dark:text-white">{{ $handover->confirmedAssistedBy->name }}</dd>
                    @endif

                    <dt class="font-semibold text-slate-600 dark:text-white">Erstellt am</dt>
                    <dd class="text-[#073070] dark:text-white">{{ $handover->created_at?->format('d.m.Y H:i') ?? '—' }}</dd>

                    @if($handover->isRejected())
                        <dt class="font-semibold text-slate-600 dark:text-white">Abgelehnt am</dt>
                        <dd class="text-[#073070] dark:text-white">{{ $handover->rejected_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    @elseif($handover->isConfirmed())
                        <dt class="font-semibold text-slate-600 dark:text-white">Bestätigt am</dt>
                        <dd class="text-[#073070] dark:text-white">{{ $handover->confirmed_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    @endif
                </dl>
            </section>

            @if($handover->signature)
                <section class="glass-card overflow-hidden p-5">
                    <flux:heading size="lg" class="mb-4">Unterschrift</flux:heading>
                    <dl class="hwk-rows">
                        <dt class="font-semibold text-slate-600 dark:text-white">Unterschrieben von</dt>
                        <dd class="text-[#073070] dark:text-white">{{ $handover->recipient?->name ?? '—' }}</dd>
                        <dt class="font-semibold text-slate-600 dark:text-white">Ausgegeben / übergeben von</dt>
                        <dd class="text-[#073070] dark:text-white">{{ $handover->issuer?->name ?? '—' }}</dd>
                        @if($handover->confirmedAssistedBy)
                            <dt class="font-semibold text-slate-600 dark:text-white">Zentrale / Assistenz</dt>
                            <dd class="text-[#073070] dark:text-white">{{ $handover->confirmedAssistedBy->name }}</dd>
                        @endif
                    </dl>
                    @php
                        $src = $handover->signature;
                        if (!str_starts_with($src, 'data:')) {
                            $src = 'data:image/png;base64,' . $src;
                        }
                    @endphp
                    <img
                        src="{{ $src }}"
                        alt="Unterschrift von {{ $handover->recipient?->name ?? 'Empfänger' }}"
                        class="max-h-48 rounded border border-[#d0e3f9] bg-white dark:border-white/10 dark:bg-white/5"
                    />
                </section>
            @endif

            <div class="flex flex-wrap gap-2">
                @if(!$handover->isConfirmed() && !$handover->isRejected() && $handover->recipient_user_id === auth()->id())
                    <flux:button href="{{ route('apps.assets.handover.confirm', $handover) }}" variant="primary" icon="check-circle">
                        Übergabe bestätigen
                    </flux:button>
                    @unless($handover->isLoan())
                        <flux:button href="{{ route('apps.assets.handover.reject', $handover) }}" variant="danger" icon="x-circle">
                            Übergabe ablehnen
                        </flux:button>
                    @endunless
                @endif
                @if($canInitiateReturn)
                    <flux:button href="{{ route('apps.assets.handover.return.initiate', $handover) }}" variant="primary" icon="arrow-uturn-left">
                        Rückgabe einleiten
                    </flux:button>
                @endif
                @if($handover->asset)
                    <flux:button
                        href="{{ route('apps.assets.show', [$handover->asset, 'from' => 'meine-assets']) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="ghost"
                        icon="eye"
                    >
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
