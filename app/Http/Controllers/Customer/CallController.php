<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\FreePBX\CallManagementService;
use App\Services\BillingService;
use App\Models\CallRecord;
use App\Models\CallRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class CallController extends Controller
{
    protected CallManagementService $callService;
    protected BillingService $billingService;

    public function __construct(CallManagementService $callService, BillingService $billingService)
    {
        $this->callService = $callService;
        $this->billingService = $billingService;
    }

    /**
     * Show the call initiation interface
     */
    public function makeCall(): View
    {
        $user = Auth::user();
        
        // Get recent destinations for quick dial
        $recentDestinations = $user->callRecords()
            ->select('destination')
            ->distinct()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->pluck('destination');
        
        // Get active calls
        $activeCalls = $user->callRecords()
            ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('customer.calls.make', compact('recentDestinations', 'activeCalls'));
    }

    /**
     * Initiate a new call
     */
    public function initiateCall(Request $request): JsonResponse
    {
        $request->validate([
            'destination' => 'required|string|min:3|max:20',
            'caller_id' => 'nullable|string|max:20',
        ]);

        $user = Auth::user();
        
        try {
            // Check if user account is active
            if (!$user->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is not active. Please contact support.'
                ], 400);
            }

            // Get call rate for destination
            $rate = $this->billingService->getCallRate($request->destination);
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rate found for this destination.'
                ], 400);
            }

            // Check if user has sufficient balance for minimum call duration
            $minimumCost = $this->billingService->calculateCallCost(
                $request->destination, 
                $rate->minimum_duration ?? 60
            );

            if (!$user->hasSufficientBalance($minimumCost)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance. Minimum required: $' . number_format($minimumCost, 4),
                    'required_amount' => $minimumCost,
                    'current_balance' => $user->balance
                ], 400);
            }

            // Initiate the call
            $result = $this->callService->initiateCall(
                $user,
                $request->destination,
                $request->caller_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Call initiated successfully',
                'call_id' => $result['call_id'],
                'call_record_id' => $result['call_record_id'],
                'estimated_cost_per_minute' => $rate->rate_per_minute
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate call: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminate an active call
     */
    public function hangupCall(Request $request, string $callId): JsonResponse
    {
        $user = Auth::user();
        
        // Verify the call belongs to the user
        $callRecord = $user->callRecords()->where('call_id', $callId)->first();
        if (!$callRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Call not found or access denied.'
            ], 404);
        }

        try {
            $result = $this->callService->terminateCall($callId);
            
            // Process billing for the completed call
            $this->billingService->processCallBilling($callRecord);

            return response()->json([
                'success' => true,
                'message' => 'Call terminated successfully',
                'call_id' => $callId
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to terminate call: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call status
     */
    public function getCallStatus(string $callId): JsonResponse
    {
        $user = Auth::user();
        
        // Verify the call belongs to the user
        $callRecord = $user->callRecords()->where('call_id', $callId)->first();
        if (!$callRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Call not found or access denied.'
            ], 404);
        }

        try {
            $status = $this->callService->getCallStatus($callId);
            
            // Refresh call record from database
            $callRecord->refresh();
            
            return response()->json([
                'success' => true,
                'call_id' => $callId,
                'status' => $status,
                'call_record' => [
                    'id' => $callRecord->id,
                    'destination' => $callRecord->destination,
                    'start_time' => $callRecord->start_time,
                    'duration' => $callRecord->getDurationInSeconds(),
                    'formatted_duration' => $callRecord->getFormattedDuration(),
                    'cost' => $callRecord->cost,
                    'status' => $callRecord->status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get call status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active calls for monitoring
     */
    public function getActiveCalls(): JsonResponse
    {
        $user = Auth::user();
        
        try {
            // Get active calls from FreePBX
            $freepbxCalls = $this->callService->getActiveCalls($user);
            
            // Get local call records
            $localCalls = $user->callRecords()
                ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($call) {
                    return [
                        'id' => $call->id,
                        'call_id' => $call->call_id,
                        'destination' => $call->destination,
                        'caller_id' => $call->caller_id,
                        'start_time' => $call->start_time,
                        'duration' => $call->getDurationInSeconds(),
                        'formatted_duration' => $call->getFormattedDuration(),
                        'status' => $call->status,
                        'cost' => $call->cost
                    ];
                });

            return response()->json([
                'success' => true,
                'active_calls' => $localCalls,
                'freepbx_calls' => $freepbxCalls,
                'count' => $localCalls->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get active calls: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call rate for destination
     */
    public function getCallRate(Request $request): JsonResponse
    {
        $request->validate([
            'destination' => 'required|string|min:3|max:20',
        ]);

        try {
            $rate = $this->billingService->getCallRate($request->destination);
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rate found for this destination.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'rate' => [
                    'destination_prefix' => $rate->destination_prefix,
                    'destination_name' => $rate->destination_name,
                    'rate_per_minute' => $rate->rate_per_minute,
                    'minimum_duration' => $rate->minimum_duration,
                    'billing_increment' => $rate->billing_increment,
                    'formatted_rate' => '$' . number_format($rate->rate_per_minute, 4) . '/min'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get call rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show active call monitoring panel
     */
    public function monitorCalls(): View
    {
        $user = Auth::user();
        
        $activeCalls = $user->callRecords()
            ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('customer.calls.monitor', compact('activeCalls'));
    }
}