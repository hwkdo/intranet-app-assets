<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Contracts;

/**
 * OpenAI-kompatible Chat Completions gegen Langdock (EU), für Wiederverwendung außerhalb des D3-Vision-Flows.
 */
interface LangdockOpenAiChatPort
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    public function createChatCompletion(
        string $model,
        array $messages,
        int $requestTimeoutSeconds,
        int $connectTimeoutSeconds,
        ?int $maxOutputTokens = null,
        ?string $apiKeyOverride = null,
        array $extraPayload = [],
    ): array;

    /**
     * Eine User-Nachricht mit Text + einem Bild (Datei → data-URL).
     *
     * @return array<string, mixed>
     */
    public function createChatCompletionWithImageFromPath(
        string $model,
        string $userText,
        string $absoluteImagePath,
        int $requestTimeoutSeconds,
        int $connectTimeoutSeconds,
        ?int $maxOutputTokens = null,
        ?string $apiKeyOverride = null,
    ): array;
}
