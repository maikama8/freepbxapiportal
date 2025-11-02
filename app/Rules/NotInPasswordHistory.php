<?php

namespace App\Rules;

use App\Models\PasswordHistory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotInPasswordHistory implements ValidationRule
{
    private int $userId;
    private int $historyCount;

    public function __construct(int $userId, int $historyCount = 5)
    {
        $this->userId = $userId;
        $this->historyCount = $historyCount;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        if (PasswordHistory::wasPasswordUsedRecently($this->userId, $value, $this->historyCount)) {
            $fail("The :attribute cannot be one of your last {$this->historyCount} passwords.");
        }
    }
}