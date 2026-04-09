<?php

use Hwkdo\IntranetAppAssets\Services\D3InvoiceVisionAnalysisService;

it('extrahiert json robust aus markdown fences', function () {
    $service = new D3InvoiceVisionAnalysisService;
    $json = $service->extractJsonFromContent(<<<'TEXT'
```json
{
  "supplier": {"name": "ACME"}
}
```
TEXT);

    expect($json)->toBe("{\n  \"supplier\": {\"name\": \"ACME\"}\n}");
});

it('normalisiert analysedaten mit defaults und deduplizierten seriennummern', function () {
    $service = new D3InvoiceVisionAnalysisService;
    $normalized = $service->normalizeAnalysisData([
        'supplier' => ['name' => '  ACME GmbH  '],
        'invoice' => ['invoice_number' => ' RE-123 '],
        'totals' => ['net' => '1.234,50', 'tax' => '234,56', 'gross' => 1469.06],
        'line_items' => [
            [
                'description' => 'Notebook',
                'quantity' => '2',
                'unit_price' => '999,99',
                'total_price' => '1.999,98',
                'serial_numbers' => ['SN-1', 'SN-1', 'SN-2'],
            ],
        ],
        'serial_numbers' => ['SN-2', 'SN-3'],
        'warnings' => ['  unscharfer scan  ', ''],
        'confidence' => '0.87',
    ]);

    expect($normalized['supplier']['name'])->toBe('ACME GmbH')
        ->and($normalized['invoice']['invoice_number'])->toBe('RE-123')
        ->and($normalized['totals']['net'])->toBe(1234.5)
        ->and($normalized['totals']['tax'])->toBe(234.56)
        ->and($normalized['totals']['gross'])->toBe(1469.06)
        ->and($normalized['line_items'][0]['serial_numbers'])->toBe(['SN-1', 'SN-2'])
        ->and($normalized['serial_numbers'])->toBe(['SN-2', 'SN-3', 'SN-1'])
        ->and($normalized['warnings'])->toBe(['unscharfer scan'])
        ->and($normalized['confidence'])->toBe(0.87);
});
