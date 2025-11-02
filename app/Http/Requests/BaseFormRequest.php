<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->sanitizeInput();
    }

    /**
     * Sanitize input data to prevent XSS and other attacks
     */
    protected function sanitizeInput(): void
    {
        $input = $this->all();
        
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace("\0", '', $value);
                
                // Trim whitespace
                $value = trim($value);
                
                // For fields that should not contain HTML, strip tags
                if ($this->shouldStripTags($key)) {
                    $value = strip_tags($value);
                }
                
                // For fields that allow HTML, sanitize it
                if ($this->shouldSanitizeHtml($key)) {
                    $value = $this->sanitizeHtml($value);
                }
                
                $input[$key] = $value;
            }
        }
        
        $this->replace($input);
    }

    /**
     * Determine if a field should have HTML tags stripped
     */
    protected function shouldStripTags(string $field): bool
    {
        $stripTagsFields = [
            'name', 'email', 'phone', 'username', 'password', 'password_confirmation',
            'sip_username', 'destination', 'caller_id', 'currency', 'timezone',
            'gateway_transaction_id', 'payment_method', 'destination_prefix',
            'destination_name', 'device_name'
        ];

        return in_array($field, $stripTagsFields);
    }

    /**
     * Determine if a field should have HTML sanitized (not stripped)
     */
    protected function shouldSanitizeHtml(string $field): bool
    {
        $htmlFields = [
            'description', 'notes', 'message', 'comment', 'content'
        ];

        return in_array($field, $htmlFields);
    }

    /**
     * Sanitize HTML content
     */
    protected function sanitizeHtml(string $html): string
    {
        // Allow only safe HTML tags
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><a><h1><h2><h3><h4><h5><h6>';
        
        // Strip dangerous tags but keep safe ones
        $html = strip_tags($html, $allowedTags);
        
        // Remove javascript: and data: protocols from links
        $html = preg_replace('/javascript:/i', '', $html);
        $html = preg_replace('/data:/i', '', $html);
        
        // Remove on* event handlers
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        
        return $html;
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'unique' => 'The :attribute has already been taken.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'numeric' => 'The :attribute must be a number.',
            'decimal' => 'The :attribute must be a decimal number.',
            'in' => 'The selected :attribute is invalid.',
            'regex' => 'The :attribute format is invalid.',
            'phone.regex' => 'The phone number format is invalid. Use format: +1234567890',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'sip_username' => 'SIP username',
            'sip_password' => 'SIP password',
            'caller_id' => 'caller ID',
            'destination_prefix' => 'destination prefix',
            'destination_name' => 'destination name',
            'rate_per_minute' => 'rate per minute',
            'minimum_duration' => 'minimum duration',
            'billing_increment' => 'billing increment',
            'effective_date' => 'effective date',
            'gateway_transaction_id' => 'gateway transaction ID',
            'payment_method' => 'payment method',
            'device_name' => 'device name',
        ];
    }
}