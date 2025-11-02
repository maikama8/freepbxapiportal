<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    private bool $requireCountryCode;
    private array $allowedCountryCodes;

    public function __construct(bool $requireCountryCode = true, array $allowedCountryCodes = [])
    {
        $this->requireCountryCode = $requireCountryCode;
        $this->allowedCountryCodes = $allowedCountryCodes;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        // Remove all non-digit characters except +
        $cleanNumber = preg_replace('/[^\d+]/', '', $value);

        // Check if it starts with +
        if ($this->requireCountryCode && !str_starts_with($cleanNumber, '+')) {
            $fail('The :attribute must include a country code (e.g., +1234567890).');
            return;
        }

        // Remove + for further validation
        $digitsOnly = ltrim($cleanNumber, '+');

        // Check length (minimum 7 digits, maximum 15 digits as per ITU-T E.164)
        if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
            $fail('The :attribute must be between 7 and 15 digits long.');
            return;
        }

        // Check if it contains only digits
        if (!ctype_digit($digitsOnly)) {
            $fail('The :attribute must contain only digits and an optional country code.');
            return;
        }

        // Check if first digit is not 0 (international format)
        if ($this->requireCountryCode && $digitsOnly[0] === '0') {
            $fail('The :attribute cannot start with 0 when using international format.');
            return;
        }

        // Check allowed country codes if specified
        if (!empty($this->allowedCountryCodes) && $this->requireCountryCode) {
            $countryCodeFound = false;
            foreach ($this->allowedCountryCodes as $code) {
                if (str_starts_with($digitsOnly, $code)) {
                    $countryCodeFound = true;
                    break;
                }
            }
            
            if (!$countryCodeFound) {
                $allowedCodes = implode(', ', $this->allowedCountryCodes);
                $fail("The :attribute must start with one of the allowed country codes: {$allowedCodes}.");
            }
        }

        // Additional validation for suspicious patterns
        if ($this->hasSuspiciousPattern($digitsOnly)) {
            $fail('The :attribute appears to have an invalid pattern.');
        }
    }

    /**
     * Check for suspicious patterns in phone numbers
     */
    private function hasSuspiciousPattern(string $number): bool
    {
        // Check for all same digits
        if (preg_match('/^(\d)\1+$/', $number)) {
            return true;
        }

        // Check for simple sequential patterns
        if (preg_match('/^(0123456789|1234567890|9876543210)/', $number)) {
            return true;
        }

        // Check for alternating patterns
        if (preg_match('/^(\d)(\d)\1\2\1\2/', $number)) {
            return true;
        }

        return false;
    }
}