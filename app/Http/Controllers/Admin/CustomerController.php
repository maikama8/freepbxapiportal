<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display customer management interface
     */
    public function index(Request $request): View
    {
        return view('admin.customers.index');
    }

    /**
     * Get customers data for DataTables
     */
    public function getData(Request $request): JsonResponse
    {
        $query = User::whereIn('role', ['customer', 'operator'])
            ->with(['sipAccounts', 'callRecords']);

        // Search functionality
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%")
                  ->orWhere('email', 'like', "%{$searchValue}%")
                  ->orWhere('phone', 'like', "%{$searchValue}%")
                  ->orWhere('extension', 'like', "%{$searchValue}%");
            });
        }

        // Advanced search filters
        if ($request->filled('name_search')) {
            $query->where('name', 'like', "%{$request->name_search}%");
        }
        
        if ($request->filled('email_search')) {
            $query->where('email', 'like', "%{$request->email_search}%");
        }
        
        if ($request->filled('phone_search')) {
            $query->where('phone', 'like', "%{$request->phone_search}%");
        }

        // Filter by role
        if ($request->has('role_filter') && !empty($request->role_filter)) {
            $query->where('role', $request->role_filter);
        }

        // Filter by account type
        if ($request->has('account_type_filter') && !empty($request->account_type_filter)) {
            $query->where('account_type', $request->account_type_filter);
        }

        // Filter by status
        if ($request->has('status_filter') && !empty($request->status_filter)) {
            $query->where('status', $request->status_filter);
        }

        // Filter by balance range
        if ($request->filled('balance_min')) {
            $query->where('balance', '>=', $request->balance_min);
        }
        
        if ($request->filled('balance_max')) {
            $query->where('balance', '<=', $request->balance_max);
        }

        // Filter by status
        if ($request->has('status_filter') && !empty($request->status_filter)) {
            $query->where('status', $request->status_filter);
        }

        // Ordering
        if ($request->has('order')) {
            $columns = ['id', 'name', 'email', 'role', 'account_type', 'balance', 'status', 'created_at'];
            $orderColumn = $columns[$request->order[0]['column']] ?? 'created_at';
            $orderDirection = $request->order[0]['dir'] ?? 'desc';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $totalRecords = User::whereIn('role', ['customer', 'operator'])->count();
        $filteredRecords = $query->count();

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 10;
        $customers = $query->skip($start)->take($length)->get();

        $data = $customers->map(function ($customer) {
            // Get primary SIP account extension
            $primarySip = $customer->sipAccounts()->where('is_primary', true)->first();
            $extension = $primarySip ? $primarySip->sip_username : ($customer->extension ?? 'N/A');
            
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? 'N/A',
                'role' => ucfirst($customer->role),
                'account_type' => ucfirst($customer->account_type),
                'balance' => number_format($customer->balance, 2),
                'credit_limit' => $customer->isPostpaid() ? number_format($customer->credit_limit, 2) : 'N/A',
                'status' => ucfirst($customer->status),
                'extension' => $extension,
                'created_at' => $customer->created_at->format('M d, Y'),
                'last_login' => $customer->last_login_at ? $customer->last_login_at->format('M d, Y H:i') : 'Never',
                'actions' => $customer->id
            ];
        });

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        ]);
    }

    /**
     * Show customer creation form
     */
    public function create(): View
    {
        return view('admin.customers.create');
    }

    /**
     * Store new customer
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:customer,operator',
            'account_type' => 'required|in:prepaid,postpaid',
            'balance' => 'nullable|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:3',
            'extension' => 'nullable|string|max:10|unique:users',
        ]);

        try {
            DB::beginTransaction();

            $customer = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'account_type' => $request->account_type,
                'balance' => $request->balance ?? 0,
                'credit_limit' => $request->credit_limit ?? 0,
                'status' => 'active',
                'timezone' => $request->timezone ?? 'UTC',
                'currency' => $request->currency ?? 'USD',
                'extension' => $request->extension,
            ]);

            // Log the creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'customer_created',
                'description' => "Created customer account for {$customer->name} ({$customer->email})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'customer_id' => $customer->id,
                    'role' => $customer->role,
                    'account_type' => $customer->account_type,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'role' => $customer->role,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show customer details
     */
    public function show(User $customer): JsonResponse
    {
        // Ensure we're only showing customers/operators
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $customerData = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'role' => $customer->role,
            'account_type' => $customer->account_type,
            'balance' => $customer->balance,
            'credit_limit' => $customer->credit_limit,
            'status' => $customer->status,
            'timezone' => $customer->timezone,
            'currency' => $customer->currency,
            'extension' => $customer->extension,
            'sip_username' => $customer->sip_username,
            'created_at' => $customer->created_at,
            'last_login_at' => $customer->last_login_at,
            'failed_login_attempts' => $customer->failed_login_attempts,
            'locked_until' => $customer->locked_until,
        ];

        // Get recent activity
        $recentCalls = $customer->callRecords()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['destination', 'duration', 'cost', 'status', 'created_at']);

        $recentTransactions = $customer->balanceTransactions()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['type', 'amount', 'description', 'created_at']);

        return response()->json([
            'success' => true,
            'customer' => $customerData,
            'recent_calls' => $recentCalls,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    /**
     * Show customer edit form
     */
    public function edit(User $customer): View
    {
        // Ensure we're only editing customers/operators
        if (!in_array($customer->role, ['customer', 'operator'])) {
            abort(404);
        }

        return view('admin.customers.edit', compact('customer'));
    }

    /**
     * Update customer
     */
    public function update(Request $request, User $customer): JsonResponse
    {
        // Ensure we're only updating customers/operators
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($customer->id)],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:customer,operator',
            'account_type' => 'required|in:prepaid,postpaid',
            'balance' => 'nullable|numeric',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,locked',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:3',
            'extension' => ['nullable', 'string', 'max:10', Rule::unique('users')->ignore($customer->id)],
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        try {
            DB::beginTransaction();

            $originalData = $customer->toArray();

            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'role' => $request->role,
                'account_type' => $request->account_type,
                'balance' => $request->balance ?? $customer->balance,
                'credit_limit' => $request->credit_limit ?? 0,
                'status' => $request->status,
                'timezone' => $request->timezone ?? 'UTC',
                'currency' => $request->currency ?? 'USD',
                'extension' => $request->extension,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $customer->update($updateData);

            // Log the update
            $changes = array_diff_assoc($updateData, $originalData);
            unset($changes['password']); // Don't log password changes

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'customer_updated',
                'description' => "Updated customer account for {$customer->name} ({$customer->email})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'customer_id' => $customer->id,
                    'changes' => $changes,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'role' => $customer->role,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer
     */
    public function destroy(Request $request, User $customer): JsonResponse
    {
        // Ensure we're only deleting customers/operators
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent deletion if customer has active calls or outstanding balance
        $activeCalls = $customer->callRecords()
            ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->count();

        if ($activeCalls > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with active calls'
            ], 422);
        }

        if ($customer->balance < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with negative balance'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $customerName = $customer->name;
            $customerEmail = $customer->email;
            $customerId = $customer->id;

            $customer->delete();

            // Log the deletion
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'customer_deleted',
                'description' => "Deleted customer account for {$customerName} ({$customerEmail})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'customer_id' => $customerId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adjust customer balance
     */
    public function adjustBalance(Request $request, User $customer): JsonResponse
    {
        // Ensure we're only adjusting balance for customers/operators
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'description' => 'required|string|max:255',
            'type' => 'required|in:credit,debit',
        ]);

        try {
            DB::beginTransaction();

            $oldBalance = $customer->balance;
            $amount = abs($request->amount);
            
            if ($request->type === 'credit') {
                $customer->addBalance($amount);
                $transactionType = 'admin_credit';
            } else {
                if (!$customer->hasSufficientBalance($amount)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance for debit adjustment'
                    ], 422);
                }
                $customer->deductBalance($amount);
                $transactionType = 'admin_debit';
                $amount = -$amount; // Make negative for debit
            }

            // Create balance transaction record
            $customer->balanceTransactions()->create([
                'type' => $transactionType,
                'amount' => $amount,
                'balance_before' => $oldBalance,
                'balance_after' => $customer->balance,
                'description' => $request->description,
                'created_by' => auth()->id(),
            ]);

            // Log the adjustment
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'balance_adjusted',
                'description' => "Adjusted balance for {$customer->name}: {$request->type} of " . abs($amount),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'customer_id' => $customer->id,
                    'adjustment_type' => $request->type,
                    'amount' => $amount,
                    'old_balance' => $oldBalance,
                    'new_balance' => $customer->balance,
                    'description' => $request->description,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Balance adjusted successfully',
                'data' => [
                    'old_balance' => number_format($oldBalance, 2),
                    'new_balance' => number_format($customer->balance, 2),
                    'adjustment' => number_format($amount, 2),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset customer password
     */
    public function resetPassword(Request $request, User $customer): JsonResponse
    {
        // Ensure we're only resetting password for customers/operators
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $customer->update([
                'password' => Hash::make($request->password),
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            // If account was locked, unlock it
            if ($customer->status === 'locked') {
                $customer->update(['status' => 'active']);
            }

            // Log the password reset
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'password_reset',
                'description' => "Reset password for {$customer->name} ({$customer->email})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'customer_id' => $customer->id,
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create additional extension for customer
     */
    public function createExtension(Request $request, User $customer): JsonResponse
    {
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $request->validate([
            'extension_number' => 'nullable|string|unique:sip_accounts,sip_username',
            'password' => 'nullable|string|min:6'
        ]);

        try {
            DB::beginTransaction();

            $extensionService = app(\App\Services\FreePBX\ExtensionService::class);
            
            $extension = $request->extension_number ?: \App\Models\SipAccount::getNextAvailableExtension();
            $password = $request->password ?: 'secure' . rand(1000, 9999);

            // Create extension in FreePBX
            $result = $extensionService->createExtension($customer, $extension, $password);

            // Create SIP account in Laravel
            $sipAccount = \App\Models\SipAccount::create([
                'user_id' => $customer->id,
                'sip_username' => $extension,
                'sip_password' => $password,
                'sip_server' => config('voip.freepbx.sip.domain'),
                'sip_port' => config('voip.freepbx.sip.port', 5060),
                'status' => 'active',
                'is_primary' => $customer->sipAccounts()->count() === 0 // First extension is primary
            ]);

            // Log the extension creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'extension_created',
                'description' => "Created extension {$extension} for {$customer->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'customer_id' => $customer->id,
                    'extension' => $extension,
                    'sip_account_id' => $sipAccount->id
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Extension {$extension} created successfully",
                'data' => [
                    'extension' => $extension,
                    'password' => $password,
                    'sip_server' => config('voip.freepbx.sip.domain'),
                    'sip_port' => config('voip.freepbx.sip.port', 5060)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create extension: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer call history
     */
    public function callHistory(Request $request, User $customer): JsonResponse
    {
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $query = $customer->callRecords();

        // Apply filters
        if ($request->filled('date_from')) {
            $query->where('call_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('call_date', '<=', $request->date_to);
        }

        if ($request->filled('call_type')) {
            $query->where('call_type', $request->call_type);
        }

        if ($request->filled('destination')) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }

        $totalRecords = $query->count();
        
        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 50;
        
        $calls = $query->orderBy('call_date', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $calls->map(function ($call) {
            return [
                'call_date' => $call->call_date->format('M d, Y H:i:s'),
                'caller_id' => $call->caller_id,
                'source' => $call->source,
                'destination' => $call->destination,
                'duration' => gmdate('H:i:s', $call->duration),
                'call_type' => ucfirst($call->call_type),
                'destination_country' => $call->destination_country,
                'total_cost' => '$' . number_format($call->total_cost, 4),
                'disposition' => $call->disposition
            ];
        });

        return response()->json([
            'draw' => intval($request->draw ?? 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    /**
     * Get customer extensions
     */
    public function getExtensions(User $customer): JsonResponse
    {
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $extensions = $customer->sipAccounts()->get()->map(function ($sipAccount) {
            return [
                'id' => $sipAccount->id,
                'extension' => $sipAccount->sip_username,
                'status' => $sipAccount->status,
                'is_primary' => $sipAccount->is_primary,
                'created_at' => $sipAccount->created_at->format('M d, Y'),
                'sip_server' => $sipAccount->sip_server,
                'sip_port' => $sipAccount->sip_port
            ];
        });

        return response()->json([
            'success' => true,
            'extensions' => $extensions
        ]);
    }

    /**
     * Delete customer extension
     */
    public function deleteExtension(Request $request, User $customer, \App\Models\SipAccount $sipAccount): JsonResponse
    {
        if (!in_array($customer->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($sipAccount->user_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Extension not found'
            ], 404);
        }

        if ($sipAccount->is_primary && $customer->sipAccounts()->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete primary extension when other extensions exist'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $extension = $sipAccount->sip_username;

            // Delete from FreePBX
            $extensionService = app(\App\Services\FreePBX\ExtensionService::class);
            $extensionService->deleteExtension($extension);

            // Delete from Laravel
            $sipAccount->delete();

            // Log the deletion
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'extension_deleted',
                'description' => "Deleted extension {$extension} for {$customer->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'customer_id' => $customer->id,
                    'extension' => $extension
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Extension {$extension} deleted successfully"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete extension: ' . $e->getMessage()
            ], 500);
        }
    }
}