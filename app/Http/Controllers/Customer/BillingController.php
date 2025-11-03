<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\CallRate;
use App\Models\BalanceTransaction;
use App\Services\RealTimeBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    protected $realTimeBillingService;

    public function __construct(RealTimeBillingService $realTimeBillingService)
    {
        $this->realTimeBillingService = $realTimeBillingService;
    }

    /**
     * Show real-time billing monitor
     */
    public function realtime()
    {
        $user = auth()->user();
        
        // Get active calls
        $activeCalls = CallRecord::where('user_id', $user->id)
            ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->with('callRate')
            ->get();

        // Calculate today's spending
        $todaySpending = BalanceTransaction::where('user_id', $user->id)
            ->whereDate('processed_at', today())
            ->whereIn('type', ['call_charge', 'did_monthly', 'did_setup'])
            ->where('amount', '<', 0)
            ->sum('amount') * -1;

        // Calculate monthly estimate based on daily average
        $dailyAverage = BalanceTransaction::where('user_id', $user->id)
            ->whereMonth('processed_at', now()->month)
            ->whereIn('type', ['call_charge', 'did_monthly', 'did_setup'])
            ->where('amount', '<', 0)
            ->sum('amount') * -1;
        
        $daysInMonth = now()->daysInMonth;
        $daysPassed = now()->day;
        $monthlyEstimate = $daysPassed > 0 ? ($dailyAverage / $daysPassed) * $daysInMonth : 0;

        // Get sample rates for display
        $sampleRates = CallRate::orderBy('rate_per_minute')
            ->limit(5)
            ->get();

        return view('customer.billing.realtime', compact(
            'activeCalls', 
            'todaySpending', 
            'monthlyEstimate', 
            'sampleRates'
        ));
    }

    /**
     * Get real-time billing data via AJAX
     */
    public function realtimeData(): JsonResponse
    {
        $user = auth()->user();
        
        // Get active calls with current costs
        $activeCalls = CallRecord::where('user_id', $user->id)
            ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->get()
            ->map(function ($call) {
                // Calculate current cost based on duration
                $currentCost = $this->realTimeBillingService->calculateCurrentCallCost($call);
                
                return [
                    'call_id' => $call->call_id,
                    'destination' => $call->destination,
                    'start_time' => $call->start_time,
                    'duration' => $call->duration,
                    'cost' => $currentCost,
                    'rate_per_minute' => $call->callRate->rate_per_minute ?? 0,
                    'billing_increment' => $call->callRate->billing_increment ?? 60,
                    'formatted_duration' => $call->getFormattedDuration(),
                    'status' => $call->status
                ];
            });

        // Calculate today's spending
        $todaySpending = BalanceTransaction::where('user_id', $user->id)
            ->whereDate('processed_at', today())
            ->whereIn('type', ['call_charge', 'did_monthly', 'did_setup'])
            ->where('amount', '<', 0)
            ->sum('amount') * -1;

        return response()->json([
            'success' => true,
            'active_calls' => $activeCalls,
            'today_spending' => $todaySpending,
            'current_balance' => $user->balance,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get call cost prediction
     */
    public function predictCost(Request $request): JsonResponse
    {
        $request->validate([
            'destination' => 'required|string',
            'duration_minutes' => 'required|integer|min:1|max:1440' // Max 24 hours
        ]);

        try {
            $rate = CallRate::where('destination_prefix', function($query) use ($request) {
                $destination = preg_replace('/[^0-9]/', '', $request->destination);
                $query->selectRaw('destination_prefix')
                    ->from('call_rates')
                    ->whereRaw('? LIKE CONCAT(destination_prefix, "%")', [$destination])
                    ->orderByRaw('LENGTH(destination_prefix) DESC')
                    ->limit(1);
            })->first();

            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rate found for this destination'
                ], 404);
            }

            $durationMinutes = $request->duration_minutes;
            $durationSeconds = $durationMinutes * 60;
            
            // Apply billing increment
            $billingIncrement = $rate->billing_increment;
            $billedSeconds = ceil($durationSeconds / $billingIncrement) * $billingIncrement;
            
            // Apply minimum duration
            $billedSeconds = max($billedSeconds, $rate->minimum_duration);
            
            $cost = ($rate->rate_per_minute / 60) * $billedSeconds;

            return response()->json([
                'success' => true,
                'prediction' => [
                    'destination' => $rate->destination_name,
                    'rate_per_minute' => $rate->rate_per_minute,
                    'billing_increment' => $rate->billing_increment,
                    'minimum_duration' => $rate->minimum_duration,
                    'requested_duration' => $durationSeconds,
                    'billed_duration' => $billedSeconds,
                    'estimated_cost' => round($cost, 4),
                    'formatted_cost' => '$' . number_format($cost, 4)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate cost prediction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update billing alert preferences
     */
    public function updateAlerts(Request $request): JsonResponse
    {
        $request->validate([
            'setting' => 'required|in:low_balance,call_cost,daily_limit',
            'enabled' => 'required|boolean'
        ]);

        $user = auth()->user();
        $field = 'alert_' . $request->setting;
        
        $user->update([$field => $request->enabled]);

        return response()->json([
            'success' => true,
            'message' => 'Alert preference updated successfully'
        ]);
    }

    /**
     * Get billing breakdown for a specific call
     */
    public function callBreakdown(CallRecord $callRecord): JsonResponse
    {
        $user = auth()->user();

        if ($callRecord->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this call record'
            ], 403);
        }

        $breakdown = $this->realTimeBillingService->getCallBillingBreakdown($callRecord);

        return response()->json([
            'success' => true,
            'breakdown' => $breakdown
        ]);
    }

    /**
     * Export billing data
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,pdf'
        ]);

        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date) : now()->endOfMonth();
        $format = $request->format ?? 'csv';

        // Get billing transactions
        $transactions = BalanceTransaction::where('user_id', $user->id)
            ->whereBetween('processed_at', [$startDate, $endDate])
            ->orderBy('processed_at', 'desc')
            ->get();

        if ($format === 'csv') {
            return $this->exportCsv($transactions, $startDate, $endDate);
        } else {
            return $this->exportPdf($transactions, $startDate, $endDate);
        }
    }

    /**
     * Export billing data as CSV
     */
    private function exportCsv($transactions, $startDate, $endDate)
    {
        $filename = 'billing_data_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'Date',
                'Type',
                'Description',
                'Amount',
                'Balance Before',
                'Balance After',
                'Reference ID'
            ]);

            // CSV data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->processed_at->format('Y-m-d H:i:s'),
                    $transaction->type,
                    $transaction->description,
                    number_format($transaction->amount, 4),
                    number_format($transaction->balance_before, 4),
                    number_format($transaction->balance_after, 4),
                    $transaction->reference_id
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export billing data as PDF
     */
    private function exportPdf($transactions, $startDate, $endDate)
    {
        // This would use a PDF library like DomPDF
        // For now, return a simple response
        return response()->json([
            'success' => false,
            'message' => 'PDF export not yet implemented'
        ], 501);
    }

    /**
     * Get billing statistics
     */
    public function statistics(): JsonResponse
    {
        $user = auth()->user();

        $stats = [
            'current_balance' => $user->balance,
            'today_spending' => BalanceTransaction::where('user_id', $user->id)
                ->whereDate('processed_at', today())
                ->whereIn('type', ['call_charge', 'did_monthly', 'did_setup'])
                ->where('amount', '<', 0)
                ->sum('amount') * -1,
            'this_month_spending' => BalanceTransaction::where('user_id', $user->id)
                ->whereMonth('processed_at', now()->month)
                ->whereIn('type', ['call_charge', 'did_monthly', 'did_setup'])
                ->where('amount', '<', 0)
                ->sum('amount') * -1,
            'total_calls_today' => CallRecord::where('user_id', $user->id)
                ->whereDate('start_time', today())
                ->count(),
            'total_minutes_today' => CallRecord::where('user_id', $user->id)
                ->whereDate('start_time', today())
                ->sum('duration') / 60,
            'active_calls' => CallRecord::where('user_id', $user->id)
                ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
                ->count(),
            'average_call_cost' => CallRecord::where('user_id', $user->id)
                ->whereMonth('start_time', now()->month)
                ->avg('cost')
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }
}