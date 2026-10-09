<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class D3InvoiceAnalysisAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
Du liest den Text einer Rechnung und füllst die Felder.

- Nur Werte, die im Text stehen. Sonst null bzw. leere Listen.
- Zahlen als JSON-Zahl mit Punkt als Dezimaltrennzeichen.
- totals.gross ist die Endsumme. net und tax nur, wenn sie ausgewiesen sind.
- line_items sind die Rechnungspositionen. serial_numbers an der Position und in der Gesamtliste, ohne Duplikate.
- warnings für unleserliche oder widersprüchliche Stellen.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'supplier' => $schema->object(fn (JsonSchema $schema): array => [
                'name' => $schema->string()->nullable()->required(),
                'address' => $schema->string()->nullable()->required(),
                'iban' => $schema->string()->nullable()->required(),
                'vat_id' => $schema->string()->nullable()->required(),
            ])->required(),
            'invoice' => $schema->object(fn (JsonSchema $schema): array => [
                'invoice_number' => $schema->string()->nullable()->required(),
                'invoice_date' => $schema->string()->nullable()->required(),
                'customer_number' => $schema->string()->nullable()->required(),
                'purchase_order_reference' => $schema->string()->nullable()->required(),
            ])->required(),
            'totals' => $schema->object(fn (JsonSchema $schema): array => [
                'currency' => $schema->string()->nullable()->required(),
                'net' => $schema->number()->nullable()->required(),
                'tax' => $schema->number()->nullable()->required(),
                'gross' => $schema->number()->nullable()->required(),
            ])->required(),
            'line_items' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'position' => $schema->string()->nullable()->required(),
                    'description' => $schema->string()->nullable()->required(),
                    'quantity' => $schema->number()->nullable()->required(),
                    'unit' => $schema->string()->nullable()->required(),
                    'unit_price' => $schema->number()->nullable()->required(),
                    'total_price' => $schema->number()->nullable()->required(),
                    'serial_numbers' => $schema->array()->items($schema->string())->required(),
                ])
            )->required(),
            'serial_numbers' => $schema->array()->items($schema->string())->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
            'confidence' => $schema->number()->nullable()->required(),
        ];
    }
}
