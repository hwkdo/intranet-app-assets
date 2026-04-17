<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Support;

use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceVisionLlmProvider;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;

/**
 * Vision-Modell für D3-Rechnungsanalyse: Open Web UI (Ollama/…) vs. Langdock.
 * Reihenfolge: MCP-/Aufruf-Override → AppSettings → Env/Config.
 */
final class D3InvoiceVisionModelResolver
{
    /**
     * @param  string|null  $override  z. B. MCP-Parameter vision_model — hat Vorrang, wenn nicht leer.
     */
    public static function resolve(
        D3InvoiceVisionLlmProvider $provider,
        ?string $override = null,
        ?AppSettings $appSettings = null,
    ): string {
        $trimmedOverride = trim((string) ($override ?? ''));
        if ($trimmedOverride !== '') {
            return $trimmedOverride;
        }

        $settings = $appSettings ?? IntranetAppAssetsSettings::resolvedAppSettings();

        if ($provider === D3InvoiceVisionLlmProvider::Langdock) {
            $fromApp = trim($settings->d3InvoiceVisionModelLangdock);
            if ($fromApp !== '') {
                return $fromApp;
            }

            return trim((string) config(
                'intranet-app-assets.d3_invoice_vision_model_langdock',
                'gpt-5-mini'
            ));
        }

        $fromApp = trim($settings->d3InvoiceVisionModelOpenWebUi);
        if ($fromApp !== '') {
            return $fromApp;
        }

        $fromConfig = trim((string) config('intranet-app-assets.d3_invoice_vision_model', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return trim((string) config('openwebui-api-laravel.default_model', ''));
    }
}
