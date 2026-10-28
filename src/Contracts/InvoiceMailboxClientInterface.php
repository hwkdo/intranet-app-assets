<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Contracts;

use Carbon\CarbonInterface;

/**
 * Zugriff auf das Rechnungs-Sammelpostfach, als Arrays entkoppelt vom Graph-SDK.
 */
interface InvoiceMailboxClientInterface
{
    /**
     * @return array{
     *   id: string,
     *   internet_message_id: ?string,
     *   from_name: ?string,
     *   from_address: ?string,
     *   to: list<string>,
     *   subject: ?string,
     *   received_at: ?string,
     *   body: string,
     *   body_type: string,
     *   has_attachments: bool
     * }
     */
    public function getMessage(string $upn, string $messageId): array;

    /**
     * @return list<array{id: ?string, name: string, content_type: ?string, size: int, is_inline: bool, content: ?string}>
     */
    public function listAttachments(string $upn, string $messageId): array;

    /**
     * @return iterable<int, string> Message-IDs im Zeitraum
     */
    public function messageIdsReceivedBetween(string $upn, CarbonInterface $from, CarbonInterface $to, string $folderId = 'inbox'): iterable;

    /**
     * @return list<array{id: string, name: string, path: string, depth: int}>
     */
    public function folders(string $upn): array;

    public function markAsRead(string $upn, string $messageId): void;

    /**
     * @return string neue Message-ID nach dem Verschieben
     */
    public function move(string $upn, string $messageId, string $folderId): string;
}
