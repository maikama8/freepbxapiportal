<?php

namespace App\Services;

use App\Models\CallRate;
use App\Models\CallRecord;
use App\Models\User;
use App\Exceptions\FreePBXApiException;
use Illuminate\Support\Facades\Log;

class BillingService
{
    /**
     * Calculate the cost for a call
     */
    public function calculateCallCost(string $destination, int $durationSeconds): array
    {
        $rate = CallRate::findRateForDestination($destination);
        
        if (!$rate) {
            throw new FreePBXApiException("No rate found for destination: {$destination}");
        }

        $cost = $rate->calculateCost($durationSeconds);
        
        return [
            'rate' => $rate,
            'cost' => $cost,
            'billable_duration' => max($durationSeconds, $rate->minimum_duration),
            'rate_per_minute' => $rate->rate_per_minute,
            'destination_name' => $rate->destination_name
        ];
    }

    /**
     * Process billing for a completed call
     */
    public function processCallBilling(CallRecord $callRecord): bool
    {
        try {
            if ($callRecord->cost !== null) {
                Log::info("Call {$callRecord->call_id} already billed");
                return true;
            }

            $durationSeconds = $callRecord->getDurationInSeconds();
            
            if ($durationSeconds <= 0) {
                Log::info("Call {$callRecord->call_id} has zero duration, no billing required");
                $callRecord->update(['cost' => 0]);
                return true;
            }

            $billingInfo = $this->calculateCallCost($callRecord->destination, $durationSeconds);
            
            // Update call record with cost
            $callRecord->update(['cost' => $billingInfo['cost']]);
            
            // Deduct from user balance for prepaid accounts
            $user = $callRecord->user;
            if ($user->isPrepaid()) {
                if (!$user->deductBalance($billingInfo['cost'])) {
                    Log::error("Failed to deduct balance for call {$callRecord->call_id}");
                    return false;
                }
            }
            
            Log::info("Successfully billed call {$callRecord->call_id} for {$billingInfo['cost']}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error processing billing for call {$callRecord->call_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user can afford a call to destination
     */
    public function canAffordCall(User $user, string $destination, int $estimatedDurationSeconds = 60): array
    {
        try {
            $rate = CallRate::findRateForDestination($destination);
            
            if (!$rate) {
                return [
                    'can_afford' => false,
                    'reason' => 'No rate found for destination',
                    'estimated_cost' => 0
                ];
            }

            $estimatedCost = $rate->calculateCost($estimatedDurationSeconds);
            
            if ($user->isPrepaid()) {
                $canAfford = $user->hasSufficientBalance($estimatedCost);
                $reason = $canAfford ? null : 'Insufficient balance';
            } else {
                // For postpaid, check credit limit
                $canAfford = $user->hasSufficientBalance($estimatedCost);
                $reason = $canAfford ? null : 'Credit limit exceeded';
            }
            
            return [
                'can_afford' => $canAfford,
                'reason' => $reason,
                'estimated_cost' => $estimatedCost,
                'rate_per_minute' => $rate->rate_per_minute,
                'destination_name' => $rate->destination_name,
                'current_balance' => $user->balance,
                'credit_limit' => $user->credit_limit
            ];
            
        } catch (\Exception $e) {
            Log::error("Error checking affordability for user {$user->id}: " . $e->getMessage());
            return [
                'can_afford' => false,
                'reason' => 'Error calculating cost',
                'estimated_cost' => 0
            ];
        }
    }

    /**
     * Get rate information for a destination
     */
    public function getRateInfo(string $destination): ?array
    {
        $rate = CallRate::findRateForDestination($destination);
        
        if (!$rate) {
            return null;
        }

        return [
            'destination_prefix' => $rate->destination_prefix,
            'destination_name' => $rate->destination_name,
            'rate_per_minute' => $rate->rate_per_minute,
            'minimum_duration' => $rate->minimum_duration,
            'billing_increment' => $rate->billing_increment,
            'formatted_rate' => $rate->getFormattedRate()
        ];
    }

    /**
     * Calculate maximum call duration for user balance
     */
    public function getMaxCallDuration(User $user, string $destination): int
    {
        $rate = CallRate::findRateForDestination($destination);
        
        if (!$rate) {
            return 0;
        }

        $availableBalance = $user->isPrepaid() 
            ? $user->balance 
            : $user->balance + $user->credit_limit;

        if ($availableBalance <= 0) {
            return 0;
        }

        // Calculate maximum minutes based on available balance
        $maxMinutes = $availableBalance / $rate->rate_per_minute;
        
        // Convert to seconds and ensure it's at least the minimum duration
        $maxSeconds = (int) ($maxMinutes * 60);
        
        return max($maxSeconds, $rate->minimum_duration);
    }

    /**
     * Bulk update call costs for unprocessed calls
     */
    public function processPendingBilling(): int
    {
        $processedCount = 0;
        
        $unbilledCalls = CallRecord::whereNull('cost')
            ->where('status', 'completed')
            ->whereNotNull('end_time')
            ->get();

        foreach ($unbilledCalls as $callRecord) {
            if ($this->processCallBilling($callRecord)) {
                $processedCount++;
            }
        }

        Log::info("Processed billing for {$processedCount} calls");
        return $processedCount;
    }
}