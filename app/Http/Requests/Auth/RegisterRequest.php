<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class RegisterRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $passwordMinLength = config('voip.security.password.min_length', 8);
        
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\-\'\.]+$/'
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
                'unique:users,email',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[1-9]\d{1,14}$/'
            ],
            'password' => [
                'required',
                'string',
                'min:' . $passwordMinLength,
                'max:255',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
            ],
            'password_confirmation' => [
                'required',
                'string',
                'min:' . $passwordMinLength,
                'max:255'
            ],
            'account_type' => [
                'required',
                'string',
                'in:prepaid,postpaid'
            ],
            'timezone' => [
                'nullable',
                'string',
                'max:50',
                'in:' . implode(',', timezone_identifiers_list())
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/'
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_\.]+$/'
            ],
            'terms' => [
                'required',
                'accepted'
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'name.regex' => 'The name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'email.regex' => 'The email format is invalid.',
            'phone.regex' => 'The phone number must be in international format (e.g., +1234567890).',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'currency.regex' => 'The currency must be a valid 3-letter ISO code (e.g., USD).',
            'device_name.regex' => 'The device name contains invalid characters.',
            'terms.required' => 'You must accept the terms of service to register.',
            'terms.accepted' => 'You must accept the terms of service to register.',
        ]);
    }
}