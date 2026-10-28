<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Jobs\InvoiceMail;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailClassifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ClassifyInvoiceMailJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 900;

    public int $timeout = 300;

    public function __construct(public int $invoiceMailId) {}

    public function backoff(): int
    {
        return 120;
    }

    public function uniqueId(): string
    {
        return (string) $this->invoiceMailId;
    }

    public function handle(InvoiceMailClassifier $classifier): void
    {
        $mail = InvoiceMail::query()->with('attachments')->find($this->invoiceMailId);
        if ($mail === null) {
            return;
        }

        $mail->markStatus(InvoiceMailStatus::Classifying);
        $startedAt = microtime(true);

        $result = $classifier->classify($mail);

        $mail->update([
            'relevance_probability' => $result['probability'],
            'relevance_kind' => $result['kind'],
            'relevance_payload' => $result['payload'],
            'is_relevant' => $result['is_relevant'],
            'threshold_used' => $result['threshold'],
            'irrelevant_reason' => $result['reason'],
        ]);

        $mail->logEvent(
            'classify',
            match (true) {
                $result['is_relevant'] && ($result['relevant_via'] ?? null) === 'document_kind' => 'Relevant, die Belegart ist sicher ('.$this->percent($result['probability']).', '.($result['kind'] ?? 'ohne Art').').',
                $result['is_relevant'] => 'Relevant ('.$this->percent($result['probability']).', '.($result['kind'] ?? 'ohne Art').').',
                default => 'Nicht relevant: '.$result['reason'].($result['probability'] !== null ? ' ('.$this->percent($result['probability']).')' : '').'.',
            },
            context: ['threshold' => $result['threshold'], 'payload' => $result['payload']],
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );

        if (! $result['is_relevant']) {
            $mail->update([
                'status' => InvoiceMailStatus::Irrelevant,
                'processed_at' => now(),
            ]);

            return;
        }

        $mail->markStatus(InvoiceMailStatus::Analyzing);

        $dispatched = 0;
        foreach ($mail->attachments as $attachment) {
            if (! $attachment->isCandidate()) {
                $attachment->update(['status' => InvoiceMailAttachmentStatus::Skipped]);

                continue;
            }

            if ($this->isDuplicateDocument($attachment)) {
                $attachment->update(['status' => InvoiceMailAttachmentStatus::Skipped, 'last_error' => 'Gleiches Dokument wurde bereits verarbeitet.']);
                $mail->logEvent('classify', 'Anhang übersprungen, das Dokument kam schon einmal: '.$attachment->filename, InvoiceMailEventLevel::Warning, attachmentId: $attachment->id);

                continue;
            }

            AnalyzeInvoiceMailAttachmentJob::dispatch($attachment->id);
            $dispatched++;
        }

        if ($dispatched === 0) {
            $mail->refreshStatusFromAttachments();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $mail = InvoiceMail::query()->find($this->invoiceMailId);
        if ($mail === null) {
            return;
        }

        $message = $exception?->getMessage() ?? 'Unbekannter Fehler';
        $mail->markStatus(InvoiceMailStatus::Failed, 'Klassifizierung: '.$message);
        $mail->logEvent('classify', 'Klassifizierung fehlgeschlagen: '.$message, InvoiceMailEventLevel::Error);
    }

    private function isDuplicateDocument(InvoiceMailAttachment $attachment): bool
    {
        if ($attachment->sha256 === null) {
            return false;
        }

        return InvoiceMailAttachment::query()
            ->where('sha256', $attachment->sha256)
            ->whereKeyNot($attachment->id)
            ->whereIn('status', [InvoiceMailAttachmentStatus::Analyzed, InvoiceMailAttachmentStatus::Analyzing])
            ->exists();
    }

    private function percent(?float $value): string
    {
        return $value === null ? '–' : number_format($value * 100, 1, ',', '').' %';
    }
}
