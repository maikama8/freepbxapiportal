<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CountryRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_code',
        'country_name',
        'country_prefix',
        'did_setup_cost',
        'did_monthly_cost',
        'call_rate_per_minute',
        'sms_rate_per_message',
        'billing_increment',
        'billing_increment_config',
        'billing_rules',
        'minimum_duration',
        'is_active',
        'area_codes',
        'features'
    ];

    protected $casts = [
        'did_setup_cost' => 'decimal:4',
        'did_monthly_cost' => 'decimal:4',
        'call_rate_per_minute' => 'decimal:4',
        'sms_rate_per_message' => 'decimal:4',
        'billing_increment' => 'integer',
        'billing_increment_config' => 'string',
        'billing_rules' => 'json',
        'minimum_duration' => 'integer',
        'is_active' => 'boolean',
        'area_codes' => 'array',
        'features' => 'array'
    ];

    /**
     * Get DID numbers for this country
     */
    public function didNumbers(): HasMany
    {
        return $this->hasMany(DidNumber::class, 'country_code', 'country_code');
    }

    /**
     * Get call rates for this country
     */
    public function callRates(): HasMany
    {
        return $this->hasMany(CallRate::class, 'country_code', 'country_code');
    }

    /**
     * Scope for active countries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate call cost based on duration and billing increment
     */
    public function calculateCallCost(int $durationSeconds): float
    {
        if ($durationSeconds < $this->minimum_duration) {
            $durationSeconds = $this->minimum_duration;
        }

        // Calculate billable duration based on increment
        $increment = (int) $this->billing_increment;
        $billableMinutes = ceil($durationSeconds / $increment) * ($increment / 60);

        return round($billableMinutes * $this->call_rate_per_minute, 4);
    }

    /**
     * Get formatted call rate
     */
    public function getFormattedCallRateAttribute(): string
    {
        return '$' . number_format($this->call_rate_per_minute, 4) . '/min';
    }

    /**
     * Get formatted DID costs
     */
    public function getFormattedDidSetupCostAttribute(): string
    {
        return '$' . number_format($this->did_setup_cost, 2);
    }

    public function getFormattedDidMonthlyCostAttribute(): string
    {
        return '$' . number_format($this->did_monthly_cost, 2);
    }

    /**
     * Get billing increment description
     */
    public function getBillingIncrementDescriptionAttribute(): string
    {
        $increment = (int) $this->billing_increment;
        if ($increment < 60) {
            return $increment . ' second' . ($increment > 1 ? 's' : '');
        } else {
            $minutes = $increment / 60;
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }
    }

    /**
     * Check if country supports specific feature
     */
    public function supportsFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? ['voice']);
    }

    /**
     * Get country by phone number prefix
     */
    public static function getByPhoneNumber(string $phoneNumber): ?self
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Try to match by prefix, starting with longest prefixes first
        return self::active()
            ->orderByRaw('LENGTH(country_prefix) DESC')
            ->get()
            ->first(function ($country) use ($cleanNumber) {
                return str_starts_with($cleanNumber, $country->country_prefix);
            });
    }

    /**
     * Get available area codes for this country
     */
    public function getAvailableAreaCodes(): array
    {
        return $this->area_codes ?? [];
    }

    /**
     * Check if area code is valid for this country
     */
    public function isValidAreaCode(string $areaCode): bool
    {
        $availableAreaCodes = $this->getAvailableAreaCodes();
        return empty($availableAreaCodes) || in_array($areaCode, $availableAreaCodes);
    }
}