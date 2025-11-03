<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DidTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'did_number_id',
        'from_user_id',
        'to_user_id',
        'transfer_code',
        'transfer_data',
        'status',
        'expires_at',
        'completed_at'
    ];

    protected $casts = [
        'transfer_data' => 'array',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    /**
     * Get the DID number being transferred
     */
    public function didNumber(): BelongsTo
    {
        return $this->belongsTo(DidNumber::class);
    }

    /**
     * Get the user transferring the DID
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * Get the user receiving the DID
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Check if transfer code is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if transfer is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Mark transfer as completed
     */
    public function markCompleted(User $recipient): void
    {
        $this->update([
            'to_user_id' => $recipient->id,
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    /**
     * Mark transfer as expired
     */
    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}