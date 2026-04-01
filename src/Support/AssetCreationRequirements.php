<?php

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Data\AppSettings;

class AssetCreationRequirements
{
    public function __construct(
        public string $variant,
        public ?bool $valueOverThreshold,
    ) {
    }

    public static function fromExplicitSelection(string $variant, ?bool $valueOverThreshold, AppSettings $settings): ?self
    {
        if (! in_array($variant, ['bestellung', 'beschaffung'], true)) {
            return null;
        }

        if ($valueOverThreshold === null) {
            return null;
        }

        return new self($variant, $valueOverThreshold);
    }

    public function showOrderNumber(): bool
    {
        return $this->variant === 'bestellung';
    }

    public function showInvoiceNumber(): bool
    {
        return $this->variant === 'beschaffung';
    }

    public function showItexiaId(): bool
    {
        return $this->valueOverThreshold === true;
    }

    public function orderNumberRequired(AppSettings $settings): bool
    {
        if (! $this->showOrderNumber()) {
            return false;
        }

        if ($this->valueOverThreshold === true) {
            return true;
        }

        return $settings->benBenoetigtWennWertKleinerGrenze;
    }

    public function invoiceNumberRequired(): bool
    {
        return $this->variant === 'beschaffung';
    }

    public function itexiaIdRequired(): bool
    {
        return $this->valueOverThreshold === true;
    }
}
