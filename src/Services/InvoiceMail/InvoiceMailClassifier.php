<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services\InvoiceMail;

use Hwkdo\IntranetAppAssets\Enums\InvoiceMailDocumentKind;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Models\InvoiceMail;
use Hwkdo\IntranetAppAssets\Models\InvoiceMailAttachment;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiClassificationQuestion;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Illuminate\Support\Str;

class InvoiceMailClassifier
{
    private const AutoReplyPattern = '/^\s*(automatische antwort|abwesenheitsnotiz|out of office|auto(matic)?[ -]?reply|undeliverable|unzustellbar|delivery status notification|mail delivery failed|lesebestätigung|read:)/iu';

    /**
     * Eine sichere Belegart lässt die Mail durch, auch wenn die Ja/Nein-Frage unter der Schwelle liegt.
     */
    private const DocumentKindConfidence = 0.9;

    public function __construct(
        private readonly IntranetAiGatewayInterface $gateway,
        private readonly InvoiceMailDocumentReader $documentReader,
    ) {}

    /**
     * @return array{
     *   is_relevant: bool,
     *   probability: ?float,
     *   kind: ?string,
     *   threshold: float,
     *   reason: ?string,
     *   relevant_via: ?string,
     *   payload: array<string, mixed>
     * }
     */
    public function classify(InvoiceMail $mail): array
    {
        $settings = IntranetAppAssetsSettings::resolvedAppSettings();
        $threshold = max(0.0, min(1.0, $settings->invoiceMailRelevanceThreshold));

        $hardReason = $this->hardFilterReason($mail, $settings->invoiceMailSenderBlocklist);
        if ($hardReason !== null) {
            return [
                'is_relevant' => false,
                'probability' => null,
                'kind' => null,
                'threshold' => $threshold,
                'reason' => $hardReason,
                'relevant_via' => null,
                'payload' => ['hard_filter' => $hardReason],
            ];
        }

        $candidate = $mail->attachments->first(fn (InvoiceMailAttachment $a): bool => $a->isCandidate());
        $imagePath = null;
        $images = [];

        try {
            try {
                $imagePath = $candidate instanceof InvoiceMailAttachment ? $this->documentReader->firstPageImage($candidate) : null;
            } catch (\Throwable) {
                $imagePath = null;
            }

            if ($imagePath !== null) {
                $images['document'] = $imagePath;
            }

            $result = $this->gateway->classify(
                $this->state($mail),
                [
                    'relevant' => AiClassificationQuestion::boolean(
                        'Enthält diese E-Mail einen echten geschäftlichen Beleg (Rechnung, Mahnung, Zahlungsbeleg, Gutschrift, Lieferschein, Bestellschein oder Angebot)? Eine automatische Versandnachricht oder eine interne Weiterleitung zählt dazu, wenn ein solcher Beleg als Anhang dabei ist. Die Empfängeradresse ist kein Merkmal. Spam, Phishing, Newsletter und Werbung ohne Beleg zählen nicht.',
                        [
                            'true' => 'Ein Belegdokument hängt an, auch bei Weiterleitung oder automatischer Versandmail',
                            'false' => 'Spam, Phishing, Werbung, Newsletter oder kein Belegdokument',
                        ],
                    ),
                    'kind' => AiClassificationQuestion::choice('Welche Art hat das gezeigte Dokument? Entscheide nach dem Dokument, nicht nach der E-Mail-Adresse. AGB, Datenschutz und Widerruf sind Sonstiges.', [
                        'invoice' => 'Rechnung',
                        'dunning' => 'Mahnung oder Zahlungserinnerung',
                        'payment_receipt' => 'Zahlungsbeleg oder Quittung',
                        'credit_note' => 'Gutschrift',
                        'delivery_note' => 'Lieferschein',
                        'purchase_order' => 'Bestellschein oder Bestellung',
                        'quote' => 'Angebot',
                        'other' => 'Sonstiges, kein Beleg, oder nur Begleitdokument wie AGB, Datenschutz oder Widerruf',
                    ]),
                ],
                new AiRequestContext(appIdentifier: 'assets', capability: AiCapability::Text),
                $images,
            );
        } finally {
            if ($candidate instanceof InvoiceMailAttachment) {
                $this->documentReader->cleanupImage($imagePath, $candidate);
            }
        }

        $probability = $result->probability('relevant');
        $kind = $result->choice('kind');
        $passesThreshold = $probability !== null && $probability >= $threshold;
        $sureDocument = $this->hasSureDocumentKind($mail, $kind, $result->choiceProbabilities('kind'));
        $isRelevant = $passesThreshold || $sureDocument;

        return [
            'is_relevant' => $isRelevant,
            'probability' => $probability,
            'kind' => $kind,
            'threshold' => $threshold,
            'reason' => $isRelevant ? null : 'JEV-Wahrscheinlichkeit unter der Schwelle',
            'relevant_via' => $isRelevant ? ($passesThreshold ? 'threshold' : 'document_kind') : null,
            'payload' => [...$result->toArray(), 'with_image' => $images !== [], 'relevant_via' => $isRelevant ? ($passesThreshold ? 'threshold' : 'document_kind') : null],
        ];
    }

    /**
     * @param  array<string, float>  $kindProbabilities
     */
    private function hasSureDocumentKind(InvoiceMail $mail, ?string $kind, array $kindProbabilities): bool
    {
        $documentKind = InvoiceMailDocumentKind::tryFrom((string) $kind);
        $confidence = $kind === null ? null : ($kindProbabilities[$kind] ?? null);

        return $documentKind?->d3Model() !== null
            && $confidence !== null
            && $confidence >= self::DocumentKindConfidence
            && $mail->attachments->contains(fn (InvoiceMailAttachment $attachment): bool => $attachment->isCandidate());
    }

    /**
     * @param  array<int, string>  $blocklist
     */
    public function hardFilterReason(InvoiceMail $mail, array $blocklist = []): ?string
    {
        if (preg_match(self::AutoReplyPattern, (string) $mail->subject) === 1) {
            return 'Automatische Antwort oder Systemnachricht';
        }

        $from = Str::lower((string) $mail->from_address);
        foreach ($blocklist as $entry) {
            $entry = Str::lower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }
            if ($from === $entry || (str_starts_with($entry, '@') && str_ends_with($from, $entry))) {
                return 'Absender steht auf der Sperrliste';
            }
        }

        if (! $mail->attachments->contains(fn (InvoiceMailAttachment $a): bool => $a->isCandidate())) {
            return 'Kein PDF- oder Bildanhang';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function state(InvoiceMail $mail): array
    {
        return [
            'from' => trim(($mail->from_name ?? '').' <'.($mail->from_address ?? '').'>'),
            'subject' => (string) $mail->subject,
            'body' => Str::limit($mail->plainBody(), 4000),
            'attachments' => $mail->attachments
                ->map(fn (InvoiceMailAttachment $a): string => $a->filename.' ('.($a->mime_type ?? 'unbekannt').')')
                ->values()
                ->all(),
        ];
    }
}
