<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Contracts\D3InvoiceVisionLlmClientInterface;
use Hwkdo\IntranetAppAssets\Contracts\LangdockOpenAiChatPort;

class LangdockD3InvoiceVisionLlmClient implements D3InvoiceVisionLlmClientInterface
{
    public function __construct(
        private LangdockOpenAiChatPort $langdockOpenAiChat,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    public function chatCompletionWithMessages(
        string $model,
        array $messages,
        int $requestTimeoutSeconds,
        int $connectTimeoutSeconds,
        ?string $bearerOverride = null,
    ): array {
        return $this->langdockOpenAiChat->createChatCompletion(
            $model,
            $messages,
            $requestTimeoutSeconds,
            $connectTimeoutSeconds,
            null,
            $bearerOverride,
        );
    }

    public function chatCompletionWithImageFile(
        string $model,
        string $userTextPrompt,
        string $absoluteImagePath,
        int $requestTimeoutSeconds,
        int $connectTimeoutSeconds,
        ?string $bearerOverride = null,
    ): array {
        return $this->langdockOpenAiChat->createChatCompletionWithImageFromPath(
            $model,
            $userTextPrompt,
            $absoluteImagePath,
            $requestTimeoutSeconds,
            $connectTimeoutSeconds,
            null,
            $bearerOverride,
        );
    }
}
