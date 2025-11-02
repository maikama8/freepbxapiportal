<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get available payment methods
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        try {
            $methods = $this->paymentService->getAvailablePaymentMethods();

            return response()->json([
                'success' => true,
                'data' => $methods
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate a payment
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.01|max:10000',
                'currency' => 'required|string|size:3|in:USD,EUR,GBP',
                'gateway' => 'required|string|in:nowpayments,paypal',
                'payment_method' => 'required|string|max:50',
                'return_url' => 'nullable|url',
                'cancel_url' => 'nullable|url'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Validate payment parameters
            $validationErrors = $this->paymentService->validatePaymentParameters(
                $request->amount,
                $request->currency,
                $request->gateway,
                $request->payment_method
            );

            if (!empty($validationErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment validation failed',
                    'errors' => $validationErrors
                ], 422);
            }

            $transaction = $this->paymentService->createPayment(
                $user,
                $request->amount,
                $request->currency,
                $request->gateway,
                $request->payment_method
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'payment_url' => $transaction->payment_url,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'gateway' => $transaction->gateway,
                    'payment_method' => $transaction->payment_method,
                    'expires_at' => $transaction->expires_at,
                    'created_at' => $transaction->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history for the authenticated user
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'status' => 'string|in:pending,completed,failed,cancelled',
                'gateway' => 'string|in:nowpayments,paypal',
                'date_from' => 'date',
                'date_to' => 'date|after_or_equal:date_from'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $filters = $request->only(['status', 'gateway', 'date_from', 'date_to', 'per_page']);

            $payments = $this->paymentService->getUserPaymentHistory($user, $filters);

            return response()->json([
                'success' => true,
                'data' => [
                    'payments' => $payments->items(),
                    'pagination' => [
                        'current_page' => $payments->currentPage(),
                        'last_page' => $payments->lastPage(),
                        'per_page' => $payments->perPage(),
                        'total' => $payments->total(),
                        'from' => $payments->firstItem(),
                        'to' => $payments->lastItem(),
                        'has_more_pages' => $payments->hasMorePages()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment transaction status
     */
    public function getPaymentStatus(Request $request, int $transactionId): JsonResponse
    {
        try {
            $user = $request->user();
            $transaction = $this->paymentService->getPaymentTransaction($transactionId, $user->id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment transaction not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'gateway' => $transaction->gateway,
                    'payment_method' => $transaction->payment_method,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'payment_url' => $transaction->payment_url,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                    'expires_at' => $transaction->expires_at,
                    'metadata' => $transaction->metadata
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a pending payment
     */
    public function cancelPayment(Request $request, int $transactionId): JsonResponse
    {
        try {
            $user = $request->user();
            $transaction = $this->paymentService->getPaymentTransaction($transactionId, $user->id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment transaction not found'
                ], 404);
            }

            if (!$transaction->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending payments can be cancelled'
                ], 400);
            }

            $success = $this->paymentService->cancelPayment($transaction);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment cancelled successfully',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'status' => $transaction->fresh()->status
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel payment'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry a failed payment
     */
    public function retryPayment(Request $request, int $transactionId): JsonResponse
    {
        try {
            $user = $request->user();
            $originalTransaction = $this->paymentService->getPaymentTransaction($transactionId, $user->id);

            if (!$originalTransaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment transaction not found'
                ], 404);
            }

            if (!$originalTransaction->isFailed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only failed payments can be retried'
                ], 400);
            }

            $newTransaction = $this->paymentService->retryPayment($originalTransaction);

            return response()->json([
                'success' => true,
                'message' => 'Payment retry initiated successfully',
                'data' => [
                    'original_transaction_id' => $originalTransaction->id,
                    'new_transaction_id' => $newTransaction->id,
                    'payment_url' => $newTransaction->payment_url,
                    'status' => $newTransaction->status,
                    'amount' => $newTransaction->amount,
                    'currency' => $newTransaction->currency,
                    'gateway' => $newTransaction->gateway,
                    'expires_at' => $newTransaction->expires_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics for the authenticated user
     */
    public function getPaymentStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $stats = $this->paymentService->getUserPaymentStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get minimum payment amount for a specific gateway and method
     */
    public function getMinimumAmount(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'gateway' => 'required|string|in:nowpayments,paypal',
                'payment_method' => 'required|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $minAmount = $this->paymentService->getMinimumAmount(
                $request->gateway,
                $request->payment_method
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'gateway' => $request->gateway,
                    'payment_method' => $request->payment_method,
                    'minimum_amount' => $minAmount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve minimum amount',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}