<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class BulkUpdateDidPricesRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'filter_country' => 'nullable|string|exists:country_rates,country_code',
            'filter_status' => 'nullable|in:available,assigned,suspended,expired',
            'filter_area_code' => 'nullable|string|max:10',
            'update_monthly_cost' => 'nullable|boolean',
            'update_setup_cost' => 'nullable|boolean',
            'update_type' => 'required|in:set,increase,decrease',
            'update_amount' => 'required|numeric|min:0'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'update_type.required' => 'Please select an update type.',
            'update_type.in' => 'Invalid update type selected.',
            'update_amount.required' => 'Please enter an update amount.',
            'update_amount.numeric' => 'The update amount must be a number.',
            'update_amount.min' => 'The update amount must be at least 0.',
            'filter_country.exists' => 'The selected country is invalid.',
            'filter_status.in' => 'Invalid status filter selected.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one cost type is selected for update
            if (!$this->input('update_monthly_cost') && !$this->input('update_setup_cost')) {
                $validator->errors()->add('update_type', 'Please select at least one cost type to update (monthly or setup cost).');
            }
        });
    }
}