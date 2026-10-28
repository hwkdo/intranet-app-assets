<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Jobs\InvoiceMail;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailD3UploadService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Automatischer Upload. Nur ein Versuch, damit D3 keine Dubletten bekommt.
 */
class UploadInvoiceMailToD3Job implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public int $timeout = 600;

    public function __construct(public int $attachmentId) {}

    public function uniqueId(): string
    {
        return (string) $this->attachmentId;
    }

    public function handle(InvoiceMailD3UploadService $uploadService): void
    {
        $attachment = InvoiceMailAttachment::query()->with(['invoiceMail', 'suggestedUser', 'correctedUser'])->find($this->attachmentId);
        if ($attachment === null || $attachment->d3_status === InvoiceMailD3Status::Uploaded) {
            return;
        }

        $user = $attachment->effectiveUser();
        if ($user === null) {
            $attachment->update(['d3_status' => InvoiceMailD3Status::None]);
            $attachment->invoiceMail?->logEvent('d3_upload', 'Automatischer Upload abgebrochen: kein Empfänger.', InvoiceMailEventLevel::Warning, attachmentId: $attachment->id);

            return;
        }

        $uploadService->upload($attachment, $user);
    }

    public function failed(?Throwable $exception): void
    {
        $attachment = InvoiceMailAttachment::query()->find($this->attachmentId);
        if ($attachment !== null && $attachment->d3_status !== InvoiceMailD3Status::Failed && $attachment->d3_status !== InvoiceMailD3Status::Uploaded) {
            $attachment->update([
                'd3_status' => InvoiceMailD3Status::Failed,
                'd3_error' => mb_substr($exception?->getMessage() ?? 'Unbekannter Fehler', 0, 2000),
            ]);
        }
    }
}
