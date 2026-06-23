<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class YubikeysTableExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, array{model: string, serial_number: string, type: string, vendor: string, owner: string, username: string, status: string}>  $rows
     */
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Modell / Name',
            'Seriennummer',
            'Typ',
            'Hersteller',
            'Besitzer',
            'Benutzername',
            'Status',
        ];
    }

    /**
     * @param  array{model: string, serial_number: string, type: string, vendor: string, owner: string, username: string, status: string}  $row
     * @return array<int, string>
     */
    public function map($row): array
    {
        return [
            $row['model'],
            $row['serial_number'],
            $row['type'],
            $row['vendor'],
            $row['owner'],
            $row['username'],
            $row['status'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 24,
            'B' => 18,
            'C' => 14,
            'D' => 14,
            'E' => 22,
            'F' => 18,
            'G' => 14,
        ];
    }
}
