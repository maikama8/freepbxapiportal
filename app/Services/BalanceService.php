<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Services\Email\PaymentNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceService
{
    protected PaymentNotificationService $notificationService;

    public function __construct(PaymentNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Add balance to user account
     */
    public function addBalance(User $user, float $amount, string $reason = 'Manual adjustment', ?User $adminUser = null): bool
    {
        try {
            DB::beginTransaction();
            
            $oldBalance = $user->balance;
            $user->addBalance($amount);
            
            // Log the balance change
            $this->logBalanceChange($user, $oldBalance, $user->balance, $reason, $adminUser);
            
            DB::commit();
            
            Log::info("Added balance {$amount} to user {$user->id}. New balance: {$user->balance}");
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to add balance to user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deduct balance from user account
     */
    public function deductBalance(User $user, float $amount, string $reason = 'Call charge', ?User $adminUser = null): bool
    {
        try {
            DB::beginTransaction();
            
            $oldBalance = $user->balance;
            
            if (!$user->deductBalance($amount)) {
                DB::rollBack();
                return false;
            }
            
            // Log the balance change
            $this->logBalanceChange($user, $oldBalance, $user->balance, $reason, $adminUser);
            
            // Check for low balance and send warning if needed
            $this->checkAndSendLowBalanceWarning($user);
            
            DB::commit();
            
            Log::info("Deducted balance {$amount} from user {$user->id}. New balance: {$user->balance}");
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to deduct balance from user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set user balance to specific amount
     */
    public function setBalance(User $user, float $newBalance, string $reason = 'Balance adjustment', ?User $adminUser = null): bool
    {
        try {
            DB::beginTransaction();
            
            $oldBalance = $user->balance;
            $user->balance = $newBalance;
            $user->save();
            
            // Log the balance change
            $this->logBalanceChange($user, $oldBalance, $newBalance, $reason, $adminUser);
            
            DB::commit();
            
            Log::info("Set balance for user {$user->id} from {$oldBalance} to {$newBalance}");
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to set balance for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update credit limit for postpaid accounts
     */
    public function updateCreditLimit(User $user, float $newCreditLimit, ?User $adminUser = null): bool
    {
        try {
            if ($user->isPrepaid()) {
                Log::warning("Attempted to set credit limit for prepaid user {$user->id}");
                return false;
            }

            DB::beginTransaction();
            
            $oldCreditLimit = $user->credit_limit;
            $user->credit_limit = $newCreditLimit;
            $user->save();
            
            // Log the credit limit change
            AuditLog::create([
                'user_id' => $adminUser?->id,
                'action' => 'credit_limit_updated',
                'description' => "Credit limit updated for user {$user->id} from {$oldCreditLimit} to {$newCreditLimit}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'target_user_id' => $user->id,
                    'old_credit_limit' => $oldCreditLimit,
                    'new_credit_limit' => $newCreditLimit
                ]
            ]);
            
            DB::commit();
            
            Log::info("Updated credit limit for user {$user->id} from {$oldCreditLimit} to {$newCreditLimit}");
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update credit limit for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available balance (including credit limit for postpaid)
     */
    public function getAvailableBalance(User $user): float
    {
        if ($user->isPrepaid()) {
            return max(0, $user->balance);
        }
        
        // For postpaid, include credit limit
        return $user->balance + $user->credit_limit;
    }

    /**
     * Check if user has sufficient balance for amount
     */
    public function hasSufficientBalance(User $user, float $amount): bool
    {
        return $this->getAvailableBalance($user) >= $amount;
    }

    /**
     * Get balance status information
     */
    public function getBalanceStatus(User $user): array
    {
        $availableBalance = $this->getAvailableBalance($user);
        $isLowBalance = false;
        $warningThreshold = 0;

        if ($user->isPrepaid()) {
            $warningThreshold = config('voip.billing.low_balance_threshold', 5.00);
            $isLowBalance = $user->balance <= $warningThreshold;
        } else {
            // For postpaid, warn when approaching credit limit
            $warningThreshold = $user->credit_limit * 0.8; // 80% of credit limit
            $isLowBalance = abs($user->balance) >= $warningThreshold;
        }

        return [
            'current_balance' => $user->balance,
            'credit_limit' => $user->credit_limit,
            'available_balance' => $availableBalance,
            'account_type' => $user->account_type,
            'is_low_balance' => $isLowBalance,
            'warning_threshold' => $warningThreshold,
            'currency' => $user->currency ?? 'USD'
        ];
    }

    /**
     * Get users with low balance
     */
    public function getUsersWithLowBalance(): array
    {
        $lowBalanceThreshold = config('voip.billing.low_balance_threshold', 5.00);
        
        $prepaidUsers = User::where('account_type', 'prepaid')
            ->where('balance', '<=', $lowBalanceThreshold)
            ->where('status', 'active')
            ->get();

        $postpaidUsers = User::where('account_type', 'postpaid')
            ->whereRaw('ABS(balance) >= (credit_limit * 0.8)')
            ->where('status', 'active')
            ->get();

        return [
            'prepaid' => $prepaidUsers,
            'postpaid' => $postpaidUsers,
            'total_count' => $prepaidUsers->count() + $postpaidUsers->count()
        ];
    }

    /**
     * Process automatic balance adjustments (e.g., monthly fees)
     */
    public function processAutomaticAdjustments(): int
    {
        $processedCount = 0;
        
        // This could be expanded to handle monthly fees, taxes, etc.
        // For now, it's a placeholder for future functionality
        
        Log::info("Processed {$processedCount} automatic balance adjustments");
        return $processedCount;
    }

    /**
     * Generate balance report for a user
     */
    public function generateBalanceReport(User $user, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $startDate = $startDate ?? now()->subMonth();
        $endDate = $endDate ?? now();

        // Get balance changes from audit logs
        $balanceChanges = AuditLog::where('metadata->target_user_id', $user->id)
            ->whereIn('action', ['balance_added', 'balance_deducted', 'balance_adjusted'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get call charges
        $callCharges = $user->callRecords()
            ->whereNotNull('cost')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('cost');

        return [
            'user_id' => $user->id,
            'period' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s')
            ],
            'current_balance' => $user->balance,
            'balance_changes' => $balanceChanges,
            'total_call_charges' => $callCharges,
            'balance_status' => $this->getBalanceStatus($user)
        ];
    }

    /**
     * Check and send low balance warning if needed
     */
    private function checkAndSendLowBalanceWarning(User $user): void
    {
        if (!config('voip.email.notifications.low_balance_warning', true)) {
            return;
        }

        $threshold = config('voip.billing.low_balance_threshold', 5.00);
        
        // Only send warnings for prepaid accounts
        if ($user->isPrepaid() && $user->balance <= $threshold) {
            if (config('voip.email.queue_emails', true)) {
                $this->notificationService->sendLowBalanceWarning($user, $threshold);
            } else {
                $this->notificationService->sendLowBalanceWarning($user, $threshold);
            }
        }
    }

    /**
     * Send low balance warnings to all eligible users
     */
    public function sendLowBalanceWarnings(): array
    {
        if (!config('voip.email.notifications.low_balance_warning', true)) {
            return ['message' => 'Low balance warnings are disabled'];
        }

        return $this->notificationService->checkAndSendLowBalanceWarnings();
    }

    /**
     * Log balance changes for audit purposes
     */
    private function logBalanceChange(User $user, float $oldBalance, float $newBalance, string $reason, ?User $adminUser = null): void
    {
        $amount = abs($newBalance - $oldBalance);
        $type = $newBalance > $oldBalance ? 'credit' : 'debit';
        
        // Create balance transaction record
        BalanceTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $oldBalance,
            'balance_after' => $newBalance,
            'description' => $reason,
            'created_by' => $adminUser?->id
        ]);

        // Also create audit log for administrative tracking
        $action = $newBalance > $oldBalance ? 'balance_added' : 'balance_deducted';
        AuditLog::create([
            'user_id' => $adminUser?->id,
            'action' => $action,
            'description' => "{$reason}: {$action} {$amount} for user {$user->id}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'target_user_id' => $user->id,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'amount' => $amount,
                'reason' => $reason
            ]
        ]);
    }
}