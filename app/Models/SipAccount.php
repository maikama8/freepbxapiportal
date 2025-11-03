<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SipAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sip_username',
        'sip_password',
        'sip_context',
        'display_name',
        'status',
        'is_primary',
        'codec_preferences',
        'call_forward_enabled',
        'call_forward_number',
        'voicemail_enabled',
        'voicemail_email',
        'freepbx_extension_id',
        'freepbx_settings',
        'freepbx_sync_status',
        'freepbx_last_sync_at',
        'freepbx_extension_data',
        'sync_retry_count',
        'sync_last_error',
        'sync_last_attempt_at',
        'last_registered_at',
        'last_registered_ip',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'call_forward_enabled' => 'boolean',
        'voicemail_enabled' => 'boolean',
        'freepbx_settings' => 'array',
        'freepbx_extension_data' => 'array',
        'freepbx_last_sync_at' => 'datetime',
        'sync_last_attempt_at' => 'datetime',
        'last_registered_at' => 'datetime',
    ];

    /**
     * Get the user that owns the SIP account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the SIP server configuration
     */
    public function getSipServerAttribute(): string
    {
        return config('voip.freepbx.sip.domain', 'localhost');
    }

    /**
     * Get the SIP port
     */
    public function getSipPortAttribute(): int
    {
        return config('voip.freepbx.sip.port', 5060);
    }

    /**
     * Get full SIP URI
     */
    public function getSipUriAttribute(): string
    {
        return "sip:{$this->sip_username}@{$this->sip_server}:{$this->sip_port}";
    }

    /**
     * Generate a secure SIP password
     */
    public static function generateSipPassword(): string
    {
        return 'sip_' . bin2hex(random_bytes(8));
    }

    /**
     * Get the next available SIP username (extension number)
     */
    public static function getNextSipUsername(): string
    {
        return self::getNextAvailableExtension();
    }

    /**
     * Get the next available extension number
     */
    public static function getNextAvailableExtension(): string
    {
        $startRange = config('voip.extension_start_range', 2000);
        $endRange = config('voip.extension_end_range', 9999);
        
        // Get all numeric extensions in the configured range
        $extensions = self::select('sip_username')
            ->get()
            ->filter(function ($account) use ($startRange, $endRange) {
                $username = $account->sip_username;
                return is_numeric($username) && 
                       (int) $username >= $startRange && 
                       (int) $username <= $endRange;
            })
            ->pluck('sip_username')
            ->map(function ($username) {
                return (int) $username;
            })
            ->sort()
            ->values();

        if ($extensions->isEmpty()) {
            return (string) $startRange;
        }

        // Find the next available extension
        $lastExtension = $extensions->last();
        $nextExtension = $lastExtension + 1;

        if ($nextExtension > $endRange) {
            // Look for gaps in the sequence
            for ($i = $startRange; $i <= $endRange; $i++) {
                if (!$extensions->contains($i)) {
                    return (string) $i;
                }
            }
            throw new \Exception('No available extensions in the configured range');
        }

        return (string) $nextExtension;
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for primary accounts
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}