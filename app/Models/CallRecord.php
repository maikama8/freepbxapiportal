<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecord extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'call_id',
        'caller_id',
        'destination',
        'start_time',
        'end_time',
        'duration',
        'cost',
        'billing_status',
        'billing_details',
        'actual_duration',
        'billable_duration',
        'status',
        'freepbx_response'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
        'cost' => 'decimal:4',
        'billing_details' => 'json',
        'actual_duration' => 'integer',
        'billable_duration' => 'integer',
        'freepbx_response' => 'json'
    ];

    /**
     * Get the user that owns the call record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate call duration in seconds
     */
    public function getDurationInSeconds(): int
    {
        // Prefer the duration field if it's set
        if ($this->duration) {
            return (int) $this->duration;
        }
        
        // Fall back to calculating from timestamps
        if ($this->start_time && $this->end_time) {
            return $this->end_time->diffInSeconds($this->start_time);
        }

        return 0;
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDuration(): string
    {
        $seconds = $this->getDurationInSeconds();
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Check if call is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['initiated', 'ringing', 'answered', 'in_progress']);
    }

    /**
     * Check if call is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'busy', 'no_answer']);
    }
}
