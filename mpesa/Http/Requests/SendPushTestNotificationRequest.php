<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendPushTestNotificationRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:2048'],
            'badge' => ['nullable', 'string', 'max:2048'],
            'tag' => ['nullable', 'string', 'max:120'],
        ];
    }
}
