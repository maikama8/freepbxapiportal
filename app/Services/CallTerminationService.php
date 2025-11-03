<?php

namespace App\Services;

use App\Models\CallRecord;
use App\Models\User;
use App\Services\FreePBX\CallManagementService;
use App\Services\RealTimeBillingService;
use Illuminate\Support\Facades\Log;

class CallTerminationService
{
    protected $callManagementService;
    protected $realTimeBillingService;

    public function __construct(
        CallManagementService $callManagementService,
        RealTimeBillingService $realTimeBillingService
    ) {
        $this->callManagementService = $callManagementService;
        $this->realTimeBillingService = $realTimeBillingService;
    }

    /**
     * Terminate call due to insufficient balance
     */
    public function terminateForInsufficientBalance(CallRecord $callRecord, string $reason = 'Insufficient balance'): bool
    {
        try {
            Log::info("Terminating call {$callRecord->call_id} due to: {$reason}");
            
            // Update call record status
            $callRecord->update([
                'status' => 'terminated',
                'end_time' => now(),
                'billing_status' => 'terminated'
            ]);

            // Attempt to terminate via FreePBX
            $terminated = $this->callManagementService->terminateCall($callRecord->call_id);
            
            if ($terminated) {
                // Finalize billing for terminated call
                $this->realTimeBillingService->finalizeBilling($callRecord);
                
                Log::info("Successfully terminated call {$callRecord->call_id}");
                return true;
            } else {
                Log::warning("FreePBX termination failed for call {$callRecord->call_id}, but marked as terminated");
                return true; // Still consider it successful since we marked it terminated
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to terminate call {$callRecord->call_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Emergency terminate call
     */
    public function emergencyTerminate(CallRecord $callRecord, string $reason = 'Emergency termination'): bool
    {
        try {
            Log::warning("Emergency terminating call {$callRecord->call_id}: {$reason}");
            
            // Force terminate via FreePBX
            $terminated = $this->callManagementService->forceTerminateCall($callRecord->call_id);
            
            // Update call record regardless of FreePBX response
            $callRecord->update([
                'status' => 'terminated',
                'end_time' => now(),
                'billing_status' => 'terminated',
                'billing_details' => json_encode([
                    'termination_reason' => $reason,
                    'emergency_termination' => true,
                    'terminated_at' => now()->toISOString()
                ])
            ]);

            // Finalize billing
            $this->realTimeBillingService->finalizeBilling($callRecord);
            
            Log::info("Emergency terminated call {$callRecord->call_id}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to emergency terminate call {$callRecord->call_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Terminate all calls for a user (e.g., when account is suspended)
     */
    public function terminateAllUserCalls(User $user, string $reason = 'Account suspended'): int
    {
        $terminatedCount = 0;
        
        $activeCalls = CallRecord::where('user_id', $user->id)
            ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->whereNull('end_time')
            ->get();

        foreach ($activeCalls as $callRecord) {
            if ($this->terminateForInsufficientBalance($callRecord, $reason)) {
                $terminatedCount++;
            }
        }

        Log::info("Terminated {$terminatedCount} calls for user {$user->id}: {$reason}");
        return $terminatedCount;
    }

    /**
     * Check and terminate calls with insufficient balance
     */
    public function checkAndTerminateInsufficientBalanceCalls(): int
    {
        $terminatedCount = 0;
        
        $activeCalls = $this->realTimeBillingService->getActiveCallsWithBilling();
        
        foreach ($activeCalls as $callData) {
            $callRecord = $callData['call_record'];
            $currentCost = $callData['current_cost'];
            $userBalance = $callData['user_balance'];
            
            // Check if user has insufficient balance for current cost
            if ($userBalance < $currentCost) {
                if ($this->terminateForInsufficientBalance($callRecord, 'Insufficient balance during call')) {
                    $terminatedCount++;
                }
            }
        }

        if ($terminatedCount > 0) {
            Log::info("Terminated {$terminatedCount} calls due to insufficient balance");
        }
        
        return $terminatedCount;
    }

    /**
     * Terminate calls that exceed maximum duration
     */
    public function terminateExcessiveDurationCalls(int $maxDurationMinutes = 480): int
    {
        $terminatedCount = 0;
        $maxDurationSeconds = $maxDurationMinutes * 60;
        
        $longCalls = CallRecord::whereIn('status', ['answered', 'in_progress'])
            ->whereNull('end_time')
            ->where('start_time', '<=', now()->subSeconds($maxDurationSeconds))
            ->get();

        foreach ($longCalls as $callRecord) {
            if ($this->terminateForInsufficientBalance($callRecord, "Exceeded maximum duration of {$maxDurationMinutes} minutes")) {
                $terminatedCount++;
            }
        }

        if ($terminatedCount > 0) {
            Log::info("Terminated {$terminatedCount} calls due to excessive duration");
        }
        
        return $terminatedCount;
    }

    /**
     * Get termination statistics
     */
    public function getTerminationStats(): array
    {
        $today = now()->startOfDay();
        
        return [
            'terminated_today' => CallRecord::where('status', 'terminated')
                ->where('created_at', '>=', $today)
                ->count(),
            'terminated_insufficient_balance' => CallRecord::where('billing_status', 'terminated')
                ->where('created_at', '>=', $today)
                ->count(),
            'active_calls' => CallRecord::whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
                ->whereNull('end_time')
                ->count(),
            'calls_at_risk' => $this->getCallsAtRiskCount()
        ];
    }

    /**
     * Get count of calls at risk of termination
     */
    protected function getCallsAtRiskCount(): int
    {
        $activeCalls = $this->realTimeBillingService->getActiveCallsWithBilling();
        
        return count(array_filter($activeCalls, function ($call) {
            return $call['user_balance'] < $call['current_cost'] * 1.5; // Less than 1.5x current cost
        }));
    }
}