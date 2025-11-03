<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;

class AuditController extends Controller
{
    /**
     * Display audit log interface
     */
    public function index(Request $request): View
    {
        return view('admin.audit.index');
    }

    /**
     * Get audit logs data for DataTables
     */
    public function getData(Request $request): JsonResponse
    {
        $query = AuditLog::with(['user'])
            ->select('audit_logs.*');

        // Search functionality
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('action', 'like', "%{$searchValue}%")
                  ->orWhere('description', 'like', "%{$searchValue}%")
                  ->orWhere('ip_address', 'like', "%{$searchValue}%")
                  ->orWhereHas('user', function($userQuery) use ($searchValue) {
                      $userQuery->where('name', 'like', "%{$searchValue}%")
                               ->orWhere('email', 'like', "%{$searchValue}%");
                  });
            });
        }

        // Filter by user
        if ($request->filled('user_filter')) {
            $query->where('user_id', $request->user_filter);
        }

        // Filter by action
        if ($request->filled('action_filter')) {
            $query->where('action', $request->action_filter);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by IP address
        if ($request->filled('ip_filter')) {
            $query->where('ip_address', 'like', "%{$request->ip_filter}%");
        }

        // Ordering
        if ($request->has('order')) {
            $columns = ['id', 'user_id', 'action', 'description', 'ip_address', 'created_at'];
            $orderColumn = $columns[$request->order[0]['column']] ?? 'created_at';
            $orderDirection = $request->order[0]['dir'] ?? 'desc';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $totalRecords = AuditLog::count();
        $filteredRecords = $query->count();

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 25;
        $logs = $query->skip($start)->take($length)->get();

        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->role
                ] : ['name' => 'System', 'email' => 'system@platform.com', 'role' => 'system'],
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at->format('M d, Y H:i:s'),
                'created_at_human' => $log->created_at->diffForHumans(),
                'severity' => $this->getActionSeverity($log->action),
                'category' => $this->getActionCategory($log->action)
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
     * Get audit log statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        $stats = [
            'total_logs' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])->count(),
            'unique_users' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->distinct('user_id')->count('user_id'),
            'unique_ips' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->distinct('ip_address')->count('ip_address'),
            'top_actions' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'top_users' => AuditLog::with('user')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'daily_activity' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'security_events' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->whereIn('action', ['login_failed', 'account_locked', 'password_changed', 'unauthorized_access'])
                ->count(),
            'admin_actions' => AuditLog::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->whereIn('action', ['user_created', 'user_updated', 'user_deleted', 'balance_adjusted', 'rate_updated'])
                ->count()
        ];

        return response()->json($stats);
    }

    /**
     * Get available users for filtering
     */
    public function getUsers(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'role')
            ->whereHas('auditLogs')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    /**
     * Get available actions for filtering
     */
    public function getActions(): JsonResponse
    {
        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return response()->json($actions);
    }

    /**
     * Export audit logs
     */
    public function export(Request $request)
    {
        $query = AuditLog::with(['user']);

        // Apply same filters as getData method
        if ($request->filled('user_filter')) {
            $query->where('user_id', $request->user_filter);
        }

        if ($request->filled('action_filter')) {
            $query->where('action', $request->action_filter);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'User', 'Email', 'Role', 'Action', 'Description', 
                'IP Address', 'User Agent', 'Date/Time', 'Severity', 'Category'
            ]);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->user ? $log->user->name : 'System',
                    $log->user ? $log->user->email : 'system@platform.com',
                    $log->user ? $log->user->role : 'system',
                    $log->action,
                    $log->description,
                    $log->ip_address,
                    $log->user_agent,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $this->getActionSeverity($log->action),
                    $this->getActionCategory($log->action)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get action severity level
     */
    private function getActionSeverity(string $action): string
    {
        $highSeverity = ['user_deleted', 'account_locked', 'unauthorized_access', 'login_failed', 'balance_adjusted'];
        $mediumSeverity = ['user_created', 'user_updated', 'password_changed', 'rate_updated', 'payment_processed'];
        
        if (in_array($action, $highSeverity)) {
            return 'high';
        } elseif (in_array($action, $mediumSeverity)) {
            return 'medium';
        }
        
        return 'low';
    }

    /**
     * Get action category
     */
    private function getActionCategory(string $action): string
    {
        $categories = [
            'user_' => 'User Management',
            'login' => 'Authentication',
            'password' => 'Security',
            'balance' => 'Financial',
            'payment' => 'Financial',
            'rate' => 'Billing',
            'call' => 'Communication',
            'did' => 'Telephony',
            'system' => 'System'
        ];

        foreach ($categories as $prefix => $category) {
            if (strpos($action, $prefix) === 0) {
                return $category;
            }
        }

        return 'General';
    }
}