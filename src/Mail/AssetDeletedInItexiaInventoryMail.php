<?php

namespace Hwkdo\IntranetAppAssets\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssetDeletedInItexiaInventoryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $deletedByName,
        public string $deleteReason,
        public string $typeName,
        public string $vendorName,
        public string $modelName,
        public string $itexiaId,
        public ?string $itexiaUuid,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Intranet: Asset mit Itexia-Verknüpfung gelöscht',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-assets::mail.asset-deleted-in-itexia-inventory',
        );
    }
}
