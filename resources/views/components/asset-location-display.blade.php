@props([
    'asset',
    'tag' => 'dd',
    'showHint' => true,
])

@php
    use Hwkdo\IntranetAppAssets\Services\AssetLocationDisplayResolver;

    $display = AssetLocationDisplayResolver::resolve($asset);
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => 'space-y-0.5']) }}>
    @if(filled($display['value']))
        <span>{{ $display['value'] }}</span>
    @else
        <span class="opacity-70">—</span>
    @endif
    @if($showHint && filled($display['hint']))
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $display['hint'] }}</flux:text>
    @endif
</{{ $tag }}>
