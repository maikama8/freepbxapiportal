<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DidNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'did_number',
        'country_code',
        'area_code',
        'provider',
        'monthly_cost',
        'setup_cost',
        'status',
        'user_id',
        'assigned_extension',
        'assigned_at',
        'expires_at',
        'features',
        'metadata',
        'billing_history',
        'last_billed_at',
        'suspension_reason',
        'suspended_at',
        'suspension_details'
    ];

    protected $casts = [
        'monthly_cost' => 'decimal:4',
        'setup_cost' => 'decimal:4',
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_billed_at' => 'datetime',
        'suspended_at' => 'datetime',
        'features' => 'array',
        'metadata' => 'array',
        'billing_history' => 'array',
        'suspension_details' => 'array'
    ];

    /**
     * Get the user that owns this DID number
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the country rate for this DID number
     */
    public function countryRate(): BelongsTo
    {
        return $this->belongsTo(CountryRate::class, 'country_code', 'country_code');
    }

    /**
     * Scope for available DID numbers
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope for active DID numbers
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope by country
     */
    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope by area code
     */
    public function scopeByAreaCode($query, string $areaCode)
    {
        return $query->where('area_code', $areaCode);
    }

    /**
     * Check if DID has specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get formatted DID number
     */
    public function getFormattedNumberAttribute(): string
    {
        $number = $this->did_number;
        
        // Format based on country
        switch ($this->country_code) {
            case 'US':
            case 'CA':
                // Format as +1 (XXX) XXX-XXXX
                if (strlen($number) === 11 && str_starts_with($number, '1')) {
                    return '+1 (' . substr($number, 1, 3) . ') ' . 
                           substr($number, 4, 3) . '-' . substr($number, 7, 4);
                }
                break;
            case 'GB':
                // Format as +44 XXXX XXXXXX
                if (str_starts_with($number, '44')) {
                    return '+44 ' . substr($number, 2, 4) . ' ' . substr($number, 6);
                }
                break;
            default:
                // Default international format
                return '+' . $number;
        }
        
        return $number;
    }

    /**
     * Check if DID is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if DID is expiring soon (within 30 days)
     */
    public function isExpiringSoon(): bool
    {
        return $this->expires_at && 
               $this->expires_at->isFuture() && 
               $this->expires_at->diffInDays() <= 30;
    }

    /**
     * Assign DID to user
     */
    public function assignToUser(User $user, ?string $extension = null): bool
    {
        if ($this->status !== 'available') {
            return false;
        }

        $this->update([
            'user_id' => $user->id,
            'assigned_extension' => $extension,
            'assigned_at' => now(),
            'status' => 'active'
        ]);

        return true;
    }

    /**
     * Release DID from user
     */
    public function release(): bool
    {
        $this->update([
            'user_id' => null,
            'assigned_extension' => null,
            'assigned_at' => null,
            'status' => 'available'
        ]);

        return true;
    }

    /**
     * Get monthly cost with currency formatting
     */
    public function getFormattedMonthlyCostAttribute(): string
    {
        return '$' . number_format($this->monthly_cost, 2);
    }

    /**
     * Get setup cost with currency formatting
     */
    public function getFormattedSetupCostAttribute(): string
    {
        return '$' . number_format($this->setup_cost, 2);
    }
}