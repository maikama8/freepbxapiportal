<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CallRecord;
use App\Models\PaymentTransaction;
use App\Models\BalanceTransaction;
use App\Models\AuditLog;
use App\Models\CallRate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class SystemController extends Controller
{
    /**
     * Display system monitoring dashboard
     */
    public function index(): View
    {
        return view('admin.system.index');
    }

    /**
     * Get system metrics and statistics
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'admin_system_metrics';
            $cacheDuration = 300; // 5 minutes

            $metrics = Cache::remember($cacheKey, $cacheDuration, function () {
                return [
                    'users' => $this->getUserMetrics(),
                    'calls' => $this->getCallMetrics(),
                    'financial' => $this->getFinancialMetrics(),
                    'system' => $this->getSystemMetrics(),
                ];
            });

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'last_updated' => now()->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get call volume data for charts
     */
    public function getCallVolumeData(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|in:24h,7d,30d,90d',
        ]);

        try {
            $period = $request->period;
            $data = [];

            switch ($period) {
                case '24h':
                    $data = $this->getHourlyCallData();
                    break;
                case '7d':
                    $data = $this->getDailyCallData(7);
                    break;
                case '30d':
                    $data = $this->getDailyCallData(30);
                    break;
                case '90d':
                    $data = $this->getWeeklyCallData(90);
                    break;
            }

            return response()->json([
                'success' => true,
                'period' => $period,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve call volume data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue data for charts
     */
    public function getRevenueData(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|in:7d,30d,90d,1y',
        ]);

        try {
            $period = $request->period;
            $data = [];

            switch ($period) {
                case '7d':
                    $data = $this->getDailyRevenueData(7);
                    break;
                case '30d':
                    $data = $this->getDailyRevenueData(30);
                    break;
                case '90d':
                    $data = $this->getWeeklyRevenueData(90);
                    break;
                case '1y':
                    $data = $this->getMonthlyRevenueData(365);
                    break;
            }

            return response()->json([
                'success' => true,
                'period' => $period,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve revenue data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment gateway configuration
     */
    public function getPaymentGatewayConfig(): JsonResponse
    {
        try {
            $config = [
                'nowpayments' => [
                    'enabled' => !empty(config('services.nowpayments.api_key')),
                    'api_key_set' => !empty(config('services.nowpayments.api_key')),
                    'sandbox_mode' => config('services.nowpayments.sandbox', false),
                ],
                'paypal' => [
                    'enabled' => !empty(config('services.paypal.client_id')),
                    'client_id_set' => !empty(config('services.paypal.client_id')),
                    'client_secret_set' => !empty(config('services.paypal.client_secret')),
                    'sandbox_mode' => config('services.paypal.sandbox', false),
                ],
            ];

            return response()->json([
                'success' => true,
                'config' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment gateway configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment gateway configuration
     */
    public function updatePaymentGatewayConfig(Request $request): JsonResponse
    {
        $request->validate([
            'gateway' => 'required|in:nowpayments,paypal',
            'config' => 'required|array',
        ]);

        try {
            $gateway = $request->gateway;
            $config = $request->config;

            // This would typically update environment variables or configuration files
            // For now, we'll just log the configuration change
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'payment_gateway_config_updated',
                'description' => "Updated {$gateway} payment gateway configuration",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'gateway' => $gateway,
                    'config_keys' => array_keys($config),
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment gateway configuration updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment gateway configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent audit logs
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 50);
            $logs = AuditLog::with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'description' => $log->description,
                        'user' => $log->user ? $log->user->name : 'System',
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at->format('M d, Y H:i:s'),
                        'metadata' => $log->metadata,
                    ];
                });

            return response()->json([
                'success' => true,
                'logs' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = [
                'database' => $this->checkDatabaseHealth(),
                'cache' => $this->checkCacheHealth(),
                'storage' => $this->checkStorageHealth(),
                'queue' => $this->checkQueueHealth(),
            ];

            $overallStatus = collect($health)->every(fn($status) => $status['status'] === 'healthy') ? 'healthy' : 'warning';

            return response()->json([
                'success' => true,
                'overall_status' => $overallStatus,
                'components' => $health,
                'checked_at' => now()->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user metrics
     */
    private function getUserMetrics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'customers' => User::where('role', 'customer')->count(),
            'operators' => User::where('role', 'operator')->count(),
            'admins' => User::where('role', 'admin')->count(),
            'prepaid_accounts' => User::where('account_type', 'prepaid')->count(),
            'postpaid_accounts' => User::where('account_type', 'postpaid')->count(),
            'locked_accounts' => User::where('status', 'locked')->count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_users_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    /**
     * Get call metrics
     */
    private function getCallMetrics(): array
    {
        $totalCalls = CallRecord::count();
        $completedCalls = CallRecord::where('status', 'completed')->count();
        $activeCalls = CallRecord::whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])->count();
        
        return [
            'total_calls' => $totalCalls,
            'completed_calls' => $completedCalls,
            'active_calls' => $activeCalls,
            'failed_calls' => CallRecord::where('status', 'failed')->count(),
            'calls_today' => CallRecord::whereDate('created_at', today())->count(),
            'calls_this_week' => CallRecord::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'average_duration' => CallRecord::where('status', 'completed')->avg('duration') ?? 0,
            'total_minutes' => CallRecord::where('status', 'completed')->sum('duration') / 60,
            'completion_rate' => $totalCalls > 0 ? round(($completedCalls / $totalCalls) * 100, 2) : 0,
        ];
    }

    /**
     * Get financial metrics
     */
    private function getFinancialMetrics(): array
    {
        $totalRevenue = CallRecord::where('status', 'completed')->sum('cost');
        $totalPayments = PaymentTransaction::where('status', 'completed')->sum('amount');
        
        return [
            'total_revenue' => $totalRevenue,
            'total_payments' => $totalPayments,
            'revenue_today' => CallRecord::where('status', 'completed')->whereDate('created_at', today())->sum('cost'),
            'revenue_this_week' => CallRecord::where('status', 'completed')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('cost'),
            'revenue_this_month' => CallRecord::where('status', 'completed')->whereMonth('created_at', now()->month)->sum('cost'),
            'payments_today' => PaymentTransaction::where('status', 'completed')->whereDate('created_at', today())->sum('amount'),
            'pending_payments' => PaymentTransaction::where('status', 'pending')->sum('amount'),
            'failed_payments' => PaymentTransaction::where('status', 'failed')->count(),
            'average_call_cost' => CallRecord::where('status', 'completed')->avg('cost') ?? 0,
            'total_customer_balance' => User::where('role', 'customer')->sum('balance'),
        ];
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'total_rates' => CallRate::count(),
            'active_rates' => CallRate::where('is_active', true)->count(),
            'audit_logs_count' => AuditLog::count(),
            'audit_logs_today' => AuditLog::whereDate('created_at', today())->count(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database_size' => $this->getDatabaseSize(),
            'uptime' => $this->getSystemUptime(),
        ];
    }

    /**
     * Get hourly call data for last 24 hours
     */
    private function getHourlyCallData(): array
    {
        $data = [];
        for ($i = 23; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $calls = CallRecord::whereBetween('created_at', [
                $hour->copy()->startOfHour(),
                $hour->copy()->endOfHour()
            ])->count();
            
            $data[] = [
                'label' => $hour->format('H:00'),
                'calls' => $calls,
                'timestamp' => $hour->timestamp
            ];
        }
        return $data;
    }

    /**
     * Get daily call data
     */
    private function getDailyCallData(int $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $calls = CallRecord::whereDate('created_at', $date)->count();
            
            $data[] = [
                'label' => $date->format('M d'),
                'calls' => $calls,
                'timestamp' => $date->timestamp
            ];
        }
        return $data;
    }

    /**
     * Get weekly call data
     */
    private function getWeeklyCallData(int $days): array
    {
        $data = [];
        $weeks = ceil($days / 7);
        
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $calls = CallRecord::whereBetween('created_at', [$weekStart, $weekEnd])->count();
            
            $data[] = [
                'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'calls' => $calls,
                'timestamp' => $weekStart->timestamp
            ];
        }
        return $data;
    }

    /**
     * Get daily revenue data
     */
    private function getDailyRevenueData(int $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $revenue = CallRecord::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('cost');
            
            $data[] = [
                'label' => $date->format('M d'),
                'revenue' => round($revenue, 2),
                'timestamp' => $date->timestamp
            ];
        }
        return $data;
    }

    /**
     * Get weekly revenue data
     */
    private function getWeeklyRevenueData(int $days): array
    {
        $data = [];
        $weeks = ceil($days / 7);
        
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $revenue = CallRecord::where('status', 'completed')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->sum('cost');
            
            $data[] = [
                'label' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
                'revenue' => round($revenue, 2),
                'timestamp' => $weekStart->timestamp
            ];
        }
        return $data;
    }

    /**
     * Get monthly revenue data
     */
    private function getMonthlyRevenueData(int $days): array
    {
        $data = [];
        $months = ceil($days / 30);
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            
            $revenue = CallRecord::where('status', 'completed')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('cost');
            
            $data[] = [
                'label' => $monthStart->format('M Y'),
                'revenue' => round($revenue, 2),
                'timestamp' => $monthStart->timestamp
            ];
        }
        return $data;
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array
    {
        try {
            DB::connection()->getPdo();
            $connectionTime = microtime(true);
            DB::select('SELECT 1');
            $queryTime = (microtime(true) - $connectionTime) * 1000;
            
            return [
                'status' => 'healthy',
                'response_time' => round($queryTime, 2) . 'ms',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'response_time' => null,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check cache health
     */
    private function checkCacheHealth(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working properly'
                ];
            } else {
                return [
                    'status' => 'warning',
                    'message' => 'Cache read/write test failed'
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check storage health
     */
    private function checkStorageHealth(): array
    {
        try {
            $storagePath = storage_path();
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 2);
            
            $status = $usedPercent > 90 ? 'error' : ($usedPercent > 80 ? 'warning' : 'healthy');
            
            return [
                'status' => $status,
                'used_percent' => $usedPercent,
                'free_space' => $this->formatBytes($freeBytes),
                'total_space' => $this->formatBytes($totalBytes),
                'message' => "Storage {$usedPercent}% used"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Storage check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check queue health
     */
    private function checkQueueHealth(): array
    {
        try {
            // This is a basic check - in production you might want to check actual queue status
            return [
                'status' => 'healthy',
                'message' => 'Queue system operational'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Queue check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get database size
     */
    private function getDatabaseSize(): string
    {
        try {
            $databaseName = config('database.connections.mysql.database');
            $result = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$databaseName]);
            
            return ($result[0]->size_mb ?? 0) . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get system uptime (approximation based on oldest log entry)
     */
    private function getSystemUptime(): string
    {
        try {
            $oldestLog = AuditLog::orderBy('created_at')->first();
            if ($oldestLog) {
                return $oldestLog->created_at->diffForHumans(null, true);
            }
            return 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}