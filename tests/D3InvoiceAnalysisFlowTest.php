<?php

use Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus;
use Hwkdo\IntranetAppAssets\Jobs\AnalyzeD3InvoiceJob;
use Hwkdo\IntranetAppAssets\Models\D3InvoiceAnalysis;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceVisionAnalysisService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('intranet_app_assets_d3_invoice_analyses');
    Schema::dropIfExists('intranet_app_assets_assets');
    Schema::create('intranet_app_assets_assets', function (Blueprint $table) {
        $table->id();
        $table->string('invoice_number')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });
    Schema::create('intranet_app_assets_d3_invoice_analyses', function (Blueprint $table) {
        $table->id();
        $table->string('d3_document_id')->unique();
        $table->string('status');
        $table->json('result_json')->nullable();
        $table->string('vision_model')->nullable();
        $table->text('error_message')->nullable();
        $table->timestamp('analyzed_at')->nullable();
        $table->timestamp('failed_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('intranet_app_assets_d3_invoice_analyses');
    Schema::dropIfExists('intranet_app_assets_assets');
});

it('findCompletedPayload liefert null wenn modellwechsel und reanalyze aktiv', function () {
    config(['intranet-app-assets.d3_invoice_analysis_reanalyze_on_model_change' => true]);
    config(['intranet-app-assets.d3_invoice_vision_model' => 'new-model']);
    D3InvoiceAnalysis::create([
        'd3_document_id' => 'T999',
        'status' => D3InvoiceAnalysisStatus::Completed,
        'result_json' => ['document' => ['id' => 'T999']],
        'vision_model' => 'old-model',
        'analyzed_at' => now(),
    ]);
    expect(D3InvoiceAnalysis::findCompletedPayloadForDocument('T999'))->toBeNull();
});

it('AnalyzeD3InvoiceJob speichert erfolgreiche analyse als completed', function (): void {
    config(['intranet-app-assets.d3_invoice_vision_model' => 'vision-m']);
    config(['openwebui-api-laravel.api_key' => 'test-token']);
    config(['openwebui-api-laravel.default_model' => 'vision-m']);

    D3InvoiceAnalysis::create([
        'd3_document_id' => 'T100',
        'status' => D3InvoiceAnalysisStatus::Pending,
    ]);

    $payload = [
        'document' => ['id' => 'T100', 'display_name' => 'Doc', 'category' => null, 'rechnungsnummer' => null, 'belegtyp' => null, 'belegdatum' => null],
        'supplier' => ['name' => null, 'address' => null, 'iban' => null, 'vat_id' => null],
        'invoice' => ['invoice_number' => null, 'invoice_date' => null, 'customer_number' => null, 'purchase_order_reference' => null],
        'totals' => ['currency' => null, 'net' => null, 'tax' => null, 'gross' => null],
        'line_items' => [],
        'serial_numbers' => [],
        'warnings' => [],
        'confidence' => null,
        'source' => ['method' => 'vision_ocr', 'model' => 'vision-m', 'processed_pages' => 1, 'total_pages' => 1, 'truncated' => false, 'from_cache' => false],
    ];

    $mock = Mockery::mock(D3InvoiceVisionAnalysisService::class);
    $mock->shouldReceive('analyze')
        ->once()
        ->with('T100', 'vision-m', 'test-token', false)
        ->andReturn($payload);
    $mock->shouldReceive('payloadForStorage')->once()->andReturnUsing(fn (array $r): array => $r);
    app()->instance(D3InvoiceVisionAnalysisService::class, $mock);

    (new AnalyzeD3InvoiceJob('T100', false))->handle(app(D3InvoiceVisionAnalysisService::class));

    $row = D3InvoiceAnalysis::query()->where('d3_document_id', 'T100')->first();
    expect($row->status)->toBe(D3InvoiceAnalysisStatus::Completed)
        ->and($row->result_json)->toBeArray();
});

it('requestAnalysis mit force setzt completed analyse auf pending zurueck', function (): void {
    $row = D3InvoiceAnalysis::create([
        'd3_document_id' => 'T002664561',
        'status' => D3InvoiceAnalysisStatus::Completed,
        'result_json' => ['document' => ['id' => 'T002664561']],
        'vision_model' => 'vision-m',
        'analyzed_at' => now()->subDay(),
    ]);

    $updated = D3InvoiceAnalysis::requestAnalysis('T002664561', true);

    expect($updated->status)->toBe(D3InvoiceAnalysisStatus::Pending)
        ->and($updated->analyzed_at)->toBeNull()
        ->and($updated->result_json)->toBeNull();
});

it('AnalyzeD3InvoiceJob mit force analysiert completed eintraege erneut', function (): void {
    config(['intranet-app-assets.d3_invoice_vision_model' => 'vision-m']);
    config(['openwebui-api-laravel.api_key' => 'test-token']);
    config(['openwebui-api-laravel.default_model' => 'vision-m']);

    D3InvoiceAnalysis::create([
        'd3_document_id' => 'T002664561',
        'status' => D3InvoiceAnalysisStatus::Completed,
        'result_json' => ['document' => ['id' => 'T002664561']],
        'vision_model' => 'vision-m',
        'analyzed_at' => now()->subDay(),
    ]);

    $payload = [
        'document' => ['id' => 'T002664561', 'display_name' => 'Doc', 'category' => null, 'rechnungsnummer' => null, 'belegtyp' => null, 'belegdatum' => null],
        'supplier' => ['name' => null, 'address' => null, 'iban' => null, 'vat_id' => null],
        'invoice' => ['invoice_number' => null, 'invoice_date' => null, 'customer_number' => null, 'purchase_order_reference' => null],
        'totals' => ['currency' => null, 'net' => null, 'tax' => null, 'gross' => null],
        'line_items' => [],
        'serial_numbers' => [],
        'warnings' => [],
        'confidence' => null,
        'source' => ['method' => 'vision_ocr', 'model' => 'vision-m', 'processed_pages' => 1, 'total_pages' => 1, 'truncated' => false, 'from_cache' => false],
    ];

    $mock = Mockery::mock(D3InvoiceVisionAnalysisService::class);
    $mock->shouldReceive('analyze')
        ->once()
        ->with('T002664561', 'vision-m', 'test-token', false)
        ->andReturn($payload);
    $mock->shouldReceive('payloadForStorage')->once()->andReturnUsing(fn (array $r): array => $r);
    app()->instance(D3InvoiceVisionAnalysisService::class, $mock);

    (new AnalyzeD3InvoiceJob('T002664561', true))->handle(app(D3InvoiceVisionAnalysisService::class));

    $row = D3InvoiceAnalysis::query()->where('d3_document_id', 'T002664561')->first();
    expect($row->status)->toBe(D3InvoiceAnalysisStatus::Completed)
        ->and($row->analyzed_at)->not->toBeNull();
});

it('AnalyzeD3InvoiceJob ohne force ueberspringt redundante completed analyse', function (): void {
    config(['intranet-app-assets.d3_invoice_analysis_reanalyze_on_model_change' => false]);

    D3InvoiceAnalysis::create([
        'd3_document_id' => 'T300',
        'status' => D3InvoiceAnalysisStatus::Completed,
        'result_json' => ['document' => ['id' => 'T300']],
        'vision_model' => 'vision-m',
        'analyzed_at' => now()->subDay(),
    ]);

    $mock = Mockery::mock(D3InvoiceVisionAnalysisService::class);
    $mock->shouldReceive('analyze')->never();
    app()->instance(D3InvoiceVisionAnalysisService::class, $mock);

    (new AnalyzeD3InvoiceJob('T300', false))->handle(app(D3InvoiceVisionAnalysisService::class));
});

it('backfill dry-run listet distinct gueltige t nummern', function (): void {
    $now = now()->toDateTimeString();
    DB::table('intranet_app_assets_assets')->insert([
        ['invoice_number' => 'T200', 'created_at' => $now, 'updated_at' => $now],
        ['invoice_number' => 'T200', 'created_at' => $now, 'updated_at' => $now],
        ['invoice_number' => 'T201', 'created_at' => $now, 'updated_at' => $now],
    ]);

    Artisan::call('intranet-app-assets:d3-invoice-analyses-backfill', ['--dry-run' => true]);
    $output = Artisan::output();
    expect($output)->toContain('T200')->toContain('T201');
});
