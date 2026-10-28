<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Jobs\InvoiceMail;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppAssets\Services\InvoiceMail\InvoiceMailMailboxActionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ApplyInvoiceMailMailboxActionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public int $invoiceMailId) {}

    public function backoff(): int
    {
        return 120;
    }

    public function uniqueId(): string
    {
        return (string) $this->invoiceMailId;
    }

    public function handle(InvoiceMailMailboxActionService $service): void
    {
        $mail = InvoiceMail::query()->find($this->invoiceMailId);
        if ($mail !== null) {
            $service->apply($mail);
        }
    }

    public function failed(?Throwable $exception): void
    {
        InvoiceMail::query()->find($this->invoiceMailId)?->logEvent(
            'mailbox_action',
            'Postfach-Aktion fehlgeschlagen: '.($exception?->getMessage() ?? 'Unbekannter Fehler'),
            InvoiceMailEventLevel::Error,
        );
    }
}
