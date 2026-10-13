@props([
    'serial' => '',
    'other' => '',
    'side' => 'asset',
])

@php
    $diff = \Hwkdo\IntranetAppAssets\Support\SerialNumberDiff::split((string) $serial, (string) $other);
    $parts = $side === 'sccm' ? $diff['sccm'] : $diff['asset'];
@endphp

<span {{ $attributes->class('font-mono text-sm whitespace-nowrap') }}>
    @foreach($parts as $part)
        @if($part['changed'])
            <mark class="rounded-sm px-0.5" style="background-color: rgb(239 68 68 / 0.45); color: inherit;">{{ $part['text'] }}</mark>
        @else
            <span>{{ $part['text'] }}</span>
        @endif
    @endforeach
</span>
