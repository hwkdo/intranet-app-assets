@php
    $serial = trim((string) ($serialNumber ?? ''));
    $visibleSerial = $serial === '' ? '' : mb_substr($serial, -10);
@endphp
<flux:table.cell class="whitespace-nowrap font-mono text-sm">
    @if($serial === '')
        <span>—</span>
    @elseif(mb_strlen($serial) > 10)
        <flux:tooltip :content="$serial" position="top">
            <span>{{ $visibleSerial }}</span>
        </flux:tooltip>
    @else
        <span>{{ $serial }}</span>
    @endif
</flux:table.cell>
