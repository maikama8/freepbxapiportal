<?php

namespace App\Services;

use App\Models\CallRate;
use App\Models\CallRecord;
use App\Models\CountryRate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Exceptions\FreePBXApiException;
use Illuminate\Support\Facades\Log;

class AdvancedBillingService
{
    /**
     * Supported billing increments (initial/subsequent seconds)
     */
    const BILLING_INCREMENTS = [
        '1/1' => ['initial' => 1, 'subsequent' => 1, 'label' => '1 second / 1 second'],
        '6/6' => ['initial' => 6, 'subsequent' => 6, 'label' => '6 seconds / 6 seconds'],
        '30/30' => ['initial' => 30, 'subsequent' => 30, 'label' => '30 seconds / 30 seconds'],
        '60/60' => ['initial' => 60, 'subsequent' => 60, 'label' => '60 seconds / 60 seconds'],
        '1/60' => ['initial' => 1, 'subsequent' => 60, 'label' => '1 second / 60 seconds'],
        '6/60' => ['initial' => 6, 'subsequent' => 60, 'label' => '6 seconds / 60 seconds'],
        '30/60' => ['initial' => 30, 'subsequent' => 60, 'label' => '30 seconds / 60 seconds'],
    ];

    /**
     * Get available billing increments
     */
    public function getAvailableBillingIncrements(): array
    {
        return self::BILLING_INCREMENTS;
    }

    /**
     * Get default billing increment configuration
     */
    public function getDefaultBillingIncrement(): string
    {
        return SystemSetting::get('billing.default_increment', '6/6');
    }

    /**
     * Set default billing increment
     */
    public function setDefaultBillingIncrement(string $increment): void
    {
        if (!array_key_exists($increment, self::BILLING_INCREMENTS)) {
            throw new \InvalidArgumentException("Invalid billing increment: {$increment}");
        }

        SystemSetting::set(
            'billing.default_increment',
            $increment,
            'string',
            'billing',
            'Default Billing Increment',
            'Default billing increment for new rates'
        );
    }

    /**
     * Get billing increment for a specific destination or country
     */
    public function getBillingIncrementForDestination(string $destination): array
    {
        // First try to get from call rate
        $callRate = CallRate::findRateForDestination($destination);
        if ($callRate && $callRate->billing_increment_config) {
            return $this->parseBillingIncrement($callRate->billing_increment_config);
        }

        // Then try country rate
        $countryRate = CountryRate::getByPhoneNumber($destination);
        if ($countryRate && $countryRate->billing_increment) {
            return $this->parseBillingIncrement($countryRate->billing_increment);
        }

        // Fall back to default
        $defaultIncrement = $this->getDefaultBillingIncrement();
        return self::BILLING_INCREMENTS[$defaultIncrement];
    }

    /**
     * Parse billing increment string to configuration array
     */
    protected function parseBillingIncrement(string $increment): array
    {
        if (array_key_exists($increment, self::BILLING_INCREMENTS)) {
            return self::BILLING_INCREMENTS[$increment];
        }

        // Try to parse custom format like "30/60"
        if (preg_match('/^(\d+)\/(\d+)$/', $increment, $matches)) {
            return [
                'initial' => (int) $matches[1],
                'subsequent' => (int) $matches[2],
                'label' => "{$matches[1]} seconds / {$matches[2]} seconds"
            ];
        }

        // Fall back to default
        $defaultIncrement = $this->getDefaultBillingIncrement();
        return self::BILLING_INCREMENTS[$defaultIncrement];
    }

    /**
     * Calculate billable duration using ASTPP-style billing increments
     */
    public function calculateBillableDuration(int $actualDurationSeconds, array $billingConfig, int $minimumDuration = 0): int
    {
        // Apply minimum duration first
        $duration = max($actualDurationSeconds, $minimumDuration);

        if ($duration <= 0) {
            return 0;
        }

        $initialIncrement = $billingConfig['initial'];
        $subsequentIncrement = $billingConfig['subsequent'];

        // If duration is less than or equal to initial increment, bill for initial increment
        if ($duration <= $initialIncrement) {
            return $initialIncrement;
        }

        // Calculate billable duration: initial increment + rounded up subsequent increments
        $remainingDuration = $duration - $initialIncrement;
        $subsequentIncrements = ceil($remainingDuration / $subsequentIncrement);
        
        return $initialIncrement + ($subsequentIncrements * $subsequentIncrement);
    }

    /**
     * Calculate advanced call cost with ASTPP-style billing
     */
    public function calculateAdvancedCallCost(string $destination, int $durationSeconds): array
    {
        // Get rate information
        $rate = CallRate::findRateForDestination($destination);
        if (!$rate) {
            // Try country rate as fallback
            $countryRate = CountryRate::getByPhoneNumber($destination);
            if (!$countryRate) {
                throw new FreePBXApiException("No rate found for destination: {$destination}");
            }
            
            return $this->calculateCostFromCountryRate($countryRate, $destination, $durationSeconds);
        }

        return $this->calculateCostFromCallRate($rate, $destination, $durationSeconds);
    }

    /**
     * Calculate cost using CallRate model
     */
    protected function calculateCostFromCallRate(CallRate $rate, string $destination, int $durationSeconds): array
    {
        $billingConfig = $this->getBillingIncrementForDestination($destination);
        $minimumDuration = $rate->minimum_duration ?? 0;
        
        $billableDuration = $this->calculateBillableDuration(
            $durationSeconds,
            $billingConfig,
            $minimumDuration
        );

        $billableMinutes = $billableDuration / 60;
        $cost = round($billableMinutes * $rate->rate_per_minute, 4);

        return [
            'rate' => $rate,
            'cost' => $cost,
            'actual_duration' => $durationSeconds,
            'billable_duration' => $billableDuration,
            'rate_per_minute' => $rate->rate_per_minute,
            'destination_name' => $rate->destination_name,
            'billing_config' => $billingConfig,
            'minimum_duration' => $minimumDuration,
            'rate_source' => 'call_rate'
        ];
    }

    /**
     * Calculate cost using CountryRate model
     */
    protected function calculateCostFromCountryRate(CountryRate $countryRate, string $destination, int $durationSeconds): array
    {
        $billingConfig = $this->getBillingIncrementForDestination($destination);
        $minimumDuration = $countryRate->minimum_duration ?? 0;
        
        $billableDuration = $this->calculateBillableDuration(
            $durationSeconds,
            $billingConfig,
            $minimumDuration
        );

        $billableMinutes = $billableDuration / 60;
        $cost = round($billableMinutes * $countryRate->call_rate_per_minute, 4);

        return [
            'country_rate' => $countryRate,
            'cost' => $cost,
            'actual_duration' => $durationSeconds,
            'billable_duration' => $billableDuration,
            'rate_per_minute' => $countryRate->call_rate_per_minute,
            'destination_name' => $countryRate->country_name,
            'billing_config' => $billingConfig,
            'minimum_duration' => $minimumDuration,
            'rate_source' => 'country_rate'
        ];
    }

    /**
     * Process advanced billing for a completed call
     */
    public function processAdvancedCallBilling(CallRecord $callRecord): bool
    {
        try {
            if ($callRecord->cost !== null) {
                Log::info("Call {$callRecord->call_id} already billed");
                return true;
            }

            $durationSeconds = $callRecord->getDurationInSeconds();
            
            if ($durationSeconds <= 0) {
                Log::info("Call {$callRecord->call_id} has zero duration, no billing required");
                $callRecord->update([
                    'cost' => 0,
                    'billing_status' => 'completed',
                    'billing_details' => json_encode(['reason' => 'zero_duration'])
                ]);
                return true;
            }

            $billingInfo = $this->calculateAdvancedCallCost($callRecord->destination, $durationSeconds);
            
            // Update call record with detailed billing information
            $callRecord->update([
                'cost' => $billingInfo['cost'],
                'billing_status' => 'calculated',
                'billing_details' => json_encode([
                    'actual_duration' => $billingInfo['actual_duration'],
                    'billable_duration' => $billingInfo['billable_duration'],
                    'rate_per_minute' => $billingInfo['rate_per_minute'],
                    'billing_config' => $billingInfo['billing_config'],
                    'minimum_duration' => $billingInfo['minimum_duration'],
                    'rate_source' => $billingInfo['rate_source']
                ])
            ]);
            
            // Deduct from user balance for prepaid accounts
            $user = $callRecord->user;
            if ($user->isPrepaid()) {
                if ($user->deductBalance($billingInfo['cost'])) {
                    $callRecord->update(['billing_status' => 'paid']);
                } else {
                    $callRecord->update(['billing_status' => 'unpaid']);
                    Log::error("Failed to deduct balance for call {$callRecord->call_id}");
                    return false;
                }
            } else {
                // For postpaid, mark as unpaid (will be billed later)
                $callRecord->update(['billing_status' => 'unpaid']);
            }
            
            Log::info("Successfully processed advanced billing for call {$callRecord->call_id} - Cost: {$billingInfo['cost']}, Duration: {$billingInfo['actual_duration']}s -> {$billingInfo['billable_duration']}s");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error processing advanced billing for call {$callRecord->call_id}: " . $e->getMessage());
            $callRecord->update([
                'billing_status' => 'error',
                'billing_details' => json_encode(['error' => $e->getMessage()])
            ]);
            return false;
        }
    }

    /**
     * Get billing configuration for admin interface
     */
    public function getBillingConfiguration(): array
    {
        return [
            'default_increment' => $this->getDefaultBillingIncrement(),
            'available_increments' => $this->getAvailableBillingIncrements(),
            'country_specific_rates' => CountryRate::active()->count(),
            'call_specific_rates' => CallRate::active()->count(),
            'billing_settings' => SystemSetting::getByGroup('billing')
        ];
    }

    /**
     * Update billing configuration
     */
    public function updateBillingConfiguration(array $config): void
    {
        if (isset($config['default_increment'])) {
            $this->setDefaultBillingIncrement($config['default_increment']);
        }

        // Update other billing settings
        foreach ($config as $key => $value) {
            if ($key !== 'default_increment' && str_starts_with($key, 'billing.')) {
                SystemSetting::set(
                    $key,
                    $value,
                    is_bool($value) ? 'boolean' : (is_numeric($value) ? 'float' : 'string'),
                    'billing'
                );
            }
        }
    }

    /**
     * Get billing statistics
     */
    public function getBillingStatistics(): array
    {
        $stats = [
            'total_calls_today' => CallRecord::whereDate('created_at', today())->count(),
            'total_revenue_today' => CallRecord::whereDate('created_at', today())->sum('cost'),
            'calls_by_billing_status' => CallRecord::selectRaw('billing_status, COUNT(*) as count')
                ->groupBy('billing_status')
                ->pluck('count', 'billing_status')
                ->toArray(),
            'average_call_duration' => CallRecord::whereNotNull('end_time')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) as avg_duration')
                ->value('avg_duration'),
            'top_destinations' => CallRecord::selectRaw('destination, COUNT(*) as call_count, SUM(cost) as total_cost')
                ->groupBy('destination')
                ->orderByDesc('call_count')
                ->limit(10)
                ->get()
        ];

        return $stats;
    }
}