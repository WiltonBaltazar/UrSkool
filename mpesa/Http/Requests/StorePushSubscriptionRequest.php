<?php

namespace App\Http\Requests;

use App\Support\WebPushEndpointValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePushSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'endpoint' => [
                'required',
                'string',
                'max:4000',
                'url',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! WebPushEndpointValidator::isAllowed($value)) {
                        $fail('The endpoint must use a trusted HTTPS web push provider host.');
                    }
                },
            ],
            'expirationTime' => ['nullable', 'integer', 'min:0'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:1024'],
            'keys.auth' => ['required', 'string', 'max:1024'],
            'contentEncoding' => [
                'nullable',
                'string',
                Rule::in(['aes128gcm', 'aesgcm']),
            ],
        ];
    }
}
