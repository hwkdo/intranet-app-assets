<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Commands;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\AnalyzeInvoiceMailAttachmentJob;
use Hwkdo\IntranetAppAssets\Jobs\InvoiceMail\ClassifyInvoiceMailJob;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppBase\Services\BackgroundActivityConfigStore;
use Illuminate\Console\Command;

class InvoiceMailsStaleCheckCommand extends Command
{
    public const ActivityKey = 'assets.invoice-mails-stale-check';

    protected $signature = 'intranet-app-assets:invoice-mails-stale-check';

    protected $description = 'Findet Rechnungs-Mails, die in der Verarbeitung hängen, und stößt sie erneut an.';

    public function handle(BackgroundActivityConfigStore $config): int
    {
        $minutes = max(5, (int) $config->parameter(self::ActivityKey, 'after_minutes', 30));
        $maxRetries = max(0, (int) $config->parameter(self::ActivityKey, 'max_retries', 2));

        $stale = InvoiceMail::query()
            ->with('attachments')
            ->whereIn('status', [InvoiceMailStatus::Received, InvoiceMailStatus::Classifying, InvoiceMailStatus::Analyzing])
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->limit(200)
            ->get();

        $restarted = 0;
        $givenUp = 0;

        foreach ($stale as $mail) {
            $retries = $mail->events()->where('step', 'stale_check')->count();

            if ($retries >= $maxRetries) {
                $mail->markStatus(InvoiceMailStatus::Failed, 'Verarbeitung hängt seit über '.$minutes.' Minuten.');
                $mail->logEvent('stale_check', 'Aufgegeben nach '.$retries.' Neustarts.', InvoiceMailEventLevel::Error);
                $givenUp++;

                continue;
            }

            $mail->logEvent('stale_check', 'Hängt seit über '.$minutes.' Minuten im Status „'.$mail->status->label().'“, neu angestoßen.', InvoiceMailEventLevel::Warning);
            $mail->touch();

            if ($mail->status === InvoiceMailStatus::Analyzing) {
                foreach ($mail->attachments as $attachment) {
                    if ($attachment->isCandidate() && $attachment->status->isOpen()) {
                        $attachment->update(['status' => InvoiceMailAttachmentStatus::Pending]);
                        AnalyzeInvoiceMailAttachmentJob::dispatch($attachment->id);
                    }
                }
            } else {
                ClassifyInvoiceMailJob::dispatch($mail->id);
            }

            $restarted++;
        }

        $this->info("Hängend: {$stale->count()}, neu angestoßen: {$restarted}, aufgegeben: {$givenUp}.");

        return self::SUCCESS;
    }
}
