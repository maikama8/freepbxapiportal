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
        $user = Auth::user();
        
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
        $user = Auth::user();
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
}