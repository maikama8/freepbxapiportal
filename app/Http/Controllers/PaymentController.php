<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Services\Payment\NowPaymentsGateway;
use App\Services\Payment\PayPalGateway;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class PaymentController extends Controller
{
    private NowPaymentsGateway $nowPaymentsGateway;
    private PayPalGateway $paypalGateway;

    public function __construct(
        NowPaymentsGateway $nowPaymentsGateway,
        PayPalGateway $paypalGateway
    ) {
        $this->nowPaymentsGateway = $nowPaymentsGateway;
        $this->paypalGateway = $paypalGateway;
    }

    /**
     * Get available payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $methods = [
                'paypal' => [
                    'name' => 'PayPal',
                    'type' => 'paypal',
                    'currencies' => ['USD', 'EUR', 'GBP'],
                    'min_amount' => 1.00,
                ],
                'crypto' => [
                    'name' => 'Cryptocurrency',
                    'type' => 'crypto',
                    'currencies' => $this->nowPaymentsGateway->getAvailableCurrencies(),
                    'min_amount' => 0.01,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $methods
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get payment methods', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load payment methods'
            ], 500);
        }
    }

    /**
     * Initiate a payment
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01|max:10000',
            'currency' => 'required|string|in:USD,EUR,GBP',
            'gateway' => 'required|string|in:nowpayments,paypal',
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $amount = $request->input('amount');
            $currency = $request->input('currency');
            $gateway = $request->input('gateway');
            $paymentMethod = $request->input('payment_method');

            // Validate payment method for gateway
            if ($gateway === 'nowpayments') {
                $availableCurrencies = $this->nowPaymentsGateway->getAvailableCurrencies();
                if (!in_array($paymentMethod, $availableCurrencies)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid cryptocurrency selected'
                    ], 422);
                }

                // Check minimum amount for crypto
                $minAmount = $this->nowPaymentsGateway->getMinimumAmount($paymentMethod);
                if ($minAmount && $amount < $minAmount) {
                    return response()->json([
                        'success' => false,
                        'message' => "Minimum amount for {$paymentMethod} is {$minAmount} {$currency}"
                    ], 422);
                }

                $transaction = $this->nowPaymentsGateway->createPayment($user, $amount, $currency, $paymentMethod);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $transaction->gateway_transaction_id,
                        'payment_address' => $transaction->metadata['pay_address'] ?? null,
                        'pay_amount' => $transaction->metadata['pay_amount'] ?? null,
                        'pay_currency' => $paymentMethod,
                        'status' => $transaction->status,
                    ]
                ]);

            } elseif ($gateway === 'paypal') {
                if ($paymentMethod !== 'paypal') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid payment method for PayPal'
                    ], 422);
                }

                $transaction = $this->paypalGateway->createPayment($user, $amount, $currency);
                $approvalUrl = $this->paypalGateway->getApprovalUrl($transaction);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'order_id' => $transaction->gateway_transaction_id,
                        'approval_url' => $approvalUrl,
                        'status' => $transaction->status,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid payment gateway'
            ], 422);

        } catch (Exception $e) {
            Log::error('Payment initiation failed', [
                'user_id' => Auth::id(),
                'amount' => $request->input('amount'),
                'gateway' => $request->input('gateway'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(Request $request, int $transactionId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $transaction = PaymentTransaction::where('id', $transactionId)
                ->where('user_id', $user->id)
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            $data = [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'gateway' => $transaction->gateway,
                'payment_method' => $transaction->payment_method,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at,
                'completed_at' => $transaction->completed_at,
            ];

            // Add gateway-specific data
            if ($transaction->gateway === 'nowpayments') {
                $data['payment_address'] = $transaction->metadata['pay_address'] ?? null;
                $data['pay_amount'] = $transaction->metadata['pay_amount'] ?? null;
                $data['actually_paid'] = $transaction->metadata['actually_paid'] ?? null;
            } elseif ($transaction->gateway === 'paypal') {
                $data['approval_url'] = $this->paypalGateway->getApprovalUrl($transaction);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get payment status', [
                'transaction_id' => $transactionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status'
            ], 500);
        }
    }

    /**
     * Get user's payment history
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 15);
            $status = $request->input('status');

            $query = PaymentTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $transactions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get payment history', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment history'
            ], 500);
        }
    }

    /**
     * Handle PayPal payment success
     */
    public function handlePayPalSuccess(Request $request): JsonResponse
    {
        try {
            $orderId = $request->input('token'); // PayPal returns order ID as 'token'
            
            if (!$orderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing order ID'
                ], 400);
            }

            $success = $this->paypalGateway->processPaymentCompletion($orderId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing failed'
                ], 400);
            }

        } catch (Exception $e) {
            Log::error('PayPal success handling failed', [
                'order_id' => $request->input('token'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing error'
            ], 500);
        }
    }

    /**
     * Handle PayPal payment cancellation
     */
    public function handlePayPalCancel(Request $request): JsonResponse
    {
        $orderId = $request->input('token');
        
        Log::info('PayPal payment cancelled', [
            'order_id' => $orderId
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled'
        ]);
    }

    /**
     * Handle general payment success (for crypto payments)
     */
    public function handlePaymentSuccess(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Payment initiated successfully. Please complete the payment using the provided details.'
        ]);
    }

    /**
     * Handle general payment cancellation
     */
    public function handlePaymentCancel(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled'
        ]);
    }
}
