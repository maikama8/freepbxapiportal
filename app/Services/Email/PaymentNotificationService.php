<?php

namespace App\Services\Email;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Mail\PaymentConfirmationEmail;
use App\Mail\PaymentFailedEmail;
use App\Mail\LowBalanceWarningEmail;
use Illuminate\Support\Facades\Log;

class PaymentNotificationService
{
    protected EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send payment confirmation email
     *
     * @param PaymentTransaction $payment
     * @return bool
     */
    public function sendPaymentConfirmation(PaymentTransaction $payment): bool
    {
        try {
            $email = new PaymentConfirmationEmail($payment);
            $result = $this->emailService->sendToUser($payment->user, $email);
            
            if ($result) {
                Log::info('Payment confirmation email sent', [
                    'user_id' => $payment->user_id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'user_id' => $payment->user_id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send payment failed email
     *
     * @param PaymentTransaction $payment
     * @param string $reason
     * @return bool
     */
    public function sendPaymentFailed(PaymentTransaction $payment, string $reason = ''): bool
    {
        try {
            $email = new PaymentFailedEmail($payment, $reason);
            $result = $this->emailService->sendToUser($payment->user, $email);
            
            if ($result) {
                Log::info('Payment failed email sent', [
                    'user_id' => $payment->user_id,
                    'payment_id' => $payment->id,
                    'reason' => $reason
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send payment failed email', [
                'user_id' => $payment->user_id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send low balance warning email
     *
     * @param User $user
     * @param float $threshold
     * @return bool
     */
    public function sendLowBalanceWarning(User $user, float $threshold = null): bool
    {
        try {
            $threshold = $threshold ?? config('voip.low_balance_threshold', 5.00);
            
            // Don't send if balance is not actually low
            if ($user->balance > $threshold) {
                return false;
            }
            
            // Check if we've already sent a warning recently (within 24 hours)
            $recentWarning = \DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('action', 'low_balance_warning_sent')
                ->where('created_at', '>', now()->subHours(24))
                ->exists();
                
            if ($recentWarning) {
                Log::info('Low balance warning skipped - recent warning already sent', [
                    'user_id' => $user->id,
                    'balance' => $user->balance
                ]);
                return false;
            }
            
            $email = new LowBalanceWarningEmail($user, $threshold);
            $result = $this->emailService->sendToUser($user, $email);
            
            if ($result) {
                // Log the warning to prevent duplicate emails
                \DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'action' => 'low_balance_warning_sent',
                    'details' => json_encode([
                        'balance' => $user->balance,
                        'threshold' => $threshold
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                Log::info('Low balance warning email sent', [
                    'user_id' => $user->id,
                    'balance' => $user->balance,
                    'threshold' => $threshold
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send low balance warning email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Queue payment confirmation email
     *
     * @param PaymentTransaction $payment
     * @return bool
     */
    public function queuePaymentConfirmation(PaymentTransaction $payment): bool
    {
        try {
            $email = new PaymentConfirmationEmail($payment);
            return $this->emailService->queueToUser($payment->user, $email);
        } catch (\Exception $e) {
            Log::error('Failed to queue payment confirmation email', [
                'user_id' => $payment->user_id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Queue payment failed email
     *
     * @param PaymentTransaction $payment
     * @param string $reason
     * @return bool
     */
    public function queuePaymentFailed(PaymentTransaction $payment, string $reason = ''): bool
    {
        try {
            $email = new PaymentFailedEmail($payment, $reason);
            return $this->emailService->queueToUser($payment->user, $email);
        } catch (\Exception $e) {
            Log::error('Failed to queue payment failed email', [
                'user_id' => $payment->user_id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check and send low balance warnings for all users
     *
     * @param float $threshold
     * @return array
     */
    public function checkAndSendLowBalanceWarnings(float $threshold = null): array
    {
        $threshold = $threshold ?? config('voip.low_balance_threshold', 5.00);
        
        $lowBalanceUsers = User::where('account_type', 'prepaid')
            ->where('balance', '<=', $threshold)
            ->where('status', 'active')
            ->get();
            
        $results = [];
        
        foreach ($lowBalanceUsers as $user) {
            $results[$user->id] = $this->sendLowBalanceWarning($user, $threshold);
        }
        
        Log::info('Low balance warning check completed', [
            'threshold' => $threshold,
            'users_checked' => $lowBalanceUsers->count(),
            'warnings_sent' => count(array_filter($results))
        ]);
        
        return $results;
    }
}