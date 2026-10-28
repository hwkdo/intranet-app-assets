<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

use Hwkdo\D3RestLaravel\models\Angebot;
use Hwkdo\D3RestLaravel\models\Bestellschein;
use Hwkdo\D3RestLaravel\models\Lieferschein;
use Hwkdo\D3RestLaravel\models\Zahlungsbeleg;

enum InvoiceMailDocumentKind: string
{
    case Invoice = 'invoice';
    case Dunning = 'dunning';
    case PaymentReceipt = 'payment_receipt';
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
    case PurchaseOrder = 'purchase_order';
    case Quote = 'quote';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Rechnung',
            self::Dunning => 'Mahnung',
            self::PaymentReceipt => 'Zahlungsbeleg',
            self::CreditNote => 'Gutschrift',
            self::DeliveryNote => 'Lieferschein',
            self::PurchaseOrder => 'Bestellschein',
            self::Quote => 'Angebot',
            self::Other => 'Sonstiges',
        };
    }

    /**
     * D3-Modell für den Upload. Sonstiges hat keins.
     *
     * @return class-string|null
     */
    public function d3Model(): ?string
    {
        return match ($this) {
            self::Invoice, self::Dunning, self::PaymentReceipt, self::CreditNote => Zahlungsbeleg::class,
            self::DeliveryNote => Lieferschein::class,
            self::PurchaseOrder => Bestellschein::class,
            self::Quote => Angebot::class,
            self::Other => null,
        };
    }

    public function d3Label(): string
    {
        return match ($this) {
            self::Invoice, self::Dunning, self::PaymentReceipt, self::CreditNote => 'Zahlungsbeleg',
            self::DeliveryNote => 'Lieferschein',
            self::PurchaseOrder => 'Bestellschein',
            self::Quote => 'Angebot',
            self::Other => 'kein D3-Modell',
        };
    }

    public function isZahlungsbeleg(): bool
    {
        return $this->d3Model() === Zahlungsbeleg::class;
    }
}
