<?php

namespace Hwkdo\IntranetAppAssets\Rules;

use Closure;
use Hwkdo\IntranetAppAssets\Contracts\OrderNumberValidationServiceInterface;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidOrderNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $service = app(OrderNumberValidationServiceInterface::class);
        $error = $service->getValidationError(trim((string) $value));
        if ($error !== null) {
            $fail($error);
        }
    }
}
