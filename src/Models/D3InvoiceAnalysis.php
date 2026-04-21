<?php

namespace Hwkdo\IntranetAppAssets\Models;

use Hwkdo\IntranetAppAssets\Enums\D3InvoiceAnalysisStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class D3InvoiceAnalysis extends Model
{
    protected $table = 'intranet_app_assets_d3_invoice_analyses';

    protected $guarded = [];

    /**
     * Assets, deren Rechnungsnummer dieser D3-Dokument-ID entspricht.
     *
     * @return HasMany<Asset, D3InvoiceAnalysis>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'invoice_number', 'd3_document_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => D3InvoiceAnalysisStatus::class,
            'result_json' => 'array',
            'analyzed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public static function normalizeDocumentId(string $documentId): string
    {
        return trim($documentId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function markCompleted(array $payload, string $visionModel): void
    {
        $this->update([
            'status' => D3InvoiceAnalysisStatus::Completed,
            'result_json' => $payload,
            'vision_model' => $visionModel,
            'analyzed_at' => now(),
            'error_message' => null,
            'failed_at' => null,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => D3InvoiceAnalysisStatus::Failed,
            'error_message' => $message,
            'failed_at' => now(),
        ]);
    }

    public function isDispatchRedundant(): bool
    {
        if ($this->status !== D3InvoiceAnalysisStatus::Completed) {
            return false;
        }

        if (! (bool) config('intranet-app-assets.d3_invoice_analysis_reanalyze_on_model_change', false)) {
            return true;
        }

        $expected = self::resolvedConfigVisionModel();
        $stored = trim((string) ($this->vision_model ?? ''));

        if ($expected === '') {
            return true;
        }

        return $stored === $expected;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findCompletedPayloadForDocument(string $documentId): ?array
    {
        $id = self::normalizeDocumentId($documentId);
        $row = self::query()->where('d3_document_id', $id)->first();
        if ($row === null || $row->status !== D3InvoiceAnalysisStatus::Completed) {
            return null;
        }

        if ((bool) config('intranet-app-assets.d3_invoice_analysis_reanalyze_on_model_change', false)) {
            $expected = self::resolvedConfigVisionModel();
            $stored = trim((string) ($row->vision_model ?? ''));
            if ($expected !== '' && $stored !== $expected) {
                return null;
            }
        }

        $payload = $row->result_json;

        return is_array($payload) ? $payload : null;
    }

    public static function requestAnalysis(string $documentId, bool $force = false): self
    {
        $id = self::normalizeDocumentId($documentId);

        return DB::transaction(function () use ($id, $force): self {
            /** @var self|null $row */
            $row = self::query()->where('d3_document_id', $id)->lockForUpdate()->first();

            if ($row === null) {
                return self::create([
                    'd3_document_id' => $id,
                    'status' => D3InvoiceAnalysisStatus::Pending,
                ]);
            }

            if ($row->status === D3InvoiceAnalysisStatus::Completed && ! $force && $row->isDispatchRedundant()) {
                return $row;
            }

            $row->update([
                'status' => D3InvoiceAnalysisStatus::Pending,
                'result_json' => null,
                'vision_model' => null,
                'analyzed_at' => null,
                'error_message' => null,
                'failed_at' => null,
            ]);
            $row->refresh();

            return $row;
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', D3InvoiceAnalysisStatus::Completed);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', D3InvoiceAnalysisStatus::Failed);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', D3InvoiceAnalysisStatus::Pending);
    }

    private static function resolvedConfigVisionModel(): string
    {
        return trim((string) config(
            'intranet-app-assets.d3_invoice_vision_model',
            config('openwebui-api-laravel.default_model', '')
        ));
    }
}
