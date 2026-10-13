@php
    $columnClass = $columnClass ?? null;
    $align = $align ?? 'start';
@endphp
<flux:table.column
    sortable
    :sorted="$this->activeSortColumn() === $column"
    :direction="$this->activeSortDirection()"
    wire:click="sortBy('{{ $column }}')"
    :align="$align"
    @class([$columnClass])
>{{ $label }}</flux:table.column>
