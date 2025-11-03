<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RealTimeBillingService;
use App\Services\CallTerminationService;
use App\Models\CallRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RealTimeBillingController extends Controller
{
    protected $realTimeBillingService;
    protected $callTerminationService;

    public function __construct(
        RealTimeBillingService $realTimeBillingService,
        CallTerminationService $callTerminationService
    ) {
        $this->realTimeBillingService = $realTimeBillingService;
        $this->callTerminationService = $callTerminationService;
    }

    /**
     * Get real-time billing dashboard data
     */
    public function dashboard()
    {
        try {
            $activeCalls = $this->realTimeBillingService->getActiveCallsWithBilling();
            $stats = $this->realTimeBillingService->getRealTimeBillingStats();
            $terminationStats = $this->callTerminationService->getTerminationStats();

            return response()->json([
                'success' => true,
                'data' => [
                    'active_calls' => $activeCalls,
                    'billing_stats' => $stats,
                    'termination_stats' => $terminationStats,
                    'calls_at_risk' => array_filter($activeCalls, function ($call) {
                        return $call['user_balance'] < $call['current_cost'];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active calls with real-time billing
     */
    public function getActiveCalls()
    {
        try {
            $activeCalls = $this->realTimeBillingService->getActiveCallsWithBilling();
            
            return response()->json([
                'success' => true,
                'data' => $activeCalls
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get active calls: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process periodic billing for all active calls
     */
    public function processPeriodicBilling()
    {
        try {
            $activeCalls = $this->realTimeBillingService->getActiveCallsWithBilling();
            $processed = 0;
            $failed = 0;

            foreach ($activeCalls as $callData) {
                $callRecord = $callData['call_record'];
                if ($this->realTimeBillingService->processPeriodicBilling($callRecord)) {
                    $processed++;
                } else {
                    $failed++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Processed billing for {$processed} calls" . ($failed > 0 ? ", {$failed} failed" : ''),
                'processed' => $processed,
                'failed' => $failed
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process periodic billing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminate a specific call
     */
    public function terminateCall(Request $request, CallRecord $callRecord)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $reason = $request->input('reason', 'Manual termination by admin');
            
            $terminated = $this->callTerminationService->terminateForInsufficientBalance($callRecord, $reason);
            
            return response()->json([
                'success' => $terminated,
                'message' => $terminated ? 'Call terminated successfully' : 'Failed to terminate call'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to terminate call: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Emergency terminate a call
     */
    public function emergencyTerminate(Request $request, CallRecord $callRecord)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $reason = $request->input('reason', 'Emergency termination by admin');
            
            $terminated = $this->callTerminationService->emergencyTerminate($callRecord, $reason);
            
            return response()->json([
                'success' => $terminated,
                'message' => $terminated ? 'Call emergency terminated successfully' : 'Failed to emergency terminate call'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to emergency terminate call: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminate all calls with insufficient balance
     */
    public function terminateInsufficientBalanceCalls()
    {
        try {
            $terminated = $this->callTerminationService->checkAndTerminateInsufficientBalanceCalls();
            
            return response()->json([
                'success' => true,
                'message' => "Terminated {$terminated} calls due to insufficient balance",
                'terminated_count' => $terminated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to terminate calls: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminate all calls for a specific user
     */
    public function terminateUserCalls(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = \App\Models\User::findOrFail($request->user_id);
            $reason = $request->input('reason', 'Admin terminated all user calls');
            
            $terminated = $this->callTerminationService->terminateAllUserCalls($user, $reason);
            
            return response()->json([
                'success' => true,
                'message' => "Terminated {$terminated} calls for user {$user->name}",
                'terminated_count' => $terminated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to terminate user calls: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time billing statistics
     */
    public function getStatistics()
    {
        try {
            $billingStats = $this->realTimeBillingService->getRealTimeBillingStats();
            $terminationStats = $this->callTerminationService->getTerminationStats();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'billing' => $billingStats,
                    'termination' => $terminationStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start real-time billing for a call
     */
    public function startBilling(CallRecord $callRecord)
    {
        try {
            $started = $this->realTimeBillingService->startRealTimeBilling($callRecord);
            
            return response()->json([
                'success' => $started,
                'message' => $started ? 'Real-time billing started' : 'Failed to start real-time billing'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start billing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize billing for a call
     */
    public function finalizeBilling(CallRecord $callRecord)
    {
        try {
            $finalized = $this->realTimeBillingService->finalizeBilling($callRecord);
            
            return response()->json([
                'success' => $finalized,
                'message' => $finalized ? 'Billing finalized successfully' : 'Failed to finalize billing'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize billing: ' . $e->getMessage()
            ], 500);
        }
    }
}