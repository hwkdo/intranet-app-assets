<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Contracts\D3InvoiceVisionLlmClientInterface;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceVisionLlmProvider;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Illuminate\Contracts\Foundation\Application;

class D3InvoiceVisionLlmClientFactory
{
    public function __construct(
        private Application $app,
        private LangdockD3InvoiceVisionLlmClient $langdockClient,
    ) {}

    public function make(?AppSettings $appSettings = null): D3InvoiceVisionLlmClientInterface
    {
        $settings = $appSettings ?? IntranetAppAssetsSettings::resolvedAppSettings();

        return match ($settings->d3InvoiceVisionLlmProvider) {
            D3InvoiceVisionLlmProvider::Langdock => $this->langdockClient,
            D3InvoiceVisionLlmProvider::OpenWebUi => $this->openWebUiWhenAvailable(),
        };
    }

    private function openWebUiWhenAvailable(): D3InvoiceVisionLlmClientInterface
    {
        if (! class_exists(\Hwkdo\OpenwebuiApiLaravel\Services\OpenWebUiRagService::class)) {
            throw new \RuntimeException(
                'Als D3-Vision-Provider ist Open Web UI eingestellt, aber hwkdo/openwebui-api-laravel fehlt oder ist nicht geladen.'
            );
        }

        return $this->app->make(OpenWebUiD3InvoiceVisionLlmClient::class);
    }
}
