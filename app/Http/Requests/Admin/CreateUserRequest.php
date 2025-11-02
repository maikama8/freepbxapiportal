<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class CreateUserRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

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
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
            'role' => [
                'required',
                'string',
                'in:admin,operator,customer'
            ],
            'account_type' => [
                'required',
                'string',
                'in:prepaid,postpaid'
            ],
            'balance' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100000',
                'regex:/^\d+(\.\d{1,4})?$/'
            ],
            'credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100000',
                'regex:/^\d+(\.\d{1,4})?$/'
            ],
            'status' => [
                'required',
                'string',
                'in:active,inactive,suspended'
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
            'balance.regex' => 'The balance must be a valid decimal number with up to 4 decimal places.',
            'credit_limit.regex' => 'The credit limit must be a valid decimal number with up to 4 decimal places.',
            'currency.regex' => 'The currency must be a valid 3-letter ISO code (e.g., USD).',
        ]);
    }
}