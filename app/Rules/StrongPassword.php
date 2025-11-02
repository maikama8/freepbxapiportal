<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    private int $minLength;
    private bool $requireUppercase;
    private bool $requireLowercase;
    private bool $requireNumbers;
    private bool $requireSpecialChars;
    private array $commonPasswords;

    public function __construct(
        int $minLength = 8,
        bool $requireUppercase = true,
        bool $requireLowercase = true,
        bool $requireNumbers = true,
        bool $requireSpecialChars = true
    ) {
        $this->minLength = $minLength;
        $this->requireUppercase = $requireUppercase;
        $this->requireLowercase = $requireLowercase;
        $this->requireNumbers = $requireNumbers;
        $this->requireSpecialChars = $requireSpecialChars;
        
        // Common passwords to reject
        $this->commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            '1234567890', 'password1', '123123', 'admin123'
        ];
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

        // Check minimum length
        if (strlen($value) < $this->minLength) {
            $fail("The :attribute must be at least {$this->minLength} characters long.");
            return;
        }

        // Check for common passwords
        if (in_array(strtolower($value), $this->commonPasswords)) {
            $fail('The :attribute is too common. Please choose a more secure password.');
            return;
        }

        // Check for repeated characters (more than 3 in a row)
        if (preg_match('/(.)\1{3,}/', $value)) {
            $fail('The :attribute cannot contain more than 3 repeated characters in a row.');
            return;
        }

        // Check for sequential characters
        if ($this->hasSequentialChars($value)) {
            $fail('The :attribute cannot contain sequential characters (e.g., 123, abc).');
            return;
        }

        $requirements = [];
        $missing = [];

        // Check uppercase requirement
        if ($this->requireUppercase) {
            $requirements[] = 'uppercase letter';
            if (!preg_match('/[A-Z]/', $value)) {
                $missing[] = 'uppercase letter';
            }
        }

        // Check lowercase requirement
        if ($this->requireLowercase) {
            $requirements[] = 'lowercase letter';
            if (!preg_match('/[a-z]/', $value)) {
                $missing[] = 'lowercase letter';
            }
        }

        // Check numbers requirement
        if ($this->requireNumbers) {
            $requirements[] = 'number';
            if (!preg_match('/\d/', $value)) {
                $missing[] = 'number';
            }
        }

        // Check special characters requirement
        if ($this->requireSpecialChars) {
            $requirements[] = 'special character';
            if (!preg_match('/[@$!%*?&]/', $value)) {
                $missing[] = 'special character (@$!%*?&)';
            }
        }

        if (!empty($missing)) {
            $requirementsList = implode(', ', $requirements);
            $missingList = implode(', ', $missing);
            $fail("The :attribute must contain at least one {$missingList}. Required: {$requirementsList}.");
        }
    }

    /**
     * Check for sequential characters
     */
    private function hasSequentialChars(string $password): bool
    {
        $sequences = [
            '0123456789',
            'abcdefghijklmnopqrstuvwxyz',
            'qwertyuiopasdfghjklzxcvbnm'
        ];

        foreach ($sequences as $sequence) {
            for ($i = 0; $i <= strlen($sequence) - 4; $i++) {
                $substr = substr($sequence, $i, 4);
                if (stripos($password, $substr) !== false) {
                    return true;
                }
                // Check reverse sequence
                if (stripos($password, strrev($substr)) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}