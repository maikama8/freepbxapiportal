<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class BulkUploadDidRequest extends BaseFormRequest
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
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'country_code' => 'required|string|exists:country_rates,country_code'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'Please select a CSV file to upload.',
            'csv_file.mimes' => 'The file must be a CSV file.',
            'csv_file.max' => 'The file size must not exceed 10MB.',
            'country_code.required' => 'Please select a default country.',
            'country_code.exists' => 'The selected country is invalid.'
        ];
    }
}