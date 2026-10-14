@props([
    'serial' => '',
    'other' => '',
    'side' => 'asset',
])

@php
    $diff = \Hwkdo\IntranetAppAssets\Support\SerialNumberDiff::split((string) $serial, (string) $other);
    $parts = $side === 'sccm' ? $diff['sccm'] : $diff['asset'];
    $fullSerial = trim($side === 'sccm' ? (string) $other : (string) $serial);
    $skip = max(0, mb_strlen($fullSerial) - 10);
    $visibleParts = [];

    foreach ($parts as $part) {
        $length = mb_strlen($part['text']);

        if ($skip >= $length) {
            $skip -= $length;

            continue;
        }

        $text = $skip > 0 ? mb_substr($part['text'], $skip) : $part['text'];
        $skip = 0;

        if ($text !== '') {
            $visibleParts[] = ['text' => $text, 'changed' => $part['changed']];
        }
    }

    $truncated = mb_strlen($fullSerial) > 10;
@endphp

@if($truncated)
    <flux:tooltip :content="$fullSerial" position="top">
@endif
<span {{ $attributes->class('font-mono text-sm whitespace-nowrap') }}>
    @foreach($visibleParts as $part)
        @if($part['changed'])
            <mark class="rounded-sm px-0.5" style="background-color: rgb(239 68 68 / 0.45); color: inherit;">{{ $part['text'] }}</mark>
        @else
            <span>{{ $part['text'] }}</span>
        @endif
    @endforeach
</span>
@if($truncated)
    </flux:tooltip>
@endif
