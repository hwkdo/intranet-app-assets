<?php

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\D3RestLaravel\Client as D3Client;
use Hwkdo\D3RestLaravel\Enums\DocTypeEnum;
use Hwkdo\IntranetAppAssets\Models\Asset;
use Hwkdo\IntranetAppAssets\Models\AssetHistory;
use Illuminate\Support\Facades\Log;

class InvoiceAutoResolveService
{
    /**
     * Sucht bei Bedarf per D3 eine Rechnung zur BEN oder markiert nach Ablauf der Frist „Rechnungsnr. offen“.
     *
     * @param  bool  $ignoreDaysLimit  Wenn true: keine Prüfung auf Anlage-Frist, kein automatisches Markieren
     *                                  als fehlende Rechnung – nur D3-Suche (für manuelle Komplett-Läufe).
     */
    public function processAsset(Asset $asset, int $maxDays, bool $ignoreDaysLimit = false): void
    {
        if (! $this->needsInvoiceResolution($asset)) {
            return;
        }

        $maxDays = max(1, min(365, $maxDays));

        if (! $ignoreDaysLimit) {
            $deadline = $asset->created_at->copy()->addDays($maxDays);

            if (now()->greaterThanOrEqualTo($deadline)) {
                $this->markExhaustedIfNeeded($asset, $maxDays);

                return;
            }
        }

        $documentId = $this->lookupInvoiceDocumentIdByOrderNumber((string) $asset->order_number);
        if ($documentId === null) {
            return;
        }

        $asset->invoice_number = $documentId;
        $asset->invoice_number_pending = false;
        $asset->save();

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventUpdated,
            'user_id' => null,
            'reason' => 'Rechnungsnummer automatisch aus D3 (BEN-Suche) gesetzt.',
            'meta' => [
                'invoice_auto_resolved' => true,
                'order_number' => $asset->order_number,
            ],
        ]);
    }

    private function needsInvoiceResolution(Asset $asset): bool
    {
        $order = trim((string) ($asset->order_number ?? ''));

        if ($order === '') {
            return false;
        }

        $invoice = trim((string) ($asset->invoice_number ?? ''));

        return $invoice === '';
    }

    private function markExhaustedIfNeeded(Asset $asset, int $maxDays): void
    {
        if ($asset->invoice_number_pending) {
            return;
        }

        $asset->invoice_number_pending = true;
        $asset->save();

        $asset->historyEntries()->create([
            'event' => AssetHistory::EventInvoiceAutoResolveExhausted,
            'user_id' => null,
            'reason' => 'Automatische Rechnungssuche (D3) nach Ablauf der konfigurierten Tage ohne Treffer beendet – Rechnungsnr. bitte manuell nachtragen.',
            'meta' => [
                'invoice_auto_resolve_max_days' => $maxDays,
                'order_number' => $asset->order_number,
            ],
        ]);
    }

    private function lookupInvoiceDocumentIdByOrderNumber(string $orderNumber): ?string
    {
        $term = trim($orderNumber);
        if ($term === '') {
            return null;
        }

        if (! class_exists(D3Client::class)) {
            return null;
        }

        try {
            $client = app(D3Client::class);
            $raw = $client->SearchResult($term, DocTypeEnum::Zahlungsbeleg, null, 200, true);
            $items = is_array($raw) ? ($raw['items'] ?? []) : [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $id = $item['id'] ?? null;
                if (filled($id)) {
                    return (string) $id;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('InvoiceAutoResolveService: D3-Suche fehlgeschlagen.', [
                'order_number' => $term,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
