@props([
    'color' => null,
    'size' => null,
    'icon' => null,
    'variant' => null,
    'inset' => null,
])

@php
    $tone = is_string($color) ? strtolower($color) : null;
    $usesHwkTone = in_array($tone, ['blue', 'sky', 'indigo', 'cyan'], true);
    $forward = array_filter([
        'color' => $usesHwkTone ? null : $tone,
        'size' => $size,
        'icon' => $icon,
        'variant' => $variant,
        'inset' => $inset,
    ], fn (mixed $value): bool => $value !== null && $value !== '');
@endphp

@if ($usesHwkTone)
    <span {{ $attributes->class('inline-flex items-center gap-1 rounded-full bg-[#d0e3f9] px-2 py-0.5 text-xs font-semibold text-[#073070] ring-1 ring-[#073070]/10 dark:bg-white/10 dark:text-white dark:ring-white/15') }}>
        @if (filled($icon))
            <flux:icon :icon="$icon" variant="micro" class="size-3.5" />
        @endif
        {{ $slot }}
    </span>
@else
    <flux:badge {{ $attributes->merge($forward) }}>{{ $slot }}</flux:badge>
@endif
