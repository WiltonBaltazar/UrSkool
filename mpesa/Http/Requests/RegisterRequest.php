<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\-\.\']+$/u'],
            'last_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\-\.\']+$/u'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(6)],
            // 'phone_number' => ['required', 'string', 'min:9', 'regex:/^[0-9+\s()-]+$/'],
            'plan_id' => ['required_without:plan_slug', 'exists:plans,id'],
            'plan_slug' => ['required_without:plan_id', 'exists:plans,slug'],
            'mpesa_contact' => [
                'required',
                'string',
                'regex:/^(82|83|84|85|86|87)[0-9]{7}$/',
            ],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First Name is required.',
            'first_name.regex' => 'First Name cannot contain emojis.',
            'last_name.required' => 'Last Name is required.',
            'last_name.regex' => 'Last Name cannot contain emojis.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            // 'phone_number.required' => 'Phone number is required for M-Pesa payment.',
            // 'phone_number.regex' => 'Please enter a valid Mozambican mobile number (e.g., 258841234567 or 841234567)',
            'mpesa_contact.regex' => 'Número de M-Pesa inválido. Use o formato: 843334444',
            'terms_accepted.accepted' => 'Deve aceitar os termos e condições',
        ];
    }
}
