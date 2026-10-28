<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use Carbon\CarbonInterface;
use Hwkdo\IntranetAppAssets\Contracts\InvoiceMailboxClientInterface;
use Hwkdo\MsGraphLaravel\Services\MailService;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Microsoft\Graph\Generated\Models\MailFolder;
use RuntimeException;

class MsGraphInvoiceMailboxClient implements InvoiceMailboxClientInterface
{
    private function mailService(): MailService
    {
        return new MailService;
    }

    public function getMessage(string $upn, string $messageId): array
    {
        $this->mailService();
        $message = MailService::getByUpnAndId($upn, $messageId);

        if ($message === null || ! is_string($message->getId())) {
            throw new RuntimeException('Die Nachricht wurde in Graph nicht gefunden.');
        }

        $from = $message->getFrom()?->getEmailAddress();
        $body = $message->getBody();
        $bodyType = $body?->getContentType()?->value() ?? 'text';

        return [
            'id' => $message->getId(),
            'internet_message_id' => $message->getInternetMessageId(),
            'from_name' => $from?->getName(),
            'from_address' => $from?->getAddress() !== null ? mb_strtolower($from->getAddress()) : null,
            'to' => collect($message->getToRecipients() ?? [])
                ->map(fn ($recipient): ?string => $recipient->getEmailAddress()?->getAddress())
                ->filter()
                ->values()
                ->all(),
            'subject' => $message->getSubject(),
            'received_at' => $message->getReceivedDateTime()?->format(DATE_ATOM),
            'body' => (string) ($body?->getContent() ?? ''),
            'body_type' => $bodyType === 'html' ? 'html' : 'text',
            'has_attachments' => (bool) $message->getHasAttachments(),
        ];
    }

    public function listAttachments(string $upn, string $messageId): array
    {
        $this->mailService();
        $response = MailService::listAttachmentsByUpnAndId($upn, $messageId);

        $attachments = [];
        foreach ($response?->getValue() ?? [] as $attachment) {
            $attachments[] = [
                'id' => $attachment->getId(),
                'name' => (string) ($attachment->getName() ?? 'anhang'),
                'content_type' => $attachment->getContentType() !== null ? mb_strtolower($attachment->getContentType()) : null,
                'size' => (int) ($attachment->getSize() ?? 0),
                'is_inline' => (bool) $attachment->getIsInline(),
                'content' => $attachment instanceof FileAttachment ? MailService::getAttachmentContent($attachment) : null,
            ];
        }

        return $attachments;
    }

    public function messageIdsReceivedBetween(string $upn, CarbonInterface $from, CarbonInterface $to, string $folderId = 'inbox'): iterable
    {
        $this->mailService();

        foreach (MailService::listReceivedBetween($upn, $from, $to, $folderId) as $message) {
            $id = $message->getId();
            if (is_string($id) && $id !== '') {
                yield $id;
            }
        }
    }

    public function folders(string $upn): array
    {
        $this->mailService();

        /** @var array<string, array{id: string, name: string, parent_id: ?string}> $byId */
        $byId = [];
        foreach (MailService::listMailFolders($upn) as $folder) {
            $id = (string) $folder->getId();
            if ($id === '') {
                continue;
            }

            $byId[$id] = [
                'id' => $id,
                'name' => (string) $folder->getDisplayName(),
                'parent_id' => $folder->getParentFolderId(),
            ];
        }

        $paths = [];
        $pathFor = function (string $id) use (&$pathFor, &$paths, $byId): string {
            if (isset($paths[$id])) {
                return $paths[$id];
            }

            $parentId = $byId[$id]['parent_id'];
            $paths[$id] = is_string($parentId) && isset($byId[$parentId])
                ? $pathFor($parentId).' / '.$byId[$id]['name']
                : $byId[$id]['name'];

            return $paths[$id];
        };

        $depthFor = function (string $id) use (&$depthFor, $byId): int {
            $parentId = $byId[$id]['parent_id'] ?? null;

            return is_string($parentId) && isset($byId[$parentId]) ? $depthFor($parentId) + 1 : 0;
        };

        return collect($byId)
            ->map(fn (array $folder): array => [
                'id' => $folder['id'],
                'name' => $folder['name'],
                'path' => $pathFor($folder['id']),
                'depth' => $depthFor($folder['id']),
            ])
            ->sortBy('path')
            ->values()
            ->all();
    }

    public function markAsRead(string $upn, string $messageId): void
    {
        $this->mailService();
        MailService::markAsRead($upn, $messageId);
    }

    public function move(string $upn, string $messageId, string $folderId): string
    {
        $this->mailService();
        $moved = MailService::move($upn, $messageId, $folderId);
        $newId = $moved->getId();

        if (! is_string($newId) || $newId === '') {
            throw new RuntimeException('Graph hat nach dem Verschieben keine Message-ID geliefert.');
        }

        return $newId;
    }
}
