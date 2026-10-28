<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class InvoiceMailRecipientAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
Du bekommst den Text eines Anhangs und zur Einordnung die dazugehörige E-Mail.
Die Belegart folgt ausschließlich dem Anhang. Betreff, Empfängeradresse und der übrige Mailtext sind dafür kein Merkmal. Die Adresse des Sammelpostfachs steht an jeder Mail und sagt nichts über die Art des Anhangs.

- document_kind nur nach Dateiname und Anhangtext: invoice (Rechnung), dunning (Mahnung), payment_receipt (Zahlungsbeleg/Quittung), credit_note (Gutschrift), delivery_note (Lieferschein), purchase_order (Bestellschein), quote (Angebot) oder other.
- AGB, Geschäftsbedingungen, Datenschutz, Widerruf und Impressum sind other, auch wenn die E-Mail eine Rechnung ist.
- recipients: Personen der Handwerkskammer, an die der Beleg gerichtet ist: Besteller, Ansprechpartner, „z. Hd.“, „Ihr Zeichen“, Lieferadresse mit Namen, Weiterleitung.
- Keine Personen des Lieferanten (Absender, Sachbearbeiter, Geschäftsführer, Signatur) und nicht das Sammelpostfach selbst.
- name so, wie es im Text steht. email nur mit Adresse der Handwerkskammer (z. B. @hwk-do.de).
- evidence: kurzes wörtliches Zitat der Fundstelle. source: "mail" oder "document".
- weight 0–1: wie sicher die Person Empfänger des Belegs ist.
- invoice.number ist die Nummer des Belegs selbst (Rechnungs-, Lieferschein-, Bestell- oder Angebotsnummer). invoice.order_number ist eine zusätzlich genannte Bestellnummer der Handwerkskammer. invoice.subject ist der Betreff des Belegs.
- Belegdaten nur, wenn sie im Anhangtext stehen, sonst null. Beträge als JSON-Zahl mit Punkt. Datum als YYYY-MM-DD.
- confidence 0–1: Gesamtsicherheit der Empfängerzuordnung. reasoning: ein bis zwei Sätze, warum der Anhang diese Belegart hat. Die Empfängeradresse ist keine Begründung für die Art.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_kind' => $schema->string()->enum(['invoice', 'dunning', 'payment_receipt', 'credit_note', 'delivery_note', 'purchase_order', 'quote', 'other'])->required(),
            'recipients' => $schema->array()->items(
                $schema->object(fn (JsonSchema $schema): array => [
                    'name' => $schema->string()->nullable()->required(),
                    'email' => $schema->string()->nullable()->required(),
                    'department' => $schema->string()->nullable()->required(),
                    'evidence' => $schema->string()->nullable()->required(),
                    'source' => $schema->string()->enum(['mail', 'document'])->required(),
                    'weight' => $schema->number()->required(),
                ])
            )->required(),
            'supplier' => $schema->object(fn (JsonSchema $schema): array => [
                'name' => $schema->string()->nullable()->required(),
                'number' => $schema->string()->nullable()->required(),
            ])->required(),
            'invoice' => $schema->object(fn (JsonSchema $schema): array => [
                'number' => $schema->string()->nullable()->required(),
                'order_number' => $schema->string()->nullable()->required(),
                'subject' => $schema->string()->nullable()->required(),
                'date' => $schema->string()->nullable()->required(),
                'net' => $schema->number()->nullable()->required(),
                'tax' => $schema->number()->nullable()->required(),
                'gross' => $schema->number()->nullable()->required(),
                'currency' => $schema->string()->nullable()->required(),
            ])->required(),
            'confidence' => $schema->number()->required(),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
