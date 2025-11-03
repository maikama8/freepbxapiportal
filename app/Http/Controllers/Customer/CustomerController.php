<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\BalanceTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Show the customer dashboard
     */
    public function dashboard(): View
    {
        $user = Auth::user()->load(['sipAccounts', 'primarySipAccount']);
        
        // Get recent call records (last 10)
        $recentCalls = $user->callRecords()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Get recent balance transactions (last 10)
        $recentTransactions = $user->balanceTransactions()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Calculate statistics
        $stats = [
            'total_calls_today' => $user->callRecords()
                ->whereDate('created_at', today())
                ->count(),
            'total_spent_today' => $user->callRecords()
                ->whereDate('created_at', today())
                ->sum('cost'),
            'total_calls_month' => $user->callRecords()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'total_spent_month' => $user->callRecords()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('cost'),
            'active_calls' => $user->callRecords()
                ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
                ->count(),
        ];
        
        return view('customer.dashboard', compact('user', 'recentCalls', 'recentTransactions', 'stats'));
    }

    /**
     * Show call history with filtering
     */
    public function callHistory(Request $request): View
    {
        $user = Auth::user();
        
        $query = $user->callRecords()->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('destination')) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }
        
        $calls = $query->paginate(20);
        
        // Get unique statuses for filter dropdown
        $statuses = $user->callRecords()
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->sort();
        
        return view('customer.call-history', compact('calls', 'statuses'));
    }

    /**
     * Show account settings
     */
    public function accountSettings(): View
    {
        $user = Auth::user()->load('sipAccounts');
        return view('customer.account-settings', compact('user'));
    }

    /**
     * Update account settings
     */
    public function updateAccountSettings(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'timezone' => 'required|string|max:50',
            'currency' => 'required|string|max:3',
        ]);
        
        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'timezone' => $request->timezone,
            'currency' => $request->currency,
        ]);
        
        return redirect()->route('customer.account-settings')
            ->with('success', 'Account settings updated successfully.');
    }

    /**
     * Show balance transactions
     */
    public function balanceHistory(Request $request): View
    {
        $user = Auth::user();
        
        $query = $user->balanceTransactions()->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        $transactions = $query->paginate(20);
        
        return view('customer.balance-history', compact('transactions'));
    }

    /**
     * Update caller ID settings
     */
    public function updateCallerId(Request $request)
    {
        $request->validate([
            'caller_id' => 'required|string|max:20',
            'caller_name' => 'nullable|string|max:50'
        ]);

        $user = Auth::user();
        
        // Validate that the caller ID belongs to the user (either their DID or extension)
        $isValidCallerId = false;
        
        // Check if it's one of their DID numbers
        if ($user->didNumbers) {
            $didNumber = $user->didNumbers()->where('did_number', $request->caller_id)->first();
            if ($didNumber) {
                $isValidCallerId = true;
            }
        }
        
        // Check if it's one of their extensions
        $sipAccount = $user->sipAccounts()->where('sip_username', $request->caller_id)->first();
        if ($sipAccount) {
            $isValidCallerId = true;
        }

        if (!$isValidCallerId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid caller ID. You can only use your assigned DID numbers or extensions.'
            ], 422);
        }

        try {
            $user->update([
                'caller_id' => $request->caller_id,
                'caller_name' => $request->caller_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Caller ID updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update caller ID: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available caller IDs for the user
     */
    public function getAvailableCallerIds()
    {
        $user = Auth::user();
        $callerIds = [];

        // Add DID numbers if the relationship exists
        if (method_exists($user, 'didNumbers')) {
            $didNumbers = $user->didNumbers()->where('status', 'active')->get();
            foreach ($didNumbers as $did) {
                $callerIds[] = [
                    'id' => $did->did_number,
                    'number' => $did->formatted_number ?? $did->did_number,
                    'type' => 'DID',
                    'description' => "DID Number ({$did->country_code})"
                ];
            }
        }

        // Add extensions
        $sipAccounts = $user->sipAccounts()->get();
        foreach ($sipAccounts as $sip) {
            $callerIds[] = [
                'id' => $sip->sip_username,
                'number' => $sip->sip_username,
                'type' => 'Extension',
                'description' => $sip->is_primary ? 'Primary Extension' : 'Extension'
            ];
        }

        return response()->json([
            'success' => true,
            'caller_ids' => $callerIds,
            'current_caller_id' => $user->caller_id,
            'current_caller_name' => $user->caller_name
        ]);
    }

    /**
     * Get user's SIP account details
     */
    public function getSipAccounts()
    {
        $user = Auth::user();
        
        $sipAccounts = $user->sipAccounts()->get()->map(function ($sip) {
            return [
                'id' => $sip->id,
                'extension' => $sip->sip_username,
                'password' => $sip->sip_password,
                'server' => $sip->sip_server,
                'port' => $sip->sip_port,
                'is_primary' => $sip->is_primary,
                'status' => $sip->status,
                'created_at' => $sip->created_at->format('M d, Y')
            ];
        });

        return response()->json([
            'success' => true,
            'sip_accounts' => $sipAccounts
        ]);
    }
}