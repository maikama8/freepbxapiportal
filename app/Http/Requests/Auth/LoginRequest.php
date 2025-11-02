<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            ],
            'password' => [
                'required',
                'string',
                'min:6',
                'max:255'
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_\.]+$/'
            ],
            'remember' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.regex' => 'The email format is invalid.',
            'device_name.regex' => 'The device name contains invalid characters.',
        ]);
    }
}