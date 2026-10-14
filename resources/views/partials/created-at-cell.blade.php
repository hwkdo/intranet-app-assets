@php
    $createdAt = $asset->created_at;
    $createdRelative = $createdAt ? $createdAt->diffForHumans(['parts' => 1]) : '—';
    $createdAbsolute = $createdAt ? $createdAt->timezone((string) config('app.timezone'))->format('d.m.Y H:i') : '';
@endphp
<flux:table.cell class="w-24 max-w-[6.5rem] whitespace-nowrap text-end text-sm text-zinc-600 dark:text-zinc-200">
    @if($createdAt)
        <flux:tooltip :content="$createdAbsolute" position="top">
            <span class="block truncate">{{ $createdRelative }}</span>
        </flux:tooltip>
    @else
        <span>—</span>
    @endif
</flux:table.cell>
