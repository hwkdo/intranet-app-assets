<?php

namespace Hwkdo\IntranetAppAssets\Mcp\Tools;

use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Hwkdo\IntranetAppAssets\Services\LegacyOrderNumberValidationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
class BestellungPruefenTool extends Tool
{
    protected string $name = 'bestellung_pruefen';

    protected string $description = 'Prüft eine Bestellnummer (BEN) auf Format und Existenz über den konfigurierten OrderNumberValidationService.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $bestellnummer = trim((string) $request->get('bestellnummer', ''));
        if ($bestellnummer === '') {
            return Response::error('Das Feld "bestellnummer" ist erforderlich.');
        }

        Log::info('bestellung_pruefen called', ['bestellnummer' => $bestellnummer]);

        /** @var OrderNumberValidationServiceInterface $service */
        $service = app(OrderNumberValidationServiceInterface::class);
        $error = $service->getValidationError($bestellnummer);

        $formatDescription = class_exists(LegacyOrderNumberValidationService::class)
            ? LegacyOrderNumberValidationService::getFormatDescription()
            : null;

        $formatValid = null;
        $exists = null;
        $valid = $error === null;

        if ($valid) {
            $formatValid = true;
            $exists = true;
        } elseif (str_contains(mb_strtolower($error), 'format')) {
            $formatValid = false;
            $exists = false;
        } elseif (str_contains(mb_strtolower($error), 'existiert nicht')) {
            $formatValid = true;
            $exists = false;
        }

        return Response::structured([
            'bestellnummer' => $bestellnummer,
            'valid' => $valid,
            'format_valid' => $formatValid,
            'exists' => $exists,
            'format_description' => $formatDescription,
            'fehler' => $error,
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'bestellnummer' => $schema->string()
                ->description('Zu prüfende Bestellnummer (BEN).')
                ->required(),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'bestellnummer' => $schema->string()
                ->description('Geprüfte Bestellnummer.')
                ->required(),
            'valid' => $schema->boolean()
                ->description('True, wenn die Bestellnummer vollständig gültig ist.')
                ->required(),
            'format_valid' => $schema->boolean()
                ->description('True/false je nach Formatprüfung, null wenn nicht eindeutig ableitbar.')
                ->nullable(),
            'exists' => $schema->boolean()
                ->description('True/false je nach Existenzprüfung, null wenn nicht eindeutig ableitbar.')
                ->nullable(),
            'format_description' => $schema->string()
                ->description('Hinweis auf das erwartete Format, sofern verfügbar.')
                ->nullable(),
            'fehler' => $schema->string()
                ->description('Fehlermeldung vom Validierungsservice oder null.')
                ->nullable(),
        ];
    }
}
