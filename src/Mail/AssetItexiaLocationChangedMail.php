<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssetItexiaLocationChangedMail extends Mailable implements ShouldQueue
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
        public ?string $itexiaUuid,
        public ?string $previousLocation,
        public ?string $newLocation,
        public string $roomSyncSummary,
        public string $assetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Intranet: Standort eines Assets mit Itexia-ID geändert',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'intranet-app-assets::mail.asset-itexia-location-changed',
        );
    }
}
