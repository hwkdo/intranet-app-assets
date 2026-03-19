<?php

namespace Hwkdo\IntranetAppAssets\Rules;

use Closure;
use Hwkdo\IntranetAppAssets\Services\D3InvoiceValidationService;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidD3InvoiceNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $service = app(D3InvoiceValidationService::class);
        $error = $service->getValidationError(trim((string) $value));
        if ($error !== null) {
            $fail($error);
        }
    }
}
