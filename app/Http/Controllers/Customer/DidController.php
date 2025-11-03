<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DidNumber;
use App\Models\CountryRate;
use App\Models\BalanceTransaction;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DidController extends Controller
{
    /**
     * Display customer's DID numbers
     */
    public function index()
    {
        $user = auth()->user();
        $assignedDids = DidNumber::where('user_id', $user->id)
            ->with('countryRate')
            ->orderBy('assigned_at', 'desc')
            ->get();

        return view('customer.dids.index', compact('assignedDids'));
    }

    /**
     * Show available DID numbers for purchase
     */
    public function browse(Request $request)
    {
        $query = DidNumber::available()->with('countryRate');

        // Filter by country
        if ($request->filled('country_code')) {
            $query->where('country_code', $request->country_code);
        }

        // Filter by area code
        if ($request->filled('area_code')) {
            $query->where('area_code', $request->area_code);
        }

        // Filter by features
        if ($request->filled('features')) {
            $features = is_array($request->features) ? $request->features : [$request->features];
            $query->where(function($q) use ($features) {
                foreach ($features as $feature) {
                    $q->whereJsonContains('features', $feature);
                }
            });
        }

        // Price range filter
        if ($request->filled('min_monthly_cost')) {
            $query->where('monthly_cost', '>=', $request->min_monthly_cost);
        }
        if ($request->filled('max_monthly_cost')) {
            $query->where('monthly_cost', '<=', $request->max_monthly_cost);
        }

        $availableDids = $query->orderBy('monthly_cost')->paginate(20);
        $countries = CountryRate::active()->orderBy('country_name')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'dids' => $availableDids,
                'countries' => $countries
            ]);
        }

        return view('customer.dids.browse', compact('availableDids', 'countries'));
    }

    /**
     * Purchase a DID number
     */
    public function purchase(Request $request, DidNumber $didNumber): JsonResponse
    {
        $user = auth()->user();

        if ($didNumber->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'This DID number is no longer available'
            ], 400);
        }

        // Calculate total cost (setup + first month)
        $totalCost = $didNumber->setup_cost + $didNumber->monthly_cost;

        if ($user->balance < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance. You need $' . number_format($totalCost, 2) . ' but have $' . number_format($user->balance, 2),
                'required_amount' => $totalCost,
                'current_balance' => $user->balance
            ], 400);
        }

        try {
            DB::beginTransaction();

            $balanceService = app(BalanceService::class);

            // Charge setup cost
            if ($didNumber->setup_cost > 0) {
                $balanceService->deductBalance($user->id, $didNumber->setup_cost, 'did_setup', [
                    'did_number' => $didNumber->did_number,
                    'setup_cost' => $didNumber->setup_cost
                ]);
            }

            // Charge first month
            if ($didNumber->monthly_cost > 0) {
                $balanceService->deductBalance($user->id, $didNumber->monthly_cost, 'did_monthly', [
                    'did_number' => $didNumber->did_number,
                    'monthly_cost' => $didNumber->monthly_cost,
                    'billing_period' => now()->format('Y-m')
                ]);
            }

            // Assign DID to user
            $didNumber->assignToUser($user);

            // Set expiry date (1 month from now)
            $didNumber->update([
                'expires_at' => now()->addMonth()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'DID number purchased successfully!',
                'data' => [
                    'did_number' => $didNumber->formatted_number,
                    'total_cost' => $totalCost,
                    'new_balance' => $user->fresh()->balance,
                    'expires_at' => $didNumber->expires_at->format('M d, Y')
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase DID number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release a DID number
     */
    public function release(DidNumber $didNumber): JsonResponse
    {
        $user = auth()->user();

        if ($didNumber->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this DID number'
            ], 403);
        }

        if ($didNumber->status !== 'assigned') {
            return response()->json([
                'success' => false,
                'message' => 'This DID number is not currently assigned'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Calculate prorated refund if applicable
            $refundAmount = 0;
            if ($didNumber->expires_at && $didNumber->expires_at->isFuture()) {
                $daysRemaining = now()->diffInDays($didNumber->expires_at);
                $daysInMonth = now()->daysInMonth;
                $refundAmount = ($daysRemaining / $daysInMonth) * $didNumber->monthly_cost;
                
                if ($refundAmount > 0.01) { // Only refund if more than 1 cent
                    $balanceService = app(BalanceService::class);
                    $balanceService->addBalance($user->id, $refundAmount, 'did_refund', [
                        'did_number' => $didNumber->did_number,
                        'days_remaining' => $daysRemaining,
                        'prorated_refund' => $refundAmount
                    ]);
                }
            }

            // Release the DID
            $didNumber->release();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'DID number released successfully' . ($refundAmount > 0 ? '. Prorated refund of $' . number_format($refundAmount, 2) . ' has been added to your balance.' : ''),
                'refund_amount' => $refundAmount,
                'new_balance' => $user->fresh()->balance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to release DID number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DID billing history
     */
    public function billingHistory(DidNumber $didNumber): JsonResponse
    {
        $user = auth()->user();

        if ($didNumber->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this DID number'
            ], 403);
        }

        $transactions = BalanceTransaction::where('user_id', $user->id)
            ->where('reference_id', $didNumber->id)
            ->where('reference_type', 'did_number')
            ->orderBy('processed_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'transactions' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'amount' => $transaction->formatted_amount,
                    'type' => $transaction->type_description,
                    'description' => $transaction->description,
                    'processed_at' => $transaction->processed_at->format('M d, Y H:i'),
                    'balance_after' => $transaction->formatted_balance_after
                ];
            })
        ]);
    }

    /**
     * Get DID statistics for customer
     */
    public function statistics(): JsonResponse
    {
        $user = auth()->user();

        $stats = [
            'total_dids' => DidNumber::where('user_id', $user->id)->count(),
            'active_dids' => DidNumber::where('user_id', $user->id)->where('status', 'assigned')->count(),
            'monthly_cost' => DidNumber::where('user_id', $user->id)->where('status', 'assigned')->sum('monthly_cost'),
            'expiring_soon' => DidNumber::where('user_id', $user->id)
                ->where('status', 'assigned')
                ->where('expires_at', '<=', now()->addDays(7))
                ->count(),
            'by_country' => DidNumber::where('user_id', $user->id)
                ->select('country_code', DB::raw('count(*) as count'))
                ->groupBy('country_code')
                ->with('countryRate:country_code,country_name')
                ->get()
        ];

        return response()->json($stats);
    }

    /**
     * Renew DID number
     */
    public function renew(DidNumber $didNumber): JsonResponse
    {
        $user = auth()->user();

        if ($didNumber->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this DID number'
            ], 403);
        }

        if ($didNumber->status !== 'assigned') {
            return response()->json([
                'success' => false,
                'message' => 'This DID number is not currently assigned'
            ], 400);
        }

        if ($user->balance < $didNumber->monthly_cost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance for renewal. Required: $' . number_format($didNumber->monthly_cost, 2),
                'required_amount' => $didNumber->monthly_cost,
                'current_balance' => $user->balance
            ], 400);
        }

        try {
            DB::beginTransaction();

            $balanceService = app(BalanceService::class);

            // Charge monthly cost
            $balanceService->deductBalance($user->id, $didNumber->monthly_cost, 'did_monthly', [
                'did_number' => $didNumber->did_number,
                'monthly_cost' => $didNumber->monthly_cost,
                'billing_period' => now()->format('Y-m'),
                'renewal' => true
            ]);

            // Extend expiry date
            $newExpiryDate = $didNumber->expires_at && $didNumber->expires_at->isFuture() 
                ? $didNumber->expires_at->addMonth()
                : now()->addMonth();

            $didNumber->update(['expires_at' => $newExpiryDate]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'DID number renewed successfully!',
                'data' => [
                    'did_number' => $didNumber->formatted_number,
                    'cost' => $didNumber->monthly_cost,
                    'new_balance' => $user->fresh()->balance,
                    'expires_at' => $newExpiryDate->format('M d, Y')
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to renew DID number: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show DID configuration page
     */
    public function configure(DidNumber $didNumber)
    {
        $user = auth()->user();

        if ($didNumber->user_id !== $user->id) {
            abort(403, 'You do not own this DID number');
        }

        // Get user's SIP extensions
        $userExtensions = $user->sipAccounts()->where('status', 'active')->get();

        // Get usage statistics for this DID
        $usageStats = $this->getDidUsageStats($didNumber);

        return view('customer.dids.configure', compact('didNumber', 'userExtensions', 'usageStats'));
    }

    /**
     * Update DID configuration
     */
    public function updateConfiguration(Request $request, DidNumber $didNumber): JsonResponse
    {
        $user = auth()->user();

        if ($didNumber->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this DID number'
            ], 403);
        }

        $request->validate([
            'assigned_extension' => 'nullable|string|max:20',
            'call_routing' => 'required|in:direct,voicemail,ivr,forward',
            'forward_number' => 'nullable|string|max:20',
            'voicemail_enabled' => 'boolean',
            'voicemail_email' => 'boolean',
            'recording_mode' => 'required|in:disabled,inbound,outbound,both',
            'business_hours_enabled' => 'boolean',
        ]);

        try {
            // Update DID configuration
            $config = $didNumber->metadata ?? [];
            
            $config['call_routing'] = $request->call_routing;
            $config['forward_number'] = $request->forward_number;
            $config['forward_on_busy'] = $request->boolean('forward_on_busy');
            $config['voicemail_enabled'] = $request->boolean('voicemail_enabled');
            $config['voicemail_email'] = $request->boolean('voicemail_email');
            $config['recording_mode'] = $request->recording_mode;
            $config['recording_announcement'] = $request->boolean('recording_announcement');
            $config['business_hours_enabled'] = $request->boolean('business_hours_enabled');
            
            if ($request->business_hours_enabled) {
                $config['business_hours'] = [
                    'start' => $request->business_start,
                    'end' => $request->business_end,
                    'timezone' => $request->timezone,
                    'after_hours_action' => $request->after_hours_action
                ];
            }

            $didNumber->update([
                'assigned_extension' => $request->assigned_extension,
                'metadata' => $config
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DID configuration updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show DID transfer page
     */
    public function showTransfer(DidNumber $didNumber)
    {
        $user = auth()->user();

        if ($didNumber->user_id !== $user->id) {
            abort(403, 'You do not own this DID number');
        }

        if ($didNumber->status !== 'assigned') {
            return redirect()->route('customer.dids.index')
                ->with('error', 'Only active DID numbers can be transferred');
        }

        // Calculate prorated amount
        $proratedAmount = 0;
        if ($didNumber->expires_at && $didNumber->expires_at->isFuture()) {
            $daysRemaining = now()->diffInDays($didNumber->expires_at);
            $daysInMonth = now()->daysInMonth;
            $proratedAmount = ($daysRemaining / $daysInMonth) * $didNumber->monthly_cost;
        }

        return view('customer.dids.transfer', compact('didNumber', 'proratedAmount'));
    }

    /**
     * Verify recipient for DID transfer
     */
    public function verifyRecipient(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $recipient = \App\Models\User::where('email', $request->email)
            ->where('role', 'customer')
            ->first();

        if (!$recipient) {
            return response()->json([
                'success' => false,
                'message' => 'No customer account found with this email address'
            ], 404);
        }

        if ($recipient->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot transfer a DID to yourself'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'name' => $recipient->name,
                'email' => $recipient->email,
                'account_type' => $recipient->account_type
            ]
        ]);
    }

    /**
     * Transfer DID to another user
     */
    public function transfer(Request $request, DidNumber $didNumber): JsonResponse
    {
        $user = auth()->user();

        if ($didNumber->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this DID number'
            ], 403);
        }

        if ($didNumber->status !== 'assigned') {
            return response()->json([
                'success' => false,
                'message' => 'Only active DID numbers can be transferred'
            ], 400);
        }

        $request->validate([
            'transfer_method' => 'required|in:email,code',
            'recipient_email' => 'required_if:transfer_method,email|email',
            'transfer_balance' => 'boolean',
            'transfer_settings' => 'boolean',
            'notify_recipient' => 'boolean',
            'transfer_message' => 'nullable|string|max:500'
        ]);

        // Check transfer fee
        $transferFee = 5.00;
        if ($user->balance < $transferFee) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance for transfer fee. Required: $' . number_format($transferFee, 2),
                'required_amount' => $transferFee,
                'current_balance' => $user->balance
            ], 400);
        }

        try {
            DB::beginTransaction();

            $balanceService = app(BalanceService::class);

            // Charge transfer fee
            $balanceService->deductBalance($user->id, $transferFee, 'did_transfer_fee', [
                'did_number' => $didNumber->did_number,
                'transfer_fee' => $transferFee
            ]);

            if ($request->transfer_method === 'email') {
                // Direct transfer to email
                $recipient = \App\Models\User::where('email', $request->recipient_email)->first();
                
                if (!$recipient) {
                    throw new \Exception('Recipient not found');
                }

                // Transfer DID
                $this->performDidTransfer($didNumber, $recipient, $request);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'DID transferred successfully to ' . $recipient->name
                ]);
            } else {
                // Generate transfer code
                $transferCode = strtoupper(substr(md5(uniqid()), 0, 8));
                
                // Store transfer request
                \App\Models\DidTransfer::create([
                    'did_number_id' => $didNumber->id,
                    'from_user_id' => $user->id,
                    'transfer_code' => $transferCode,
                    'transfer_data' => [
                        'transfer_balance' => $request->boolean('transfer_balance'),
                        'transfer_settings' => $request->boolean('transfer_settings'),
                        'message' => $request->transfer_message
                    ],
                    'expires_at' => now()->addDay()
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Transfer code generated successfully',
                    'transfer_code' => $transferCode
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DID usage statistics
     */
    private function getDidUsageStats(DidNumber $didNumber): array
    {
        $callRecords = \App\Models\CallRecord::where('caller_id', $didNumber->did_number)
            ->orWhere('destination', $didNumber->did_number);

        return [
            'total_calls' => $callRecords->count(),
            'total_minutes' => $callRecords->sum('duration'),
            'monthly_calls' => $callRecords->whereMonth('start_time', now()->month)->count(),
            'avg_duration' => $callRecords->avg('duration') ? 
                gmdate('i:s', $callRecords->avg('duration')) : '0:00'
        ];
    }

    /**
     * Perform the actual DID transfer
     */
    private function performDidTransfer(DidNumber $didNumber, \App\Models\User $recipient, $request): void
    {
        // Calculate prorated refund/transfer
        $proratedAmount = 0;
        if ($request->boolean('transfer_balance') && $didNumber->expires_at && $didNumber->expires_at->isFuture()) {
            $daysRemaining = now()->diffInDays($didNumber->expires_at);
            $daysInMonth = now()->daysInMonth;
            $proratedAmount = ($daysRemaining / $daysInMonth) * $didNumber->monthly_cost;
        }

        // Transfer balance if requested
        if ($proratedAmount > 0) {
            $balanceService = app(BalanceService::class);
            
            // Deduct from current owner
            $balanceService->deductBalance(auth()->id(), $proratedAmount, 'did_transfer_out', [
                'did_number' => $didNumber->did_number,
                'recipient' => $recipient->email,
                'prorated_amount' => $proratedAmount
            ]);
            
            // Add to recipient
            $balanceService->addBalance($recipient->id, $proratedAmount, 'did_transfer_in', [
                'did_number' => $didNumber->did_number,
                'sender' => auth()->user()->email,
                'prorated_amount' => $proratedAmount
            ]);
        }

        // Prepare transfer data
        $transferData = [
            'previous_owner' => auth()->user()->email,
            'transfer_date' => now(),
            'transfer_message' => $request->transfer_message
        ];

        // Keep settings if requested
        if (!$request->boolean('transfer_settings')) {
            $transferData['previous_settings'] = $didNumber->metadata;
            $didNumber->metadata = null;
            $didNumber->assigned_extension = null;
        }

        // Transfer the DID
        $didNumber->update([
            'user_id' => $recipient->id,
            'metadata' => array_merge($didNumber->metadata ?? [], $transferData)
        ]);

        // Send notification if requested
        if ($request->boolean('notify_recipient')) {
            // TODO: Send email notification to recipient
        }
    }
}