<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Jobs\InvoiceMail;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailSource;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailImport;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailIngestService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Einstieg für Change-Notifications (Webhook-Mapping) und den Datumsbereich-Import.
 */
class IngestInvoiceMailJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public int $timeout = 300;

    /**
     * @param  array<string, mixed>  $notification  Graph-Notification (`resource`, `resourceData.id`)
     */
    public function __construct(
        public array $notification = [],
        public ?string $messageId = null,
        public ?int $importId = null,
    ) {
        $this->messageId ??= self::messageIdFromNotification($notification);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Graph-Message-IDs sind ~152 Zeichen. Zusammen mit Cache-Prefix und Klassenname
     * überschreitet der Lock-Schlüssel sonst varchar(255) in `cache_locks`, der Insert
     * schlägt fehl und Laravel verwirft den Job, ohne ihn in die Queue zu legen.
     */
    public function uniqueId(): string
    {
        return hash('sha256', (string) $this->messageId);
    }

    public function handle(InvoiceMailIngestService $ingest): void
    {
        if ($this->messageId === null || $this->messageId === '') {
            Log::warning('assets.invoice_mail.ingest.no_message_id', ['notification' => $this->notification]);

            return;
        }

        $upn = IntranetAppAssetsSettings::resolvedAppSettings()->invoiceMailMailboxUpn;
        $mail = $ingest->ingest(
            $upn,
            $this->messageId,
            $this->importId !== null ? InvoiceMailSource::Import : InvoiceMailSource::Webhook,
            $this->importId,
        );

        if ($mail === null) {
            $this->countImport('skipped_duplicates');

            return;
        }

        ClassifyInvoiceMailJob::dispatch($mail->id);
    }

    public function failed(?Throwable $exception): void
    {
        $this->countImport('failed');

        Log::error('assets.invoice_mail.ingest.failed', [
            'message_id' => $this->messageId,
            'import_id' => $this->importId,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function countImport(string $column): void
    {
        if ($this->importId !== null) {
            InvoiceMailImport::query()->whereKey($this->importId)->increment($column);
        }
    }

    /**
     * @param  array<string, mixed>  $notification
     */
    public static function messageIdFromNotification(array $notification): ?string
    {
        $id = $notification['resourceData']['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return $id;
        }

        $resource = $notification['resource'] ?? null;
        if (is_string($resource) && preg_match('~/messages/([^/]+)$~i', $resource, $matches) === 1) {
            return urldecode($matches[1]);
        }

        return null;
    }
}
