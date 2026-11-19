<?php

use Flux\Flux;
use Hwkdo\IntranetAppAssets\Data\AppSettings;
use Hwkdo\IntranetAppAssets\Models\IntranetAppAssetsSettings;
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

    public string $aiImageEditProviderOverride = '';

    public string $aiImageEditModelOverride = '';

    public string $aiClassificationProviderOverride = '';

    public string $aiClassificationModelOverride = '';

    public string $openWebUiModel = 'intranet-app-assets';

    public string $openWebUiCollectionIds = '';

    public function mount(): void
    {
        $settings = IntranetAppAssetsSettings::resolvedAppSettings();

        $this->aiTextProviderOverride = $settings->aiTextProviderOverride?->value ?? '';
        $this->aiTextModelOverride = $settings->textModelOverride() ?? '';
        $this->aiImageProviderOverride = $settings->aiImageProviderOverride?->value ?? '';
        $this->aiImageModelOverride = $settings->imageModelOverride() ?? '';
        $this->aiImageEditProviderOverride = $settings->aiImageEditProviderOverride?->value ?? '';
        $this->aiImageEditModelOverride = $settings->imageEditModelOverride() ?? '';
        $this->aiClassificationProviderOverride = $settings->aiClassificationProviderOverride?->value ?? '';
        $this->aiClassificationModelOverride = $settings->classificationModelOverride() ?? '';
        $this->openWebUiModel = $settings->openWebUiModel;
        $this->openWebUiCollectionIds = json_encode(
            $settings->openWebUiCollectionIds,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '[]';
    }

    public function updatedAiImageProviderOverride(): void
    {
        $this->aiImageModelOverride = '';
    }

    public function updatedAiImageEditProviderOverride(): void
    {
        $this->aiImageEditModelOverride = '';
    }

    public function save(): void
    {
        $this->validate([
            'aiTextProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiTextModelOverride' => 'nullable|string|max:100',
            'aiImageProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiImageModelOverride' => 'nullable|string|max:100',
            'aiImageEditProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiImageEditModelOverride' => 'nullable|string|max:150',
            'aiClassificationProviderOverride' => ['nullable', 'string', Rule::enum(AiProvider::class)],
            'aiClassificationModelOverride' => 'nullable|string|max:150',
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
            'aiImageEditProviderOverride' => $this->parseProviderOverride($this->aiImageEditProviderOverride),
            'aiImageEditModelOverride' => $this->blankToNull($this->aiImageEditModelOverride),
            'aiClassificationProviderOverride' => $this->parseProviderOverride($this->aiClassificationProviderOverride),
            'aiClassificationModelOverride' => $this->blankToNull($this->aiClassificationModelOverride),
            'openWebUiModel' => trim($this->openWebUiModel),
            'openWebUiCollectionIds' => $collectionIds,
        ]));

        IntranetAppAssetsSettings::persistAppSettings($settings);

        unset($this->baseAiTextSummary, $this->baseAiImageSummary, $this->baseAiClassificationSummary, $this->effectiveAiTextSummary, $this->effectiveAiImageSummary, $this->effectiveAiClassificationSummary, $this->effectiveAiVisionSummary);

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
        $generate = $base->imageModel() ?? 'Provider-Standard';
        $edit = $base->imageEditModel() ?? 'Provider-Standard';

        return 'Generate '.$base->imageProvider()->label().' / '.$generate
            .', Edit '.$base->imageEditProvider()->label().' / '.$edit;
    }

    #[Computed]
    public function baseAiClassificationSummary(): string
    {
        $base = app(IntranetBaseAiConfigSourceInterface::class);
        $model = $base->classificationModel() ?? 'Provider-Standard';

        return $base->classificationProvider()->label().' / '.$model;
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
        $settings = IntranetAppAssetsSettings::resolvedAppSettings();
        $editProvider = $settings->imageEditProviderOverride()
            ?? app(IntranetBaseAiConfigSourceInterface::class)->imageEditProvider();
        $editModel = app(AiConfigResolverInterface::class)->resolveImageEditModel('assets') ?? 'Provider-Standard';

        return 'Generate '.$resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard')
            .', Edit '.$editProvider->label().' / '.$editModel;
    }

    #[Computed]
    public function effectiveAiClassificationSummary(): string
    {
        $resolved = app(AiConfigResolverInterface::class)->resolve('assets', AiCapability::Classification);

        return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
    }

    #[Computed]
    public function effectiveAiVisionSummary(): string
    {
        $resolved = app(AiConfigResolverInterface::class)->resolve('assets', AiCapability::Vision);

        return $resolved->provider->label().' / '.($resolved->model ?? 'Provider-Standard');
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

<section class="glass-card overflow-hidden p-5 sm:p-6">
    <flux:heading size="lg" class="mb-2">KI-Einstellungen</flux:heading>
    <flux:text class="mb-6 text-sm text-slate-600 dark:text-white/70">
        Gateway-Overrides für die Rechnungsanalyse und der Assets-KI-Chat. Leere Override-Felder nutzen die globalen Einstellungen unter Manager → Base Settings.
    </flux:text>

    <div class="space-y-8">
        <div>
            <flux:heading size="sm" class="mb-3">Gateway (Text, Bild, Vision)</flux:heading>

            <div class="mb-4 flex items-start gap-3 rounded-xl bg-[#d0e3f9]/50 px-4 py-3 text-sm text-[#073070] dark:bg-white/10 dark:text-white">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#073070]/10 text-[#073070] dark:bg-white/10 dark:text-white">
                    <flux:icon icon="information-circle" variant="micro" class="size-5" />
                </span>
                <p>
                    <span class="font-semibold">Globale KI-Standards.</span>
                    Text: <strong>{{ $this->baseAiTextSummary }}</strong>,
                    Bilder: <strong>{{ $this->baseAiImageSummary }}</strong>,
                    Klassifikation: <strong>{{ $this->baseAiClassificationSummary }}</strong>
                    — änderbar unter Manager → Base Settings.
                </p>
            </div>

            <x-intranet-app-base::admin-ai-settings
                ai-text-provider-override="aiTextProviderOverride"
                ai-text-model-override="aiTextModelOverride"
                ai-image-provider-override="aiImageProviderOverride"
                ai-image-model-override="aiImageModelOverride"
                ai-image-edit-provider-override="aiImageEditProviderOverride"
                ai-image-edit-model-override="aiImageEditModelOverride"
                :image-provider="$aiImageProviderOverride"
                :image-edit-provider="$aiImageEditProviderOverride"
                ai-classification-provider-override="aiClassificationProviderOverride"
                ai-classification-model-override="aiClassificationModelOverride"
            />

            <flux:text class="mt-4 text-sm text-slate-600 dark:text-white/70">
                Aktuell wirksam für Assets:
                Text <strong>{{ $this->effectiveAiTextSummary }}</strong>,
                Bilder <strong>{{ $this->effectiveAiImageSummary }}</strong>,
                Klassifikation <strong>{{ $this->effectiveAiClassificationSummary }}</strong>,
                Vision (Gateway) <strong>{{ $this->effectiveAiVisionSummary }}</strong>.
            </flux:text>
        </div>

        <flux:separator />

        <div>
            <flux:heading size="sm" class="mb-3">Assets-KI-Chat</flux:heading>
            <flux:text class="mb-4 text-sm text-slate-600 dark:text-white/70">
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
</section>
