<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CallRate extends Model
{
    protected $fillable = [
        'destination_prefix',
        'destination_name',
        'rate_per_minute',
        'minimum_duration',
        'billing_increment',
        'effective_date',
        'is_active'
    ];

    protected $casts = [
        'rate_per_minute' => 'decimal:6',
        'minimum_duration' => 'integer',
        'billing_increment' => 'integer',
        'effective_date' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Find the best matching rate for a destination
     */
    public static function findRateForDestination(string $destination): ?self
    {
        // Remove any non-numeric characters and get the number
        $cleanDestination = preg_replace('/[^0-9]/', '', $destination);
        
        if (empty($cleanDestination)) {
            return null;
        }

        // Find the longest matching prefix
        $rate = null;
        $maxPrefixLength = 0;

        $activeRates = self::where('is_active', true)
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->get();

        foreach ($activeRates as $candidateRate) {
            $prefix = $candidateRate->destination_prefix;
            $prefixLength = strlen($prefix);
            
            if ($prefixLength > $maxPrefixLength && str_starts_with($cleanDestination, $prefix)) {
                $rate = $candidateRate;
                $maxPrefixLength = $prefixLength;
            }
        }

        return $rate;
    }

    /**
     * Calculate cost for a call duration
     */
    public function calculateCost(int $durationSeconds): float
    {
        // Apply minimum duration
        $billableDuration = max($durationSeconds, $this->minimum_duration);
        
        // Round up to billing increment
        $billingIncrement = $this->billing_increment;
        $billableDuration = ceil($billableDuration / $billingIncrement) * $billingIncrement;
        
        // Calculate cost (rate is per minute, so convert seconds to minutes)
        $billableMinutes = $billableDuration / 60;
        
        return round($billableMinutes * $this->rate_per_minute, 4);
    }

    /**
     * Get formatted rate per minute
     */
    public function getFormattedRate(): string
    {
        return number_format($this->rate_per_minute, 6);
    }

    /**
     * Scope for active rates
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
                    ->where('effective_date', '<=', now());
    }

    /**
     * Scope for specific destination prefix
     */
    public function scopeForPrefix(Builder $query, string $prefix): Builder
    {
        return $query->where('destination_prefix', $prefix);
    }
}
