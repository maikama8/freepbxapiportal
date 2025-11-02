<?php

namespace App\Services\Payment;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PayPalGateway
{
    private string $clientId;
    private string $clientSecret;
    private string $apiUrl;
    private bool $sandbox;
    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct()
    {
        $config = config('voip.payments.paypal');
        
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->apiUrl = $config['api_url'];
        $this->sandbox = $config['sandbox'];
    }

    /**
     * Create a PayPal payment order
     */
    public function createPayment(User $user, float $amount, string $currency = 'USD'): PaymentTransaction
    {
        try {
            // Create payment transaction record
            $transaction = PaymentTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => $currency,
                'gateway' => 'paypal',
                'payment_method' => 'paypal',
                'status' => 'pending',
                'metadata' => [
                    'currency' => $currency,
                ]
            ]);

            // Create PayPal order
            $response = $this->makeApiRequest('POST', '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => (string) $transaction->id,
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', '')
                        ],
                        'description' => "VoIP Platform Balance Top-up - User {$user->id}",
                    ]
                ],
                'application_context' => [
                    'return_url' => route('payment.paypal.success'),
                    'cancel_url' => route('payment.paypal.cancel'),
                    'brand_name' => config('voip.company.name', 'VoIP Platform'),
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Update transaction with PayPal order ID
                $transaction->update([
                    'gateway_transaction_id' => $data['id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'order_id' => $data['id'],
                        'status' => $data['status'],
                        'links' => $data['links'] ?? [],
                        'created_time' => $data['create_time'] ?? null,
                    ])
                ]);

                Log::info('PayPal payment order created', [
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'order_id' => $data['id'],
                    'amount' => $amount,
                    'currency' => $currency
                ]);

                return $transaction;
            } else {
                $error = $response->json()['message'] ?? 'Unknown error';
                throw new Exception("PayPal API error: {$error}");
            }

        } catch (Exception $e) {
            Log::error('PayPal payment creation failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => $currency,
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
     * Capture a PayPal payment order
     */
    public function capturePayment(string $orderId): array
    {
        try {
            $response = $this->makeApiRequest('POST', "/v2/checkout/orders/{$orderId}/capture");

            if ($response->successful()) {
                return $response->json();
            } else {
                $error = $response->json()['message'] ?? 'Unknown error';
                throw new Exception("PayPal capture error: {$error}");
            }
        } catch (Exception $e) {
            Log::error('PayPal payment capture failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get PayPal order details
     */
    public function getOrderDetails(string $orderId): array
    {
        try {
            $response = $this->makeApiRequest('GET', "/v2/checkout/orders/{$orderId}");

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new Exception('Failed to get PayPal order details');
            }
        } catch (Exception $e) {
            Log::error('Failed to get PayPal order details', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process PayPal payment completion
     */
    public function processPaymentCompletion(string $orderId): bool
    {
        try {
            // Find the transaction
            $transaction = PaymentTransaction::where('gateway_transaction_id', $orderId)
                ->where('gateway', 'paypal')
                ->first();

            if (!$transaction) {
                Log::warning('PayPal payment completion: Transaction not found', [
                    'order_id' => $orderId
                ]);
                return false;
            }

            if ($transaction->isCompleted()) {
                return true; // Already processed
            }

            // Capture the payment
            $captureData = $this->capturePayment($orderId);
            
            // Update transaction metadata
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'capture_data' => $captureData,
                    'captured_at' => now()->toISOString(),
                ])
            ]);

            // Check capture status
            $captureStatus = $captureData['status'] ?? null;
            
            if ($captureStatus === 'COMPLETED') {
                $this->completePayment($transaction, $captureData);
                return true;
            } else {
                Log::warning('PayPal payment capture not completed', [
                    'transaction_id' => $transaction->id,
                    'order_id' => $orderId,
                    'capture_status' => $captureStatus
                ]);
                return false;
            }

        } catch (Exception $e) {
            Log::error('PayPal payment completion failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process webhook notification from PayPal
     */
    public function processWebhook(array $payload): bool
    {
        try {
            $eventType = $payload['event_type'] ?? null;
            $resource = $payload['resource'] ?? [];

            Log::info('PayPal webhook received', [
                'event_type' => $eventType,
                'resource_id' => $resource['id'] ?? null
            ]);

            switch ($eventType) {
                case 'CHECKOUT.ORDER.APPROVED':
                    return $this->handleOrderApproved($resource);
                
                case 'PAYMENT.CAPTURE.COMPLETED':
                    return $this->handleCaptureCompleted($resource);
                
                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.REFUNDED':
                    return $this->handlePaymentFailed($resource, $eventType);
                
                default:
                    Log::info('PayPal webhook event not handled', [
                        'event_type' => $eventType
                    ]);
                    return true; // Not an error, just not handled
            }

        } catch (Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle PayPal order approved webhook
     */
    private function handleOrderApproved(array $resource): bool
    {
        $orderId = $resource['id'] ?? null;
        
        if (!$orderId) {
            return false;
        }

        $transaction = PaymentTransaction::where('gateway_transaction_id', $orderId)
            ->where('gateway', 'paypal')
            ->first();

        if ($transaction) {
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'approved_at' => now()->toISOString(),
                    'approval_data' => $resource,
                ])
            ]);
        }

        return true;
    }

    /**
     * Handle PayPal capture completed webhook
     */
    private function handleCaptureCompleted(array $resource): bool
    {
        // Extract order ID from the resource
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if (!$orderId) {
            Log::warning('PayPal capture completed webhook missing order ID');
            return false;
        }

        $transaction = PaymentTransaction::where('gateway_transaction_id', $orderId)
            ->where('gateway', 'paypal')
            ->first();

        if (!$transaction) {
            Log::warning('PayPal capture completed: Transaction not found', [
                'order_id' => $orderId
            ]);
            return false;
        }

        if (!$transaction->isCompleted()) {
            $this->completePayment($transaction, $resource);
        }

        return true;
    }

    /**
     * Handle PayPal payment failed webhook
     */
    private function handlePaymentFailed(array $resource, string $eventType): bool
    {
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if (!$orderId) {
            return false;
        }

        $transaction = PaymentTransaction::where('gateway_transaction_id', $orderId)
            ->where('gateway', 'paypal')
            ->first();

        if ($transaction && !$transaction->isCompleted()) {
            $transaction->markAsFailed('failed');
            
            Log::info('PayPal payment failed', [
                'transaction_id' => $transaction->id,
                'order_id' => $orderId,
                'event_type' => $eventType
            ]);
        }

        return true;
    }

    /**
     * Complete the payment and update user balance
     */
    private function completePayment(PaymentTransaction $transaction, array $captureData): void
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
                'Payment via PayPal',
                'payment_transaction',
                $transaction->id
            );

            Log::info('PayPal payment completed', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
                'order_id' => $transaction->gateway_transaction_id
            ]);

            // TODO: Send payment confirmation email
            // This will be implemented in task 8.2

        } catch (Exception $e) {
            Log::error('Failed to complete PayPal payment', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        // Check if we have a valid token
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->apiUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                $this->tokenExpiresAt = time() + ($data['expires_in'] - 60); // 60 second buffer
                
                return $this->accessToken;
            } else {
                throw new Exception('Failed to get PayPal access token');
            }
        } catch (Exception $e) {
            Log::error('PayPal authentication failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Make API request to PayPal
     */
    private function makeApiRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->apiUrl . $endpoint;
        $token = $this->getAccessToken();
        
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        return Http::withHeaders($headers)
            ->timeout(30)
            ->retry(3, 1000)
            ->{strtolower($method)}($url, $data);
    }

    /**
     * Get PayPal approval URL from transaction
     */
    public function getApprovalUrl(PaymentTransaction $transaction): ?string
    {
        $links = $transaction->metadata['links'] ?? [];
        
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        return null;
    }
}