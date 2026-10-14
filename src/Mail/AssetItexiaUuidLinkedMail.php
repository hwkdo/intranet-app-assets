<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssetItexiaUuidLinkedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $actorName,
        public string $typeName,
        public string $vendorName,
        public string $modelName,
        public string $serialNumber,
        public string $itexiaId,
        public string $itexiaUuid,
        public ?string $invoiceNumber,
        public ?string $invoiceUrl,
        public string $assetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Intranet: Asset mit Itexia-UUID verknüpft',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-assets::mail.asset-itexia-uuid-linked',
        );
    }
}
