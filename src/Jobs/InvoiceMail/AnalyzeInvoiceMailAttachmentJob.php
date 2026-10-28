<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Jobs\InvoiceMail;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailAttachmentAnalyzer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AnalyzeInvoiceMailAttachmentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 1800;

    public int $timeout = 900;

    public function __construct(public int $attachmentId) {}

    public function backoff(): int
    {
        return 180;
    }

    public function uniqueId(): string
    {
        return (string) $this->attachmentId;
    }

    public function handle(InvoiceMailAttachmentAnalyzer $analyzer): void
    {
        $attachment = InvoiceMailAttachment::query()->with('invoiceMail')->find($this->attachmentId);
        if ($attachment === null || $attachment->invoiceMail === null) {
            return;
        }

        $analyzer->analyze($attachment);
        $attachment->refresh();

        if ($analyzer->shouldAutoUpload($attachment)) {
            $attachment->update(['d3_status' => InvoiceMailD3Status::Queued]);
            UploadInvoiceMailToD3Job::dispatch($attachment->id);
        }

        $this->finishMail($attachment);
    }

    public function failed(?Throwable $exception): void
    {
        $attachment = InvoiceMailAttachment::query()->with('invoiceMail')->find($this->attachmentId);
        if ($attachment === null) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Unbekannter Fehler';
        $attachment->update(['status' => InvoiceMailAttachmentStatus::Failed, 'last_error' => mb_substr($message, 0, 2000)]);
        $attachment->invoiceMail?->logEvent('analyze', 'Analyse fehlgeschlagen: '.$message, InvoiceMailEventLevel::Error, attachmentId: $attachment->id);

        if ($attachment->invoiceMail !== null) {
            $attachment->invoiceMail->refreshStatusFromAttachments();
        }
    }

    private function finishMail(InvoiceMailAttachment $attachment): void
    {
        $mail = $attachment->invoiceMail;
        $mail->refreshStatusFromAttachments();

        if ($mail->refresh()->status === InvoiceMailStatus::Analyzed) {
            ApplyInvoiceMailMailboxActionJob::dispatch($mail->id);
        }
    }
}
