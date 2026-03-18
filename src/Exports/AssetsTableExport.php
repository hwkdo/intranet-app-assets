<?php

namespace Hwkdo\IntranetAppAssets\Exports;

use Hwkdo\IntranetAppAssets\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetsTableExport implements FromQuery, WithColumnWidths, WithHeadings, WithMapping
{
    /**
     * @param  array<int, array{heading: string, value: callable(Asset): string|int|null}>  $columns
     */
    public function __construct(
        private Builder $query,
        private array $columns
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_column($this->columns, 'heading');
    }

    /**
     * @param  Asset  $row
     * @return array<int, string|int|null>
     */
    public function map($row): array
    {
        return array_map(fn (array $col): string|int|null => $col['value']($row), $this->columns);
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        $widths = [];
        for ($i = 0; $i < count($this->columns); $i++) {
            $widths[chr(ord('A') + $i)] = 18;
        }

        return $widths;
    }
}
