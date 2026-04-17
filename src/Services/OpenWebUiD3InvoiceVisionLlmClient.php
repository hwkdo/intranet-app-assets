<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Contracts\D3InvoiceVisionLlmClientInterface;
use Hwkdo\OpenwebuiApiLaravel\Services\OpenWebUiRagService;

class OpenWebUiD3InvoiceVisionLlmClient implements D3InvoiceVisionLlmClientInterface
{
    public function __construct(
        private OpenWebUiRagService $openWebUiRag,
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
        return $this->openWebUiRag->postVisionChatCompletion(
            $model,
            $messages,
            $bearerOverride,
            [],
            $requestTimeoutSeconds,
            $connectTimeoutSeconds,
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
        return $this->openWebUiRag->chatWithImageFilePath(
            $model,
            $userTextPrompt,
            $absoluteImagePath,
            $bearerOverride,
            [],
            $requestTimeoutSeconds,
            $connectTimeoutSeconds,
        );
    }
}
