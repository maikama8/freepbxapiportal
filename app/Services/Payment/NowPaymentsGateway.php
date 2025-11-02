<?php

namespace App\Services\Payment;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class NowPaymentsGateway
{
    private string $apiKey;
    private string $ipnSecret;
    private string $apiUrl;
    private bool $sandbox;
    private array $supportedCurrencies;

    public function __construct()
    {
        $config = config('voip.payments.nowpayments');
        
        $this->apiKey = $config['api_key'];
        $this->ipnSecret = $config['ipn_secret'];
        $this->apiUrl = $config['api_url'];
        $this->sandbox = $config['sandbox'];
        $this->supportedCurrencies = $config['supported_currencies'];
    }

    /**
     * Create a payment request
     */
    public function createPayment(User $user, float $amount, string $currency = 'USD', string $payCurrency = 'BTC'): PaymentTransaction
    {
        try {
            // Validate currency
            if (!in_array($payCurrency, $this->supportedCurrencies)) {
                throw new Exception("Unsupported payment currency: {$payCurrency}");
            }

            // Create payment transaction record
            $transaction = PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => $currency,
                'gateway' => 'nowpayments',
                'payment_method' => $payCurrency,
                'status' => 'pending',
                'metadata' => [
                    'price_currency' => $currency,
                    'pay_currency' => $payCurrency,
                ]
            ]);

            // Create payment with NowPayments API
            $response = $this->makeApiRequest('POST', '/v1/payment', [
                'price_amount' => $amount,
                'price_currency' => $currency,
                'pay_currency' => $payCurrency,
                'order_id' => $transaction->id,
                'order_description' => "VoIP Platform Balance Top-up - User {$user->id}",
                'ipn_callback_url' => route('webhooks.nowpayments'),
                'success_url' => route('payment.success'),
                'cancel_url' => route('payment.cancel'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Update transaction with gateway response
                $transaction->update([
                    'gateway_transaction_id' => $data['payment_id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_id' => $data['payment_id'],
                        'payment_status' => $data['payment_status'],
                        'pay_address' => $data['pay_address'] ?? null,
                        'pay_amount' => $data['pay_amount'] ?? null,
                        'actually_paid' => $data['actually_paid'] ?? null,
                        'price_currency' => $currency,
                        'pay_currency' => $payCurrency,
                        'created_at' => $data['created_at'] ?? null,
                        'updated_at' => $data['updated_at'] ?? null,
                    ])
                ]);

                Log::info('NowPayments payment created', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'payment_id' => $data['payment_id'],
                    'amount' => $amount,
                    'currency' => $currency,
                    'pay_currency' => $payCurrency
                ]);

                return $transaction;
            } else {
                $error = $response->json()['message'] ?? 'Unknown error';
                throw new Exception("NowPayments API error: {$error}");
            }

        } catch (Exception $e) {
            Log::error('NowPayments payment creation failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => $currency,
                'pay_currency' => $payCurrency,
                'error' => $e->getMessage()
            ]);

            // Mark transaction as failed if it was created
            if (isset($transaction)) {
                $transaction->markAsFailed();
            }

            throw $e;
        }
    }

    /**
     * Get payment status from NowPayments
     */
    public function getPaymentStatus(string $paymentId): array
    {
        try {
            $response = $this->makeApiRequest('GET', "/v1/payment/{$paymentId}");

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('Failed to get payment status from NowPayments');
            }
        } catch (Exception $e) {
            Log::error('Failed to get NowPayments status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process webhook notification from NowPayments
     */
    public function processWebhook(array $payload, string $signature): bool
    {
        try {
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid NowPayments webhook signature');
                return false;
            }

            $paymentId = $payload['payment_id'] ?? null;
            $paymentStatus = $payload['payment_status'] ?? null;
            $orderId = $payload['order_id'] ?? null;

            if (!$paymentId || !$paymentStatus || !$orderId) {
                Log::warning('Invalid NowPayments webhook payload', $payload);
                return false;
            }

            // Find the transaction
            $transaction = PaymentTransaction::where('id', $orderId)
                ->where('gateway', 'nowpayments')
                ->where('gateway_transaction_id', $paymentId)
                ->first();

            if (!$transaction) {
                Log::warning('NowPayments webhook: Transaction not found', [
                    'payment_id' => $paymentId,
                    'order_id' => $orderId
                ]);
                return false;
            }

            // Update transaction metadata
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], $payload)
            ]);

            // Process payment status
            switch ($paymentStatus) {
                case 'finished':
                case 'partially_paid':
                    if ($paymentStatus === 'finished' || 
                        ($payload['actually_paid'] ?? 0) >= ($payload['pay_amount'] ?? 0)) {
                        
                        $this->completePayment($transaction, $payload);
                    }
                    break;

                case 'failed':
                case 'refunded':
                case 'expired':
                    $transaction->markAsFailed($paymentStatus);
                    Log::info('NowPayments payment failed', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $paymentId,
                        'status' => $paymentStatus
                    ]);
                    break;

                default:
                    // For waiting, confirming, sending, etc. - just update metadata
                    Log::info('NowPayments payment status update', [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $paymentId,
                        'status' => $paymentStatus
                    ]);
                    break;
            }

            return true;

        } catch (Exception $e) {
            Log::error('NowPayments webhook processing failed', [
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Complete the payment and update user balance
     */
    private function completePayment(PaymentTransaction $transaction, array $payload): void
    {
        if ($transaction->isCompleted()) {
            return; // Already processed
        }

        try {
            $transaction->markAsCompleted();

            // Update user balance through BalanceService
            $balanceService = app(\App\Services\BalanceService::class);
            $balanceService->addCredit(
                $transaction->user,
                $transaction->amount,
                'Payment via NowPayments',
                'payment_transaction',
                $transaction->id
            );

            Log::info('NowPayments payment completed', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
                'payment_id' => $payload['payment_id'] ?? null
            ]);

            // TODO: Send payment confirmation email
            // This will be implemented in task 8.2

        } catch (Exception $e) {
            Log::error('Failed to complete NowPayments payment', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(array $payload, string $signature): bool
    {
        if (!$this->ipnSecret) {
            Log::warning('NowPayments IPN secret not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha512', json_encode($payload, JSON_UNESCAPED_SLASHES), $this->ipnSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get available currencies from NowPayments
     */
    public function getAvailableCurrencies(): array
    {
        try {
            $response = $this->makeApiRequest('GET', '/v1/currencies');

            if ($response->successful()) {
                $currencies = $response->json()['currencies'] ?? [];
                
                // Filter to only supported currencies
                return array_intersect($currencies, $this->supportedCurrencies);
            }

            return $this->supportedCurrencies; // Fallback to config
        } catch (Exception $e) {
            Log::error('Failed to get NowPayments currencies', [
                'error' => $e->getMessage()
            ]);
            return $this->supportedCurrencies; // Fallback to config
        }
    }

    /**
     * Get minimum payment amount for a currency
     */
    public function getMinimumAmount(string $currency): ?float
    {
        try {
            $response = $this->makeApiRequest('GET', "/v1/min-amount?currency_from=USD&currency_to={$currency}");

            if ($response->successful()) {
                return (float) $response->json()['min_amount'];
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get NowPayments minimum amount', [
                'currency' => $currency,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Make API request to NowPayments
     */
    private function makeApiRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->apiUrl . $endpoint;
        
        $headers = [
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        return Http::withHeaders($headers)
            ->timeout(30)
            ->retry(3, 1000)
            ->{strtolower($method)}($url, $data);
    }
}