<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAssets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterFidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'exists:users,username'],
            'app-id' => ['required', 'string', Rule::in([config('intranet-app-assets.fido_register_app_id')])],
            'sn' => ['nullable', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function serialNumber(): string
    {
        $sn = $this->input('sn');

        return is_string($sn) && trim($sn) !== '' ? trim($sn) : '?';
    }

    public function pinForNote(): string
    {
        $pin = $this->input('pin');

        return is_string($pin) ? $pin : '';
    }
}
