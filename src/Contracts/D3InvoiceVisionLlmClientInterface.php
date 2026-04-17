<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Contracts;

/**
 * LLM-Zugriff für D3-Rechnungsvision (Open-WebUI- oder Langdock-Pfad).
 */
interface D3InvoiceVisionLlmClientInterface
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    public function chatCompletionWithMessages(
        string $model,
        array $messages,
        int $requestTimeoutSeconds,
        int $connectTimeoutSeconds,
        ?string $bearerOverride = null,
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function chatCompletionWithImageFile(
        string $model,
        string $userTextPrompt,
        string $absoluteImagePath,
        int $requestTimeoutSeconds,
        int $connectTimeoutSeconds,
        ?string $bearerOverride = null,
    ): array;
}
