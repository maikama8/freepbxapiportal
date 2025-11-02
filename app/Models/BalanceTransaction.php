<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'created_by'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4'
    ];

    /**
     * Get the user that owns the transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created the transaction
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reference model (polymorphic)
     */
    public function reference()
    {
        if ($this->reference_type && $this->reference_id) {
            $modelClass = 'App\\Models\\' . str_replace('_', '', ucwords($this->reference_type, '_'));
            if (class_exists($modelClass)) {
                return $modelClass::find($this->reference_id);
            }
        }
        return null;
    }

    /**
     * Check if transaction is a credit
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    /**
     * Check if transaction is a debit
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Get formatted amount with sign
     */
    public function getFormattedAmount(): string
    {
        $sign = $this->isCredit() ? '+' : '-';
        return $sign . number_format($this->amount, 4);
    }
}
