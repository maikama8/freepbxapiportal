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
        'last_registered_at',
        'last_registered_ip',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'call_forward_enabled' => 'boolean',
        'voicemail_enabled' => 'boolean',
        'freepbx_settings' => 'array',
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
     * Get the next available SIP username
     */
    public static function getNextSipUsername(): string
    {
        $startRange = config('voip.freepbx.extensions.start_range', 2000);
        $endRange = config('voip.freepbx.extensions.end_range', 9999);
        
        $lastExtension = self::where('sip_username', 'REGEXP', '^[0-9]+$')
            ->orderBy('sip_username', 'desc')
            ->first();
        
        if ($lastExtension) {
            $nextExtension = (int) $lastExtension->sip_username + 1;
        } else {
            $nextExtension = $startRange;
        }
        
        // Ensure we don't exceed the range
        if ($nextExtension > $endRange) {
            throw new \Exception('No available SIP extensions in the configured range');
        }
        
        // Check if extension already exists
        while (self::where('sip_username', (string) $nextExtension)->exists()) {
            $nextExtension++;
            if ($nextExtension > $endRange) {
                throw new \Exception('No available SIP extensions in the configured range');
            }
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