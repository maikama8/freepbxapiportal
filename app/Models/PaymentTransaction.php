<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'gateway',
        'gateway_transaction_id',
        'status',
        'payment_method',
        'payment_url',
        'expires_at',
        'metadata',
        'completed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'metadata' => 'json',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the payment transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled', 'expired']);
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $status = 'failed'): void
    {
        $this->update(['status' => $status]);
    }
}
