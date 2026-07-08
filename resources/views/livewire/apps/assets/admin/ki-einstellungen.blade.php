<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Enums\D3InvoiceVisionLlmProvider;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
use Hwkdo\IntranetAppAssets\Support\D3InvoiceVisionModelResolver;
use Hwkdo\IntranetAppBase\Contracts\AiConfigResolverInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetBaseAiConfigSourceInterface;
use Hwkdo\IntranetAppBase\Enums\AiCapability;
use Hwkdo\IntranetAppBase\Enums\AiProvider;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $aiTextProviderOverride = '';

    public string $aiTextModelOverride = '';

    public string $aiImageProviderOverride = '';

    public string $aiImageModelOverride = '';

    public string $d3InvoiceVisionLlmProvider = 'openwebui';

    public string $d3InvoiceVisionModelOpenWebUi = '';

    public string $d3InvoiceVisionModelLangdock = '';

    public string $openWebUiModel = 'intranet-app-assets';

    public string $openWebUiCollectionIds = '';

    public function mount(): void
    {
        $settings = IntranetAppAssetsSettings::resolvedAppSettings();

        $this->aiTextProviderOverride = $settings->aiTextProviderOverride?->value ?? '';
        $this->aiTextModelOverride = $settings->textModelOverride() ?? '';
        $this->aiImageProviderOverride = $settings->aiImageProviderOverride?->value ?? '';
        $this->aiImageModelOverride = $settings->imageModelOverride() ?? '';
        $this->d3InvoiceVisionLlmProvider = $settings->d3InvoiceVisionLlmProvider->value;
        $this->d3InvoiceVisionModelOpenWebUi = $settings->d3InvoiceVisionModelOpenWebUi;
        $this->d3InvoiceVisionModelLangdock = $settings->d3InvoiceVisionModelLangdock;
        $this->openWebUiModel = $settings->openWebUiModel;
        $this->openWebUiCollectionIds = json_encode(
            $settings->openWebUiCollectionIds,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '[]';
    }

    public function save(): void
    {
        $this->validate([
            'aiTextProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiTextModelOverride' => 'nullable|string|max:100',
            'aiImageProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiImageModelOverride' => 'nullable|string|max:100',
            'd3InvoiceVisionLlmProvider' => ['required', 'string', Rule::enum(D3InvoiceVisionLlmProvider::class)],
            'd3InvoiceVisionModelOpenWebUi' => 'nullable|string|max:255',
            'd3InvoiceVisionModelLangdock' => 'nullable|string|max:255',
            'openWebUiModel' => 'required|string|max:255',
            'openWebUiCollectionIds' => 'nullable|string|max:5000',
        ]);

        $collectionIds = $this->decodeCollectionIds($this->openWebUiCollectionIds);
        $current = IntranetAppAssetsSettings::resolvedAppSettings();

        $settings = AppSettings::from(array_merge($current->toArray(), [
            'aiTextProviderOverride' => $this->parseProviderOverride($this->aiTextProviderOverride),
            'aiTextModelOverride' => $this->blankToNull($this->aiTextModelOverride),
            'aiImageProviderOverride' => $this->parseProviderOverride($this->aiImageProviderOverride),
            'aiImageModelOverride' => $this->blankToNull($this->aiImageModelOverride),
            'd3InvoiceVisionLlmProvider' => D3InvoiceVisionLlmProvider::from($this->d3InvoiceVisionLlmProvider),
            'd3InvoiceVisionModelOpenWebUi' => trim($this->d3InvoiceVisionModelOpenWebUi),
            'd3InvoiceVisionModelLangdock' => trim($this->d3InvoiceVisionModelLangdock),
            'openWebUiModel' => trim($this->openWebUiModel),
            'openWebUiCollectionIds' => $collectionIds,
        ]));

        IntranetAppAssetsSettings::persistAppSettings($settings);

        Flux::toast(
            heading: 'Gespeichert',
            text: 'KI-Einstellungen wurden gespeichert.',
            variant: 'success',
        );
    }

    #[Computed]
    public function baseAiTextSummary(): string
    {
        $base = app(IntranetBaseAiConfigSourceInterface::class);
        $model = $base->textModel() ?? 'Provider-Standard';

        return $base->textProvider()->label().' / '.$model;
    }

    #[Computed]
    public function baseAiImageSummary(): string
    {
        $base = app(IntranetBaseAiConfigSourceInterface::class);
        $model = $base->imageModel() ?? 'Provider-Standard';

        return $base->imageProvider()->label().' / '.$model;
    }

    #[Computed]
    public function effectiveAiTextSummary(): string
    {
        $resolved = app(AiConfigResolverInterface::class)->resolve('assets', AiCapability::Text);

        return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
    }

    #[Computed]
    public function effectiveAiImageSummary(): string
    {
        $resolved = app(AiConfigResolverInterface::class)->resolve('assets', AiCapability::Image);

        return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
    }

    #[Computed]
    public function effectiveAiVisionSummary(): string
    {
        $resolved = app(AiConfigResolverInterface::class)->resolve('assets', AiCapability::Vision);

        return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
    }

    #[Computed]
    public function effectiveD3VisionSummary(): string
    {
        $provider = D3InvoiceVisionLlmProvider::from($this->d3InvoiceVisionLlmProvider);
        $model = D3InvoiceVisionModelResolver::resolve(
            $provider,
            null,
            IntranetAppAssetsSettings::resolvedAppSettings(),
        );
        $label = $provider === D3InvoiceVisionLlmProvider::Langdock ? 'Langdock' : 'Open Web UI';

        return $label.' / '.($model !== '' ? $model : 'nicht konfiguriert');
    }

    /**
     * @return list<string>
     */
    private function decodeCollectionIds(string $raw): array
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || $trimmed === '[]') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'openWebUiCollectionIds' => 'Ungültiges JSON für Collection-IDs.',
            ]);
        }

        return array_values(array_filter(array_map(
            fn (mixed $id): string => trim((string) $id),
            $decoded,
        ), fn (string $id): bool => $id !== ''));
    }

    private function parseProviderOverride(string $value): ?AiProvider
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return AiProvider::from($trimmed);
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
};
?>

<flux:card class="glass-card">
    <flux:heading size="lg" class="mb-2">KI-Einstellungen</flux:heading>
    <flux:text class="mb-6 text-sm text-zinc-500">
        Gateway-Overrides, D3-Rechnungsvision und Assets-KI-Chat. Leere Override-Felder nutzen die globalen Einstellungen unter Manager → Base Settings.
    </flux:text>

    <div class="space-y-8">
        <div>
            <flux:heading size="sm" class="mb-3">Gateway (Text, Bild, Vision)</flux:heading>

            <flux:callout class="mb-4" icon="information-circle">
                <flux:callout.heading>Globale KI-Standards</flux:callout.heading>
                <flux:callout.text>
                    Text: <strong>{{ $this->baseAiTextSummary }}</strong><br>
                    Bilder: <strong>{{ $this->baseAiImageSummary }}</strong>
                    — änderbar unter Manager → Base Settings.
                </flux:callout.text>
            </flux:callout>

            <x-intranet-app-base::admin-ai-settings
                ai-text-provider-override="aiTextProviderOverride"
                ai-text-model-override="aiTextModelOverride"
                ai-image-provider-override="aiImageProviderOverride"
                ai-image-model-override="aiImageModelOverride"
            />

            <flux:text class="mt-4 text-sm text-zinc-500">
                Aktuell wirksam für Assets:
                Text <strong>{{ $this->effectiveAiTextSummary }}</strong>,
                Bilder <strong>{{ $this->effectiveAiImageSummary }}</strong>,
                Vision (Gateway) <strong>{{ $this->effectiveAiVisionSummary }}</strong>.
            </flux:text>
        </div>

        <flux:separator />

        <div>
            <flux:heading size="sm" class="mb-3">D3-Rechnungsvision</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">
                Steuert OCR und strukturierte Auswertung von Rechnungs-PDFs aus D3 (Queue, MCP-Tool, Rechnungen-Übersicht).
                Bei Langdock nutzt der Vision-Client zusätzlich die Gateway-Konfiguration oben.
            </flux:text>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model.live="d3InvoiceVisionLlmProvider" label="Vision-Backend">
                    @foreach (\Hwkdo\IntranetAppAssets\Enums\D3InvoiceVisionLlmProvider::options() as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:field>
                    <flux:label>Aktuell wirksam</flux:label>
                    <flux:text class="text-sm">{{ $this->effectiveD3VisionSummary }}</flux:text>
                </flux:field>

                @if ($d3InvoiceVisionLlmProvider === 'openwebui')
                    <flux:input
                        wire:model="d3InvoiceVisionModelOpenWebUi"
                        class="md:col-span-2"
                        label="Vision-Modell (Open Web UI)"
                        placeholder="Leer = INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL / OPENWEBUI_DEFAULT_MODEL"
                    />
                @else
                    <flux:input
                        wire:model="d3InvoiceVisionModelLangdock"
                        class="md:col-span-2"
                        label="Vision-Modell (Langdock)"
                        placeholder="Leer = INTRANET_APP_ASSETS_D3_INVOICE_VISION_MODEL_LANGDOCK"
                    />
                @endif
            </div>
        </div>

        <flux:separator />

        <div>
            <flux:heading size="sm" class="mb-3">Assets-KI-Chat</flux:heading>
            <flux:text class="mb-4 text-sm text-zinc-500">
                Interaktiver Chat (prism-chat / Open Web UI) — separat vom zentralen Gateway.
            </flux:text>

            <div class="grid gap-4">
                <flux:input
                    wire:model="openWebUiModel"
                    label="Open Web UI Modell"
                    description="Modellname in Open Web UI für den Assets-Assistenten."
                />

                <flux:field>
                    <flux:label>Open Web UI Collection-IDs</flux:label>
                    <flux:textarea
                        wire:model="openWebUiCollectionIds"
                        rows="4"
                        placeholder='["collection-id-1", "collection-id-2"]'
                    />
                    <flux:description>JSON-Array für file_search im Assets-KI-Chat.</flux:description>
                </flux:field>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <flux:button wire:click="save" variant="primary">
            KI-Einstellungen speichern
        </flux:button>
    </div>
</flux:card>
