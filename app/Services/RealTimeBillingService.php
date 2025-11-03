<?php

namespace App\Services;

use App\Models\CallRecord;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\AdvancedBillingService;
use App\Services\FreePBX\CallManagementService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RealTimeBillingService
{
    protected $advancedBillingService;
    protected $callManagementService;

    public function __construct(
        AdvancedBillingService $advancedBillingService,
        CallManagementService $callManagementService
    ) {
        $this->advancedBillingService = $advancedBillingService;
        $this->callManagementService = $callManagementService;
    }

    /**
     * Start real-time billing for a call
     */
    public function startRealTimeBilling(CallRecord $callRecord): bool
    {
        if (!$this->isRealTimeBillingEnabled()) {
            Log::info("Real-time billing is disabled for call {$callRecord->call_id}");
            return false;
        }

        try {
            // Get billing configuration for the destination
            $billingConfig = $this->advancedBillingService->getBillingIncrementForDestination($callRecord->destination);
            
            // Calculate initial cost and check balance
            $initialCost = $this->calculateInitialCost($callRecord, $billingConfig);
            
            if (!$this->checkAndReserveBalance($callRecord->user, $initialCost)) {
                Log::warning("Insufficient balance for call {$callRecord->call_id}");
                return false;
            }

            // Store billing session data
            $this->storeBillingSession($callRecord, $billingConfig, $initialCost);
            
            // Schedule periodic billing checks
            $this->schedulePeriodicBilling($callRecord);
            
            Log::info("Started real-time billing for call {$callRecord->call_id}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to start real-time billing for call {$callRecord->call_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process periodic billing during active call
     */
    public function processPeriodicBilling(CallRecord $callRecord): bool
    {
        if (!$this->isRealTimeBillingEnabled()) {
            return false;
        }

        try {
            $billingSession = $this->getBillingSession($callRecord);
            if (!$billingSession) {
                Log::warning("No billing session found for call {$callRecord->call_id}");
                return false;
            }

            $currentDuration = $this->getCurrentCallDuration($callRecord);
            $newCost = $this->calculateCurrentCost($callRecord, $billingSession, $currentDuration);
            
            // Check if user has sufficient balance for the new cost
            if (!$this->checkSufficientBalance($callRecord->user, $newCost)) {
                Log::warning("Insufficient balance during call {$callRecord->call_id}, terminating");
                $this->terminateCallForInsufficientBalance($callRecord);
                return false;
            }

            // Update billing session with new cost
            $this->updateBillingSession($callRecord, $newCost, $currentDuration);
            
            Log::debug("Processed periodic billing for call {$callRecord->call_id} - Duration: {$currentDuration}s, Cost: {$newCost}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to process periodic billing for call {$callRecord->call_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finalize billing when call ends
     */
    public function finalizeBilling(CallRecord $callRecord): bool
    {
        try {
            $billingSession = $this->getBillingSession($callRecord);
            if (!$billingSession) {
                Log::info("No billing session to finalize for call {$callRecord->call_id}");
                return $this->advancedBillingService->processAdvancedCallBilling($callRecord);
            }

            $finalDuration = $callRecord->getDurationInSeconds();
            $finalCost = $this->calculateFinalCost($callRecord, $billingSession, $finalDuration);
            
            // Update call record with final billing information
            $callRecord->update([
                'cost' => $finalCost,
                'actual_duration' => $finalDuration,
                'billable_duration' => $billingSession['billable_duration'],
                'billing_status' => 'calculated',
                'billing_details' => json_encode([
                    'real_time_billing' => true,
                    'billing_config' => $billingSession['billing_config'],
                    'periodic_checks' => $billingSession['periodic_checks'] ?? 0,
                    'final_cost' => $finalCost,
                    'reserved_amount' => $billingSession['reserved_amount'] ?? 0
                ])
            ]);

            // Deduct final amount from user balance
            if ($callRecord->user->isPrepaid()) {
                if ($callRecord->user->deductBalance($finalCost)) {
                    $callRecord->update(['billing_status' => 'paid']);
                } else {
                    $callRecord->update(['billing_status' => 'unpaid']);
                    Log::error("Failed to deduct final amount for call {$callRecord->call_id}");
                }
            } else {
                $callRecord->update(['billing_status' => 'unpaid']);
            }

            // Clean up billing session
            $this->clearBillingSession($callRecord);
            
            Log::info("Finalized real-time billing for call {$callRecord->call_id} - Final cost: {$finalCost}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to finalize billing for call {$callRecord->call_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Terminate call due to insufficient balance
     */
    public function terminateCallForInsufficientBalance(CallRecord $callRecord): bool
    {
        if (!$this->isAutoTerminationEnabled()) {
            Log::info("Auto-termination is disabled, not terminating call {$callRecord->call_id}");
            return false;
        }

        try {
            // Apply grace period if configured
            $gracePeriod = $this->getGracePeriod();
            if ($gracePeriod > 0) {
                Log::info("Applying {$gracePeriod}s grace period for call {$callRecord->call_id}");
                sleep($gracePeriod);
                
                // Re-check balance after grace period
                $billingSession = $this->getBillingSession($callRecord);
                if ($billingSession && $this->checkSufficientBalance($callRecord->user, $billingSession['current_cost'])) {
                    Log::info("Balance sufficient after grace period for call {$callRecord->call_id}");
                    return false;
                }
            }

            // Terminate the call via FreePBX
            $terminated = $this->callManagementService->terminateCall($callRecord->call_id);
            
            if ($terminated) {
                $callRecord->update([
                    'status' => 'terminated',
                    'billing_status' => 'terminated',
                    'end_time' => now()
                ]);
                
                // Finalize billing for the terminated call
                $this->finalizeBilling($callRecord);
                
                Log::info("Successfully terminated call {$callRecord->call_id} due to insufficient balance");
                return true;
            } else {
                Log::error("Failed to terminate call {$callRecord->call_id} via FreePBX");
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to terminate call {$callRecord->call_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get active calls with real-time billing
     */
    public function getActiveCallsWithBilling(): array
    {
        $activeCalls = CallRecord::whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->whereNull('end_time')
            ->get();

        $callsWithBilling = [];
        
        foreach ($activeCalls as $call) {
            $billingSession = $this->getBillingSession($call);
            if ($billingSession) {
                $currentDuration = $this->getCurrentCallDuration($call);
                $currentCost = $this->calculateCurrentCost($call, $billingSession, $currentDuration);
                
                $callsWithBilling[] = [
                    'call_record' => $call,
                    'billing_session' => $billingSession,
                    'current_duration' => $currentDuration,
                    'current_cost' => $currentCost,
                    'user_balance' => $call->user->balance
                ];
            }
        }
        
        return $callsWithBilling;
    }

    /**
     * Calculate initial cost for call start
     */
    protected function calculateInitialCost(CallRecord $callRecord, array $billingConfig): float
    {
        try {
            $billingInfo = $this->advancedBillingService->calculateAdvancedCallCost(
                $callRecord->destination,
                $billingConfig['initial']
            );
            
            return $billingInfo['cost'];
        } catch (\Exception $e) {
            Log::error("Failed to calculate initial cost for call {$callRecord->call_id}: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate current cost based on duration
     */
    protected function calculateCurrentCost(CallRecord $callRecord, array $billingSession, int $currentDuration): float
    {
        try {
            $billingInfo = $this->advancedBillingService->calculateAdvancedCallCost(
                $callRecord->destination,
                $currentDuration
            );
            
            return $billingInfo['cost'];
        } catch (\Exception $e) {
            Log::error("Failed to calculate current cost for call {$callRecord->call_id}: " . $e->getMessage());
            return $billingSession['current_cost'] ?? 0.0;
        }
    }

    /**
     * Calculate final cost for call completion
     */
    protected function calculateFinalCost(CallRecord $callRecord, array $billingSession, int $finalDuration): float
    {
        return $this->calculateCurrentCost($callRecord, $billingSession, $finalDuration);
    }

    /**
     * Check and reserve balance for initial cost
     */
    protected function checkAndReserveBalance(User $user, float $amount): bool
    {
        if ($user->isPrepaid()) {
            return $user->hasSufficientBalance($amount);
        } else {
            // For postpaid, check credit limit
            return $user->hasSufficientBalance($amount);
        }
    }

    /**
     * Check if user has sufficient balance
     */
    protected function checkSufficientBalance(User $user, float $amount): bool
    {
        return $this->checkAndReserveBalance($user, $amount);
    }

    /**
     * Get current call duration in seconds
     */
    protected function getCurrentCallDuration(CallRecord $callRecord): int
    {
        if ($callRecord->start_time) {
            return now()->diffInSeconds($callRecord->start_time);
        }
        
        return 0;
    }

    /**
     * Store billing session data in cache
     */
    protected function storeBillingSession(CallRecord $callRecord, array $billingConfig, float $initialCost): void
    {
        $sessionData = [
            'call_id' => $callRecord->call_id,
            'user_id' => $callRecord->user_id,
            'destination' => $callRecord->destination,
            'billing_config' => $billingConfig,
            'initial_cost' => $initialCost,
            'current_cost' => $initialCost,
            'reserved_amount' => $initialCost,
            'start_time' => now()->toISOString(),
            'last_check' => now()->toISOString(),
            'periodic_checks' => 0,
            'billable_duration' => $billingConfig['initial']
        ];
        
        Cache::put("billing_session_{$callRecord->call_id}", $sessionData, 3600); // 1 hour TTL
    }

    /**
     * Get billing session data from cache
     */
    protected function getBillingSession(CallRecord $callRecord): ?array
    {
        return Cache::get("billing_session_{$callRecord->call_id}");
    }

    /**
     * Update billing session with new cost and duration
     */
    protected function updateBillingSession(CallRecord $callRecord, float $newCost, int $currentDuration): void
    {
        $sessionData = $this->getBillingSession($callRecord);
        if ($sessionData) {
            $sessionData['current_cost'] = $newCost;
            $sessionData['last_check'] = now()->toISOString();
            $sessionData['periodic_checks'] = ($sessionData['periodic_checks'] ?? 0) + 1;
            $sessionData['current_duration'] = $currentDuration;
            
            Cache::put("billing_session_{$callRecord->call_id}", $sessionData, 3600);
        }
    }

    /**
     * Clear billing session from cache
     */
    protected function clearBillingSession(CallRecord $callRecord): void
    {
        Cache::forget("billing_session_{$callRecord->call_id}");
    }

    /**
     * Schedule periodic billing checks (placeholder for queue implementation)
     */
    protected function schedulePeriodicBilling(CallRecord $callRecord): void
    {
        // This would typically dispatch a job to run periodic checks
        // For now, we'll rely on external cron jobs or manual triggers
        Log::debug("Scheduled periodic billing for call {$callRecord->call_id}");
    }

    /**
     * Check if real-time billing is enabled
     */
    protected function isRealTimeBillingEnabled(): bool
    {
        return SystemSetting::get('billing.enable_real_time', true);
    }

    /**
     * Check if auto-termination is enabled
     */
    protected function isAutoTerminationEnabled(): bool
    {
        return SystemSetting::get('billing.auto_terminate_on_zero_balance', true);
    }

    /**
     * Get grace period in seconds
     */
    protected function getGracePeriod(): int
    {
        return (int) SystemSetting::get('billing.grace_period_seconds', 30);
    }

    /**
     * Get real-time billing statistics
     */
    public function getRealTimeBillingStats(): array
    {
        $activeCalls = $this->getActiveCallsWithBilling();
        
        return [
            'active_calls_count' => count($activeCalls),
            'total_active_cost' => array_sum(array_column($activeCalls, 'current_cost')),
            'calls_at_risk' => array_filter($activeCalls, function ($call) {
                return $call['user_balance'] < $call['current_cost'] * 2; // Less than 2x current cost
            }),
            'real_time_enabled' => $this->isRealTimeBillingEnabled(),
            'auto_termination_enabled' => $this->isAutoTerminationEnabled(),
            'grace_period' => $this->getGracePeriod()
        ];
    }
}