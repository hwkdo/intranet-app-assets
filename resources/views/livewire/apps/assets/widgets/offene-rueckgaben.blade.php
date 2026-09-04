<?php

use Hwkdo\IntranetAppAssets\Enums\ReturnScheduleType;
use Hwkdo\IntranetAppAssets\Models\AssetReturn;
use Hwkdo\IntranetAppAssets\Support\AssetReturnSchedulePresenter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private function itemLimit(): int
    {
        $value = auth()->user()?->settings->app->assets->dashboard['widgetItemCounts']['offene-rueckgaben']
            ?? auth()->user()?->settings->dashboard->personalGrid?->widgetItemCounts['offene-rueckgaben']
            ?? 5;

        return min(max((int) $value, 1), 30);
    }

    public function mount(): void
    {
        $this->authorize('manage-app-assets');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<AssetReturn>
     */
    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return AssetReturn::query()
            ->whereNull('completed_at')
            ->whereHas('handover');
    }

    #[Computed]
    public function returns(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->baseQuery()
            ->with([
                'handover.asset.type',
                'handover.asset.vendor',
                'handover.recipient',
                'initiatedBy',
            ])
            ->orderByRaw('CASE WHEN schedule_type = ? AND scheduled_at IS NOT NULL AND scheduled_at <= ? THEN 0 WHEN schedule_type = ? AND scheduled_at IS NOT NULL THEN 1 ELSE 2 END', [
                ReturnScheduleType::Scheduled->value,
                now(),
                ReturnScheduleType::Scheduled->value,
            ])
            ->orderBy('scheduled_at')
            ->orderByDesc('created_at')
            ->limit($this->itemLimit())
            ->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->totalCount() > $this->itemLimit();
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->baseQuery()->count();
    }
};
?>

@placeholder
    <flux:card class="h-full">
        <div class="mb-3 space-y-2">
            <flux:skeleton class="h-4 w-44" />
            <flux:skeleton class="h-3 w-64" />
        </div>
        <div class="space-y-2">
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
            <flux:skeleton class="h-14 w-full rounded-md" />
        </div>
    </flux:card>
@endplaceholder

<x-intranet-app-base::dashboard.widget-card
    :title="'Offene Rückgaben ('.$this->totalCount().')'"
    :description="'Warten auf Empfangsbestätigung (max. '.$this->itemLimit().')'"
>
    @forelse($this->returns as $return)
        @php
            $handover = $return->handover;
            $asset = $handover?->asset;
            $badge = AssetReturnSchedulePresenter::scheduleBadge($return);
            $fromName = $return->initiatedBy?->name ?? $handover?->recipient?->name ?? '—';
        @endphp
        <a
            href="{{ route('apps.assets.admin.return.complete', $return) }}"
            wire:navigate
            class="group block cursor-pointer rounded-md border border-zinc-200 px-3 py-2 transition-colors duration-150 hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800/40 dark:hover:bg-white/15"
        >
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="font-medium group-hover:text-zinc-900 dark:group-hover:text-white">
                        {{ $asset?->display_name ?? 'Unbekanntes Asset' }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-white">
                        {{ $asset?->serial_number ?? '—' }}
                        · von {{ $fromName }}
                    </div>
                    @if($return->isScheduled())
                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-300">
                            Termin {{ AssetReturnSchedulePresenter::formattedScheduledAt($return->scheduled_at) ?? '—' }}
                        </div>
                    @endif
                </div>
                @if($badge)
                    <flux:badge size="sm" :color="$badge['color']" class="shrink-0">{{ $badge['label'] }}</flux:badge>
                @elseif(! $return->isScheduled())
                    <flux:badge size="sm" color="amber" class="shrink-0">Sofort</flux:badge>
                @endif
            </div>
        </a>
    @empty
        <flux:text class="text-zinc-500 dark:text-white">Keine offenen Rückgaben vorhanden.</flux:text>
    @endforelse

    @if($this->hasMore)
        <div class="pt-1">
            <flux:button variant="ghost" size="sm" :href="route('apps.assets.admin.returns.pending')" wire:navigate>
                Weitere anzeigen
            </flux:button>
        </div>
    @endif
</x-intranet-app-base::dashboard.widget-card>
