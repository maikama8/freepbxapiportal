<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\Payment\PaymentService;
use App\Services\Payment\NowPaymentsGateway;
use App\Services\Payment\PayPalGateway;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected PaymentService $paymentService;
    protected NowPaymentsGateway $nowPaymentsGateway;
    protected PayPalGateway $paypalGateway;

    public function __construct(
        PaymentService $paymentService,
        NowPaymentsGateway $nowPaymentsGateway,
        PayPalGateway $paypalGateway
    ) {
        $this->paymentService = $paymentService;
        $this->nowPaymentsGateway = $nowPaymentsGateway;
        $this->paypalGateway = $paypalGateway;
    }

    /**
     * Handle NowPayments webhook notifications
     */
    public function handleNowPayments(Request $request): JsonResponse
    {
        try {
            Log::info('NowPayments webhook received', [
                'headers' => $request->headers->all(),
                'payload' => $request->all()
            ]);

            // Verify webhook signature
            if (!$this->nowPaymentsGateway->verifyWebhookSignature($request)) {
                Log::warning('NowPayments webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'payload' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook signature'
                ], 401);
            }

            $payload = $request->all();
            
            // Extract transaction information
            $gatewayTransactionId = $payload['payment_id'] ?? null;
            $status = $payload['payment_status'] ?? null;
            $orderId = $payload['order_id'] ?? null;

            if (!$gatewayTransactionId || !$orderId) {
                Log::error('NowPayments webhook missing required fields', [
                    'payload' => $payload
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }

            // Find the transaction
            $transaction = PaymentTransaction::where('gateway_transaction_id', $gatewayTransactionId)
                ->orWhere('id', $orderId)
                ->first();

            if (!$transaction) {
                Log::error('NowPayments webhook: transaction not found', [
                    'gateway_transaction_id' => $gatewayTransactionId,
                    'order_id' => $orderId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Process the webhook based on status
            switch ($status) {
                case 'finished':
                case 'partially_paid':
                    $this->paymentService->completePayment($transaction);
                    break;

                case 'failed':
                case 'refunded':
                case 'expired':
                    $reason = $payload['outcome']['reason'] ?? 'Payment failed';
                    $this->paymentService->failPayment($transaction, $reason);
                    break;

                case 'confirming':
                case 'confirmed':
                case 'sending':
                    // Update transaction with intermediate status
                    $transaction->update([
                        'status' => 'processing',
                        'metadata' => array_merge($transaction->metadata ?? [], [
                            'webhook_status' => $status,
                            'webhook_received_at' => now()->toISOString()
                        ])
                    ]);
                    break;

                default:
                    Log::info('NowPayments webhook: unhandled status', [
                        'status' => $status,
                        'transaction_id' => $transaction->id
                    ]);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('NowPayments webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Handle PayPal webhook notifications
     */
    public function handlePayPal(Request $request): JsonResponse
    {
        try {
            Log::info('PayPal webhook received', [
                'headers' => $request->headers->all(),
                'payload' => $request->all()
            ]);

            // Verify webhook signature
            if (!$this->paypalGateway->verifyWebhookSignature($request)) {
                Log::warning('PayPal webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'payload' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid webhook signature'
                ], 401);
            }

            $payload = $request->all();
            $eventType = $payload['event_type'] ?? null;

            if (!$eventType) {
                Log::error('PayPal webhook missing event_type', [
                    'payload' => $payload
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing event_type'
                ], 400);
            }

            // Extract payment information
            $resource = $payload['resource'] ?? [];
            $paymentId = $resource['id'] ?? null;
            $customId = $resource['custom_id'] ?? null;

            if (!$paymentId && !$customId) {
                Log::error('PayPal webhook missing payment identifiers', [
                    'payload' => $payload
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing payment identifiers'
                ], 400);
            }

            // Find the transaction
            $transaction = null;
            if ($customId) {
                $transaction = PaymentTransaction::find($customId);
            }
            
            if (!$transaction && $paymentId) {
                $transaction = PaymentTransaction::where('gateway_transaction_id', $paymentId)->first();
            }

            if (!$transaction) {
                Log::error('PayPal webhook: transaction not found', [
                    'payment_id' => $paymentId,
                    'custom_id' => $customId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Process the webhook based on event type
            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                case 'CHECKOUT.ORDER.APPROVED':
                    $this->paymentService->completePayment($transaction);
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.DECLINED':
                case 'CHECKOUT.ORDER.VOIDED':
                    $reason = $resource['status_details']['reason'] ?? 'Payment declined';
                    $this->paymentService->failPayment($transaction, $reason);
                    break;

                case 'PAYMENT.CAPTURE.PENDING':
                case 'CHECKOUT.ORDER.PROCESSED':
                    // Update transaction with intermediate status
                    $transaction->update([
                        'status' => 'processing',
                        'metadata' => array_merge($transaction->metadata ?? [], [
                            'webhook_event' => $eventType,
                            'webhook_received_at' => now()->toISOString()
                        ])
                    ]);
                    break;

                default:
                    Log::info('PayPal webhook: unhandled event type', [
                        'event_type' => $eventType,
                        'transaction_id' => $transaction->id
                    ]);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    /**
     * Handle generic payment status updates
     */
    public function handlePaymentStatusUpdate(Request $request): JsonResponse
    {
        try {
            $validator = \Validator::make($request->all(), [
                'transaction_id' => 'required|integer|exists:payment_transactions,id',
                'status' => 'required|string|in:pending,processing,completed,failed,cancelled',
                'gateway_transaction_id' => 'nullable|string',
                'reason' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $transaction = PaymentTransaction::find($request->transaction_id);

            // Update gateway transaction ID if provided
            if ($request->has('gateway_transaction_id')) {
                $transaction->update([
                    'gateway_transaction_id' => $request->gateway_transaction_id
                ]);
            }

            // Process status update
            switch ($request->status) {
                case 'completed':
                    $this->paymentService->completePayment($transaction);
                    break;

                case 'failed':
                case 'cancelled':
                    $reason = $request->reason ?? 'Payment ' . $request->status;
                    $this->paymentService->failPayment($transaction, $reason);
                    break;

                case 'processing':
                    $transaction->update([
                        'status' => 'processing',
                        'metadata' => array_merge($transaction->metadata ?? [], [
                            'status_update_received_at' => now()->toISOString(),
                            'update_reason' => $request->reason
                        ])
                    ]);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'status' => $transaction->fresh()->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Payment status update failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment status update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}