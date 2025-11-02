<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateRateRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['admin', 'operator']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'destination_prefix' => [
                'required',
                'string',
                'max:10',
                'regex:/^[0-9+]+$/'
            ],
            'destination_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z\s\-\'\.()]+$/'
            ],
            'rate_per_minute' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^\d+(\.\d{1,6})?$/'
            ],
            'minimum_duration' => [
                'required',
                'integer',
                'min:1',
                'max:3600'
            ],
            'billing_increment' => [
                'required',
                'integer',
                'min:1',
                'max:60'
            ],
            'effective_date' => [
                'nullable',
                'date',
                'after_or_equal:today'
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'destination_prefix.regex' => 'The destination prefix may only contain numbers and plus signs.',
            'destination_name.regex' => 'The destination name may only contain letters, spaces, hyphens, apostrophes, periods, and parentheses.',
            'rate_per_minute.regex' => 'The rate per minute must be a valid decimal number with up to 6 decimal places.',
            'effective_date.after_or_equal' => 'The effective date must be today or a future date.',
        ]);
    }
}