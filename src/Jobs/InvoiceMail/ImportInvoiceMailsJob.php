<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Jobs\InvoiceMail;

use Hwkdo\IntranetAppAssets\Contracts\InvoiceMailboxClientInterface;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailImportStatus;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailImport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Einmaliger Import eines Datumsbereichs. Jede Mail läuft danach durch die normale Pipeline.
 */
class ImportInvoiceMailsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(public int $importId) {}

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(InvoiceMailboxClientInterface $mailbox): void
    {
        $import = InvoiceMailImport::query()->find($this->importId);
        if ($import === null || $import->status !== InvoiceMailImportStatus::Queued) {
            return;
        }

        $import->update(['status' => InvoiceMailImportStatus::Running, 'started_at' => now(), 'last_error' => null]);

        $found = 0;
        $queued = 0;
        $duplicates = 0;

        $folderIds = collect($import->folders ?? [])
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values();

        if ($folderIds->isEmpty()) {
            $folderIds = collect(['inbox']);
        }

        foreach ($folderIds as $folderId) {
            foreach ($mailbox->messageIdsReceivedBetween($import->mailbox_upn, $import->received_from, $import->received_to, $folderId) as $messageId) {
                $found++;

                if (InvoiceMail::query()->where('graph_message_id', $messageId)->exists()) {
                    $duplicates++;
                } else {
                    IngestInvoiceMailJob::dispatch(messageId: $messageId, importId: $import->id);
                    $queued++;
                }

                if ($found % 25 === 0) {
                    $import->update(['found' => $found, 'queued' => $queued, 'skipped_duplicates' => $duplicates]);
                }
            }
        }

        $import->update([
            'status' => InvoiceMailImportStatus::Completed,
            'found' => $found,
            'queued' => $queued,
            'skipped_duplicates' => $duplicates,
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        InvoiceMailImport::query()->whereKey($this->importId)->update([
            'status' => InvoiceMailImportStatus::Failed,
            'last_error' => mb_substr($exception?->getMessage() ?? 'Unbekannter Fehler', 0, 2000),
            'finished_at' => now(),
        ]);
    }
}
