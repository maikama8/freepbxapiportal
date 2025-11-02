<?php

namespace App\Services\Payment;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Email\PaymentNotificationService;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    private NowPaymentsGateway $nowPaymentsGateway;
    private PayPalGateway $paypalGateway;
    private PaymentNotificationService $notificationService;

    public function __construct(
        NowPaymentsGateway $nowPaymentsGateway,
        PayPalGateway $paypalGateway,
        PaymentNotificationService $notificationService
    ) {
        $this->nowPaymentsGateway = $nowPaymentsGateway;
        $this->paypalGateway = $paypalGateway;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a payment using the specified gateway
     */
    public function createPayment(
        User $user,
        float $amount,
        string $currency,
        string $gateway,
        string $paymentMethod
    ): PaymentTransaction {
        switch ($gateway) {
            case 'nowpayments':
                return $this->nowPaymentsGateway->createPayment($user, $amount, $currency, $paymentMethod);
            
            case 'paypal':
                return $this->paypalGateway->createPayment($user, $amount, $currency);
            
            default:
                throw new Exception("Unsupported payment gateway: {$gateway}");
        }
    }

    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods(): array
    {
        $methods = [];

        // PayPal
        $methods['paypal'] = [
            'name' => 'PayPal',
            'type' => 'paypal',
            'gateway' => 'paypal',
            'currencies' => ['USD', 'EUR', 'GBP'],
            'min_amount' => 1.00,
            'max_amount' => 10000.00,
            'description' => 'Pay with PayPal account or credit/debit card',
        ];

        // Cryptocurrency via NowPayments
        try {
            $cryptoCurrencies = $this->nowPaymentsGateway->getAvailableCurrencies();
            
            foreach ($cryptoCurrencies as $crypto) {
                $methods["crypto_{$crypto}"] = [
                    'name' => $crypto,
                    'type' => 'cryptocurrency',
                    'gateway' => 'nowpayments',
                    'currencies' => ['USD', 'EUR'],
                    'min_amount' => 0.01,
                    'max_amount' => 10000.00,
                    'description' => "Pay with {$crypto} cryptocurrency",
                    'crypto_currency' => $crypto,
                ];
            }
        } catch (Exception $e) {
            Log::warning('Failed to get crypto currencies', [
                'error' => $e->getMessage()
            ]);
        }

        return $methods;
    }

    /**
     * Get payment transaction by ID
     */
    public function getPaymentTransaction(int $transactionId, ?int $userId = null): ?PaymentTransaction
    {
        $query = PaymentTransaction::where('id', $transactionId);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Get user's payment history
     */
    public function getUserPaymentHistory(User $user, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = PaymentTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 15;
        
        return $query->paginate($perPage);
    }

    /**
     * Get payment statistics for a user
     */
    public function getUserPaymentStats(User $user): array
    {
        $stats = [
            'total_payments' => 0,
            'completed_payments' => 0,
            'pending_payments' => 0,
            'failed_payments' => 0,
            'total_amount' => 0,
            'completed_amount' => 0,
        ];

        $transactions = PaymentTransaction::where('user_id', $user->id)->get();

        foreach ($transactions as $transaction) {
            $stats['total_payments']++;
            
            switch ($transaction->status) {
                case 'completed':
                    $stats['completed_payments']++;
                    $stats['completed_amount'] += $transaction->amount;
                    break;
                case 'pending':
                    $stats['pending_payments']++;
                    break;
                default:
                    $stats['failed_payments']++;
                    break;
            }
            
            $stats['total_amount'] += $transaction->amount;
        }

        return $stats;
    }

    /**
     * Process payment completion
     */
    public function completePayment(PaymentTransaction $transaction): bool
    {
        if ($transaction->isCompleted()) {
            return true;
        }

        try {
            // Mark payment as completed
            $transaction->markAsCompleted();
            
            // Update user balance
            $transaction->user->addBalance($transaction->amount);
            
            Log::info('Payment completed', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
                'gateway' => $transaction->gateway
            ]);

            // Send payment confirmation email if enabled
            if (config('voip.email.notifications.payment_confirmation', true)) {
                if (config('voip.email.queue_emails', true)) {
                    $this->notificationService->queuePaymentConfirmation($transaction);
                } else {
                    $this->notificationService->sendPaymentConfirmation($transaction);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to complete payment', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process payment failure
     */
    public function failPayment(PaymentTransaction $transaction, string $reason = ''): bool
    {
        if ($transaction->isFailed()) {
            return true;
        }

        try {
            $transaction->markAsFailed('failed');
            
            Log::info('Payment failed', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'reason' => $reason,
                'gateway' => $transaction->gateway
            ]);

            // Send payment failure email if enabled
            if (config('voip.email.notifications.payment_failed', true)) {
                if (config('voip.email.queue_emails', true)) {
                    $this->notificationService->queuePaymentFailed($transaction, $reason);
                } else {
                    $this->notificationService->sendPaymentFailed($transaction, $reason);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Failed to process payment failure', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Cancel a pending payment
     */
    public function cancelPayment(PaymentTransaction $transaction): bool
    {
        if (!$transaction->isPending()) {
            return false;
        }

        try {
            $transaction->markAsFailed('cancelled');
            
            Log::info('Payment cancelled', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'gateway' => $transaction->gateway
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to cancel payment', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Retry a failed payment
     */
    public function retryPayment(PaymentTransaction $originalTransaction): PaymentTransaction
    {
        if (!$originalTransaction->isFailed()) {
            throw new Exception('Can only retry failed payments');
        }

        return $this->createPayment(
            $originalTransaction->user,
            $originalTransaction->amount,
            $originalTransaction->currency,
            $originalTransaction->gateway,
            $originalTransaction->payment_method
        );
    }

    /**
     * Get minimum payment amount for a gateway and currency
     */
    public function getMinimumAmount(string $gateway, string $paymentMethod): ?float
    {
        switch ($gateway) {
            case 'nowpayments':
                return $this->nowPaymentsGateway->getMinimumAmount($paymentMethod);
            
            case 'paypal':
                return 1.00; // PayPal minimum
            
            default:
                return null;
        }
    }

    /**
     * Validate payment parameters
     */
    public function validatePaymentParameters(
        float $amount,
        string $currency,
        string $gateway,
        string $paymentMethod
    ): array {
        $errors = [];

        // Validate amount
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }

        if ($amount > 10000) {
            $errors[] = 'Amount cannot exceed $10,000';
        }

        // Validate currency
        $supportedCurrencies = ['USD', 'EUR', 'GBP'];
        if (!in_array($currency, $supportedCurrencies)) {
            $errors[] = 'Unsupported currency';
        }

        // Validate gateway and payment method
        switch ($gateway) {
            case 'nowpayments':
                $availableCrypto = $this->nowPaymentsGateway->getAvailableCurrencies();
                if (!in_array($paymentMethod, $availableCrypto)) {
                    $errors[] = 'Unsupported cryptocurrency';
                }
                
                $minAmount = $this->nowPaymentsGateway->getMinimumAmount($paymentMethod);
                if ($minAmount && $amount < $minAmount) {
                    $errors[] = "Minimum amount for {$paymentMethod} is {$minAmount} {$currency}";
                }
                break;

            case 'paypal':
                if ($paymentMethod !== 'paypal') {
                    $errors[] = 'Invalid payment method for PayPal';
                }
                
                if ($amount < 1.00) {
                    $errors[] = 'Minimum amount for PayPal is $1.00';
                }
                break;

            default:
                $errors[] = 'Unsupported payment gateway';
                break;
        }

        return $errors;
    }
}