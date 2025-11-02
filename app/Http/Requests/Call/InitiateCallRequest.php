<?php

namespace App\Http\Requests\Call;

use App\Http\Requests\BaseFormRequest;

class InitiateCallRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'destination' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[1-9]\d{1,14}$/'
            ],
            'caller_id' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[1-9]\d{1,14}$/'
            ],
            'max_duration' => [
                'nullable',
                'integer',
                'min:1',
                'max:7200' // 2 hours max
            ],
            'callback_url' => [
                'nullable',
                'url',
                'max:255',
                'regex:/^https?:\/\//'
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'destination.regex' => 'The destination must be a valid phone number in international format.',
            'caller_id.regex' => 'The caller ID must be a valid phone number in international format.',
            'callback_url.regex' => 'The callback URL must use HTTP or HTTPS protocol.',
        ]);
    }
}