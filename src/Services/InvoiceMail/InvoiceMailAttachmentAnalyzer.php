<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use Hwkdo\IntranetAppAssets\Ai\Agents\InvoiceMailRecipientAgent;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailAttachmentStatus;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailD3Status;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailDocumentKind;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailEventLevel;
use Hwkdo\IntranetAppAssets\Enums\InvoiceMailMatchStatus;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppAssets\Support\InvoiceMailAccompanyingDocument;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceMailAttachmentAnalyzer
{
    public function __construct(
        private readonly InvoiceMailDocumentReader $documentReader,
        private readonly IntranetAiGatewayInterface $gateway,
        private readonly InvoiceMailRecipientMatcher $matcher,
    ) {}

    public function analyze(InvoiceMailAttachment $attachment): void
    {
        $mail = $attachment->invoiceMail;
        $startedAt = microtime(true);

        $attachment->update(['status' => InvoiceMailAttachmentStatus::Analyzing, 'last_error' => null]);

        if (InvoiceMailAccompanyingDocument::matchesFilename((string) $attachment->filename)) {
            $this->markAccompanying(
                $attachment,
                'Der Dateiname bezeichnet ein Begleitdokument (AGB, Datenschutz, Widerruf oder Ähnliches), keinen Beleg.',
            );

            return;
        }

        ['text' => $text, 'source' => $source] = $this->documentReader->text($attachment);

        $structured = $this->gateway->agent(
            new InvoiceMailRecipientAgent,
            $this->prompt($attachment, $text),
            new AiRequestContext(appIdentifier: 'assets', capability: AiCapability::Agent),
        );

        if (! is_array($structured)) {
            throw new RuntimeException('Die KI-Antwort enthält keine strukturierten Daten.');
        }

        $recipients = array_values(array_filter(
            is_array($structured['recipients'] ?? null) ? $structured['recipients'] : [],
            'is_array',
        ));

        $accompanying = InvoiceMailAccompanyingDocument::matchesText($text);

        if ($accompanying) {
            $structured['document_kind'] = InvoiceMailDocumentKind::Other->value;
            $structured['reasoning'] = 'Der Anhangtext ist ein Begleitdokument (AGB, Datenschutz, Widerruf oder Ähnliches), kein Beleg.';
            $recipients = [];
        }

        $match = $this->matcher->match($recipients, (float) ($structured['confidence'] ?? 0));

        $attachment->update([
            'status' => InvoiceMailAttachmentStatus::Analyzed,
            'document_kind' => is_string($structured['document_kind'] ?? null) ? $structured['document_kind'] : null,
            'text_source' => $source,
            'extracted_text' => $text,
            'agent_result' => $structured,
            'match_status' => $match['status'],
            'suggested_user_id' => $match['user_id'],
            'suggested_confidence' => $match['confidence'],
            'candidates' => $match['candidates'],
        ]);

        $mail?->logEvent(
            'analyze',
            $accompanying
                ? 'Begleitdokument anhand des Textes erkannt: '.$attachment->filename
                : match ($match['status']) {
                    InvoiceMailMatchStatus::Unique => 'Zuordnung: '.($match['candidates'][0]['name'] ?? '?').' ('.round(($match['confidence'] ?? 0) * 100).' %).',
                    InvoiceMailMatchStatus::Ambiguous => 'Mehrdeutig: '.count($match['candidates']).' mögliche Empfänger.',
                    InvoiceMailMatchStatus::None => 'Kein Empfänger gefunden.',
                },
            $accompanying || $match['status'] === InvoiceMailMatchStatus::Unique ? InvoiceMailEventLevel::Info : InvoiceMailEventLevel::Warning,
            ['text_source' => $source, 'text_chars' => mb_strlen($text), 'recipients' => $recipients],
            $attachment->id,
            (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    private function markAccompanying(InvoiceMailAttachment $attachment, string $reason): void
    {
        $attachment->update([
            'status' => InvoiceMailAttachmentStatus::Analyzed,
            'document_kind' => InvoiceMailDocumentKind::Other->value,
            'agent_result' => [
                'document_kind' => InvoiceMailDocumentKind::Other->value,
                'reasoning' => $reason,
                'confidence' => 1,
                'recipients' => [],
            ],
            'match_status' => InvoiceMailMatchStatus::None,
            'suggested_user_id' => null,
            'suggested_confidence' => null,
            'candidates' => [],
        ]);

        $attachment->invoiceMail?->logEvent(
            'analyze',
            $reason.' '.$attachment->filename,
            InvoiceMailEventLevel::Info,
            attachmentId: $attachment->id,
        );
    }

    /**
     * Auto-Upload nur ohne Report-Modus, eindeutig, sicher genug, unkorrigiert und noch nicht hochgeladen.
     */
    public function shouldAutoUpload(InvoiceMailAttachment $attachment): bool
    {
        $mail = $attachment->invoiceMail;
        $settings = IntranetAppAssetsSettings::resolvedAppSettings();

        return $mail !== null
            && ! $mail->report_mode
            && ! $settings->invoiceMailReportMode
            && $attachment->status === InvoiceMailAttachmentStatus::Analyzed
            && $attachment->match_status === InvoiceMailMatchStatus::Unique
            && $attachment->suggested_user_id !== null
            && ! $attachment->wasCorrected()
            && ($attachment->suggested_confidence ?? 0) >= $settings->invoiceMailAutoUploadMinConfidence
            && in_array($attachment->d3_status, [InvoiceMailD3Status::None, null], true)
            && InvoiceMailDocumentKind::tryFrom((string) $attachment->document_kind)?->d3Model() !== null;
    }

    private function prompt(InvoiceMailAttachment $attachment, string $text): string
    {
        $mail = $attachment->invoiceMail;

        return implode("\n", [
            'E-Mail',
            'Von: '.trim(($mail?->from_name ?? '').' <'.($mail?->from_address ?? '').'>'),
            'An: '.implode(', ', $mail?->to_recipients ?? []),
            'Betreff: '.($mail?->subject ?? ''),
            '',
            Str::limit($mail?->plainBody() ?? '', 6000),
            '',
            'Anhang: '.$attachment->filename,
            '',
            Str::limit($text, 20000),
        ]);
    }
}
