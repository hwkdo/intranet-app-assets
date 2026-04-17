<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Enums;

enum D3InvoiceVisionLlmProvider: string
{
    case OpenWebUi = 'openwebui';
    case Langdock = 'langdock';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::OpenWebUi->value => 'Open Web UI',
            self::Langdock->value => 'Langdock',
        ];
    }
}
