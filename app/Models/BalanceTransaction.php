<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'description',
        'reference_id',
        'reference_type',
        'metadata',
        'balance_before',
        'balance_after',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'processed_at' => 'datetime',
        'metadata' => 'json',
    ];

    /**
     * Get the user that owns this transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for specific transaction types
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for positive amounts (credits)
     */
    public function scopeCredits($query)
    {
        return $query->where('amount', '>', 0);
    }

    /**
     * Scope for negative amounts (debits)
     */
    public function scopeDebits($query)
    {
        return $query->where('amount', '<', 0);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('processed_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute(): string
    {
        $prefix = $this->amount >= 0 ? '+' : '';
        return $prefix . '$' . number_format($this->amount, 2);
    }

    /**
     * Get formatted balance before
     */
    public function getFormattedBalanceBeforeAttribute(): string
    {
        return '$' . number_format($this->balance_before, 2);
    }

    /**
     * Get formatted balance after
     */
    public function getFormattedBalanceAfterAttribute(): string
    {
        return '$' . number_format($this->balance_after, 2);
    }

    /**
     * Check if transaction is a credit
     */
    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if transaction is a debit
     */
    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get transaction type description
     */
    public function getTypeDescriptionAttribute(): string
    {
        return match($this->type) {
            'payment' => 'Payment',
            'call_charge' => 'Call Charge',
            'did_setup' => 'DID Setup Fee',
            'did_monthly' => 'DID Monthly Fee',
            'refund' => 'Refund',
            'adjustment' => 'Balance Adjustment',
            'bonus' => 'Bonus Credit',
            'penalty' => 'Penalty Charge',
            default => ucfirst(str_replace('_', ' ', $this->type))
        };
    }

    /**
     * Create a new balance transaction
     */
    public static function createTransaction(
        int $userId,
        float $amount,
        string $type,
        string $description,
        ?string $referenceId = null,
        ?string $referenceType = null,
        ?array $metadata = null
    ): self {
        $user = User::findOrFail($userId);
        $balanceBefore = $user->balance;
        $balanceAfter = $balanceBefore + $amount;

        // Update user balance
        $user->update(['balance' => $balanceAfter]);

        // Create transaction record
        return self::create([
            'user_id' => $userId,
            'amount' => $amount,
            'type' => $type,
            'description' => $description,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'metadata' => $metadata,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'processed_at' => now()
        ]);
    }
}