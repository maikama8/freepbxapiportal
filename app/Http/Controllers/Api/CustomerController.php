<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CallRecord;
use App\Services\BalanceService;
use App\Services\FreePBX\CallManagementService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    protected BalanceService $balanceService;
    protected CallManagementService $callService;
    protected PaymentService $paymentService;

    public function __construct(
        BalanceService $balanceService,
        CallManagementService $callService,
        PaymentService $paymentService
    ) {
        $this->balanceService = $balanceService;
        $this->callService = $callService;
        $this->paymentService = $paymentService;
    }

    /**
     * Get customer account balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $balanceStatus = $this->balanceService->getBalanceStatus($user);

            return response()->json([
                'success' => true,
                'data' => $balanceStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve balance information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer call history
     */
    public function getCallHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'date_from' => 'date',
                'date_to' => 'date|after_or_equal:date_from',
                'status' => 'string|in:initiated,ringing,answered,completed,failed,cancelled',
                'destination' => 'string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $perPage = $request->get('per_page', 15);

            $query = CallRecord::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('date_from')) {
                $query->where('start_time', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('start_time', '<=', $request->date_to . ' 23:59:59');
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('destination')) {
                $query->where('destination', 'like', '%' . $request->destination . '%');
            }

            $calls = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'calls' => $calls->items(),
                    'pagination' => [
                        'current_page' => $calls->currentPage(),
                        'last_page' => $calls->lastPage(),
                        'per_page' => $calls->perPage(),
                        'total' => $calls->total(),
                        'from' => $calls->firstItem(),
                        'to' => $calls->lastItem()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve call history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate a new call
     */
    public function initiateCall(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'destination' => 'required|string|max:50',
                'caller_id' => 'nullable|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Check if user has sufficient balance
            if (!$this->balanceService->hasSufficientBalance($user, 1.0)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance to initiate call',
                    'data' => [
                        'current_balance' => $user->balance,
                        'available_balance' => $this->balanceService->getAvailableBalance($user)
                    ]
                ], 402);
            }

            $result = $this->callService->initiateCall(
                $user,
                $request->destination,
                $request->caller_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Call initiated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminate an active call
     */
    public function terminateCall(Request $request, string $callId): JsonResponse
    {
        try {
            $user = $request->user();

            // Verify the call belongs to the user
            $callRecord = CallRecord::where('call_id', $callId)
                ->where('user_id', $user->id)
                ->first();

            if (!$callRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Call not found or access denied'
                ], 404);
            }

            $result = $this->callService->terminateCall($callId);

            return response()->json([
                'success' => true,
                'message' => 'Call terminated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to terminate call',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call status
     */
    public function getCallStatus(Request $request, string $callId): JsonResponse
    {
        try {
            $user = $request->user();

            // Verify the call belongs to the user
            $callRecord = CallRecord::where('call_id', $callId)
                ->where('user_id', $user->id)
                ->first();

            if (!$callRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Call not found or access denied'
                ], 404);
            }

            $status = $this->callService->getCallStatus($callId);

            return response()->json([
                'success' => true,
                'data' => [
                    'call_record' => $callRecord,
                    'live_status' => $status
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get call status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active calls for the user
     */
    public function getActiveCalls(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $activeCalls = $this->callService->getActiveCalls($user);

            return response()->json([
                'success' => true,
                'data' => $activeCalls
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active calls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment methods available to the customer
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
                'payment_method' => 'required|string|max:50'
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
                    'expires_at' => $transaction->expires_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history
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
                        'to' => $payments->lastItem()
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
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                    'expires_at' => $transaction->expires_at
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
     * Get customer account information
     */
    public function getAccountInfo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $balanceStatus = $this->balanceService->getBalanceStatus($user);
            $paymentStats = $this->paymentService->getUserPaymentStats($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'account_type' => $user->account_type,
                        'status' => $user->status,
                        'timezone' => $user->timezone,
                        'currency' => $user->currency,
                        'sip_username' => $user->sip_username,
                        'extension' => $user->extension,
                        'created_at' => $user->created_at
                    ],
                    'balance' => $balanceStatus,
                    'payment_stats' => $paymentStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve account information',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}