<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Services;

use Hwkdo\IntranetAppAssets\Contracts\D3InvoiceVisionLlmClientInterface;
use Hwkdo\IntranetAppBase\Contracts\IntranetAiGatewayInterface;
use Hwkdo\IntranetAppBase\Data\AiRequestContext;
use Hwkdo\IntranetAppBase\Enums\AiCapability;

class LangdockD3InvoiceVisionLlmClient implements D3InvoiceVisionLlmClientInterface
{
    public function __construct(
        private readonly IntranetAiGatewayInterface $gateway,
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
        $result = $this->gateway->chat(
            $messages,
            new AiRequestContext(
                appIdentifier: 'assets',
                capability: AiCapability::Vision,
            ),
        );

        return json_decode((string) $result->rawJson, true) ?? [
            'choices' => [
                ['message' => ['content' => $result->content]],
            ],
        ];
    }

    public function chatCompletionWithImageFile(
        string $model,
        string $userTextPrompt,
        string $absoluteImagePath,
        int $requestTimeoutSeconds,
        int $connectTimeoutSeconds,
        ?string $bearerOverride = null,
    ): array {
        return $this->gateway->chatCompletionWithImageFromPath(
            $absoluteImagePath,
            $userTextPrompt,
            new AiRequestContext(
                appIdentifier: 'assets',
                capability: AiCapability::Vision,
            ),
            $requestTimeoutSeconds,
            $connectTimeoutSeconds,
        );
    }
}
