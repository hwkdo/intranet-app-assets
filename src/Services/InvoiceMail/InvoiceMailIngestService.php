<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use Hwkdo\IntranetAppAssets\Contracts\InvoiceMailboxClientInterface;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailSource;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailStatus;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceMailIngestService
{
    public function __construct(private readonly InvoiceMailboxClientInterface $mailbox) {}

    /**
     * Holt die Mail aus Graph und legt sie samt Anhängen lokal ab.
     *
     * @return InvoiceMail|null null, wenn die Mail schon erfasst ist
     */
    public function ingest(
        string $upn,
        string $messageId,
        InvoiceMailSource $source,
        ?int $importId = null,
    ): ?InvoiceMail {
        if (InvoiceMail::query()->where('graph_message_id', $messageId)->exists()) {
            return null;
        }

        $startedAt = microtime(true);
        $message = $this->mailbox->getMessage($upn, $messageId);

        if (is_string($message['internet_message_id'])
            && InvoiceMail::query()->where('internet_message_id', $message['internet_message_id'])->exists()) {
            return null;
        }

        try {
            $mail = InvoiceMail::query()->create([
                'graph_message_id' => $message['id'],
                'mailbox_upn' => $upn,
                'internet_message_id' => $message['internet_message_id'],
                'source' => $source,
                'import_id' => $importId,
                'from_name' => $message['from_name'],
                'from_address' => $message['from_address'],
                'to_recipients' => $message['to'],
                'subject' => $message['subject'] !== null ? mb_substr($message['subject'], 0, 1000) : null,
                'received_at' => $message['received_at'] !== null ? Carbon::parse($message['received_at']) : now(),
                'body' => $message['body'],
                'body_type' => $message['body_type'],
                'status' => InvoiceMailStatus::Received,
                'report_mode' => IntranetAppAssetsSettings::resolvedAppSettings()->invoiceMailReportMode,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $stored = $message['has_attachments'] ? $this->storeAttachments($mail, $upn, $messageId) : 0;

        $mail->logEvent(
            'ingest',
            'Mail übernommen, '.$stored.' Anhang/Anhänge gespeichert.',
            context: ['source' => $source->value],
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );

        return $mail;
    }

    private function storeAttachments(InvoiceMail $mail, string $upn, string $messageId): int
    {
        $count = 0;
        $disk = Storage::disk('local');

        foreach ($this->mailbox->listAttachments($upn, $messageId) as $index => $attachment) {
            if ($attachment['content'] === null) {
                continue;
            }

            $filename = $this->safeFilename($attachment['name'], $index);
            $path = $mail->storageDirectory().'/'.$filename;
            $disk->put($path, $attachment['content']);

            $mail->attachments()->create([
                'graph_attachment_id' => $attachment['id'],
                'filename' => $attachment['name'],
                'mime_type' => $attachment['content_type'] ?? $this->guessMime($attachment['name']),
                'size' => $attachment['size'] > 0 ? $attachment['size'] : strlen($attachment['content']),
                'is_inline' => $attachment['is_inline'],
                'path' => $path,
                'sha256' => hash('sha256', $attachment['content']),
                'status' => InvoiceMailAttachmentStatus::Pending,
                'd3_status' => InvoiceMailD3Status::None,
            ]);
            $count++;
        }

        return $count;
    }

    private function safeFilename(string $name, int $index): string
    {
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'anhang';

        return sprintf('%02d-%s%s', $index + 1, Str::limit($base, 80, ''), $extension !== '' ? '.'.$extension : '');
    }

    private function guessMime(string $name): ?string
    {
        return match (Str::lower(pathinfo($name, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
    }
}
