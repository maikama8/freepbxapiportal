<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdvancedBillingService;
use App\Services\RealTimeBillingService;
use App\Models\CallRate;
use App\Models\CountryRate;
use App\Models\CallRecord;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BillingController extends Controller
{
    protected $advancedBillingService;
    protected $realTimeBillingService;

    public function __construct(
        AdvancedBillingService $advancedBillingService,
        RealTimeBillingService $realTimeBillingService
    ) {
        $this->advancedBillingService = $advancedBillingService;
        $this->realTimeBillingService = $realTimeBillingService;
    }

    /**
     * Display billing configuration dashboard
     */
    public function index()
    {
        $config = $this->advancedBillingService->getBillingConfiguration();
        $stats = $this->advancedBillingService->getBillingStatistics();
        
        return view('admin.billing.index', compact('config', 'stats'));
    }

    /**
     * Show billing increment configuration
     */
    public function increments()
    {
        $config = $this->advancedBillingService->getBillingConfiguration();
        $callRates = CallRate::active()->paginate(20);
        $countryRates = CountryRate::active()->paginate(20);
        
        return view('admin.billing.increments', compact('config', 'callRates', 'countryRates'));
    }

    /**
     * Update billing increment configuration
     */
    public function updateIncrements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'default_increment' => 'required|string|in:' . implode(',', array_keys($this->advancedBillingService->getAvailableBillingIncrements())),
            'billing.enable_real_time' => 'boolean',
            'billing.auto_terminate_on_zero_balance' => 'boolean',
            'billing.grace_period_seconds' => 'integer|min:0|max:300'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $this->advancedBillingService->updateBillingConfiguration($request->all());
            
            return redirect()->route('admin.billing.increments')
                ->with('success', 'Billing configuration updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update billing configuration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update call rate billing increment
     */
    public function updateCallRateIncrement(Request $request, CallRate $callRate)
    {
        $validator = Validator::make($request->all(), [
            'billing_increment_config' => 'required|string',
            'minimum_duration' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $callRate->update([
                'billing_increment_config' => $request->billing_increment_config,
                'minimum_duration' => $request->minimum_duration ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call rate billing increment updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update call rate: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update country rate billing increment
     */
    public function updateCountryRateIncrement(Request $request, CountryRate $countryRate)
    {
        $validator = Validator::make($request->all(), [
            'billing_increment_config' => 'required|string',
            'minimum_duration' => 'integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $countryRate->update([
                'billing_increment_config' => $request->billing_increment_config,
                'minimum_duration' => $request->minimum_duration ?? 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Country rate billing increment updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update country rate: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Bulk update billing increments
     */
    public function bulkUpdateIncrements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:call_rates,country_rates',
            'billing_increment_config' => 'required|string',
            'minimum_duration' => 'integer|min:0',
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $updateData = [
                'billing_increment_config' => $request->billing_increment_config,
                'minimum_duration' => $request->minimum_duration ?? 0
            ];

            if ($request->type === 'call_rates') {
                CallRate::whereIn('id', $request->ids)->update($updateData);
            } else {
                CountryRate::whereIn('id', $request->ids)->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Billing increments updated successfully for ' . count($request->ids) . ' records'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show billing statistics and reports
     */
    public function reports()
    {
        $stats = $this->advancedBillingService->getBillingStatistics();
        
        // Get recent billing activities
        $recentCalls = CallRecord::with('user')
            ->whereNotNull('cost')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get billing status distribution
        $billingStatusStats = CallRecord::selectRaw('billing_status, COUNT(*) as count, SUM(cost) as total_cost')
            ->whereNotNull('billing_status')
            ->groupBy('billing_status')
            ->get();

        return view('admin.billing.reports', compact('stats', 'recentCalls', 'billingStatusStats'));
    }

    /**
     * Process pending billing manually
     */
    public function processPendingBilling()
    {
        try {
            $processedCount = 0;
            
            $pendingCalls = CallRecord::where('billing_status', 'pending')
                ->where('status', 'completed')
                ->whereNotNull('end_time')
                ->limit(100)
                ->get();

            foreach ($pendingCalls as $callRecord) {
                if ($this->advancedBillingService->processAdvancedCallBilling($callRecord)) {
                    $processedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully processed billing for {$processedCount} calls",
                'processed_count' => $processedCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process pending billing: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test billing calculation
     */
    public function testBilling(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'destination' => 'required|string',
            'duration' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()]);
        }

        try {
            $result = $this->advancedBillingService->calculateAdvancedCallCost(
                $request->destination,
                $request->duration
            );

            return response()->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate cost: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Advanced billing configuration dashboard
     */
    public function configuration(): View
    {
        $billingConfig = $this->getBillingConfiguration();
        $performanceMetrics = $this->getBillingPerformanceMetrics();
        $billingRules = $this->getBillingRules();
        
        return view('admin.billing.configuration', compact(
            'billingConfig', 
            'performanceMetrics', 
            'billingRules'
        ));
    }

    /**
     * Get billing configuration data
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = $this->getBillingConfiguration();
            
            return response()->json([
                'success' => true,
                'configuration' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve billing configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update advanced billing configuration
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'default_billing_increment' => 'required|integer|in:1,6,30,60',
            'minimum_call_duration' => 'required|integer|min:0|max:300',
            'grace_period_seconds' => 'required|integer|min:0|max:60',
            'real_time_billing_enabled' => 'required|boolean',
            'auto_terminate_on_zero_balance' => 'required|boolean',
            'balance_check_interval' => 'required|integer|min:1|max:60',
            'billing_precision' => 'required|integer|in:2,4,6',
            'rounding_method' => 'required|string|in:up,down,nearest',
            'weekend_rate_multiplier' => 'required|numeric|min:0.1|max:10',
            'holiday_rate_multiplier' => 'required|numeric|min:0.1|max:10',
            'peak_hours_start' => 'required|date_format:H:i',
            'peak_hours_end' => 'required|date_format:H:i',
            'peak_rate_multiplier' => 'required|numeric|min:0.1|max:10',
            'low_balance_threshold' => 'required|numeric|min:0|max:1000',
            'critical_balance_threshold' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $settings = [
                'billing_default_increment' => $request->default_billing_increment,
                'billing_minimum_duration' => $request->minimum_call_duration,
                'billing_grace_period' => $request->grace_period_seconds,
                'billing_real_time_enabled' => $request->real_time_billing_enabled,
                'billing_auto_terminate' => $request->auto_terminate_on_zero_balance,
                'billing_balance_check_interval' => $request->balance_check_interval,
                'billing_precision' => $request->billing_precision,
                'billing_rounding_method' => $request->rounding_method,
                'billing_weekend_multiplier' => $request->weekend_rate_multiplier,
                'billing_holiday_multiplier' => $request->holiday_rate_multiplier,
                'billing_peak_hours_start' => $request->peak_hours_start,
                'billing_peak_hours_end' => $request->peak_hours_end,
                'billing_peak_multiplier' => $request->peak_rate_multiplier,
                'billing_low_balance_threshold' => $request->low_balance_threshold,
                'billing_critical_balance_threshold' => $request->critical_balance_threshold,
            ];

            foreach ($settings as $key => $value) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            // Log the configuration change
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'billing_configuration_updated',
                'description' => 'Updated advanced billing configuration',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'updated_settings' => $settings,
                ]
            ]);

            // Clear configuration cache
            Cache::forget('billing_configuration');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing configuration updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update billing configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time billing monitoring dashboard
     */
    public function monitoringDashboard(): View
    {
        return view('admin.billing.monitoring');
    }

    /**
     * Get real-time billing monitoring data
     */
    public function getMonitoringData(): JsonResponse
    {
        try {
            $data = [
                'active_calls' => $this->getActiveCallsData(),
                'billing_queue' => $this->getBillingQueueData(),
                'performance_metrics' => $this->getBillingPerformanceMetrics(),
                'system_health' => $this->getBillingSystemHealth(),
                'recent_activities' => $this->getRecentBillingActivities(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve monitoring data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get billing rules management interface
     */
    public function rulesManagement(): View
    {
        $rules = $this->getBillingRules();
        $availableConditions = $this->getAvailableRuleConditions();
        $availableActions = $this->getAvailableRuleActions();
        
        return view('admin.billing.rules', compact(
            'rules', 
            'availableConditions', 
            'availableActions'
        ));
    }

    /**
     * Create new billing rule
     */
    public function createRule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.parameters' => 'required|array',
            'priority' => 'required|integer|min:1|max:100',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $rule = SystemSetting::create([
                'key' => 'billing_rule_' . uniqid(),
                'value' => json_encode([
                    'name' => $request->name,
                    'description' => $request->description,
                    'conditions' => $request->conditions,
                    'actions' => $request->actions,
                    'priority' => $request->priority,
                    'is_active' => $request->is_active,
                    'created_at' => now()->toISOString(),
                    'created_by' => auth()->id(),
                ])
            ]);

            // Log the rule creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'billing_rule_created',
                'description' => "Created billing rule: {$request->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'rule_id' => $rule->id,
                    'rule_name' => $request->name,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing rule created successfully',
                'rule_id' => $rule->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create billing rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test billing rule
     */
    public function testRule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rule_id' => 'required|integer|exists:system_settings,id',
            'test_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rule = SystemSetting::findOrFail($request->rule_id);
            $ruleData = json_decode($rule->value, true);
            
            $testResult = $this->evaluateBillingRule($ruleData, $request->test_data);

            return response()->json([
                'success' => true,
                'test_result' => $testResult,
                'rule_name' => $ruleData['name']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test billing rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get billing performance analytics
     */
    public function getPerformanceAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|in:hour,day,week,month',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $analytics = $this->generateBillingAnalytics(
                $request->period,
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'generated_at' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate performance analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export billing configuration
     */
    public function exportConfiguration()
    {
        try {
            $config = $this->getBillingConfiguration();
            $rules = $this->getBillingRules();
            
            $exportData = [
                'configuration' => $config,
                'rules' => $rules,
                'exported_at' => now()->toISOString(),
                'exported_by' => auth()->user()->name,
            ];

            $filename = 'billing_configuration_' . now()->format('Y-m-d_H-i-s') . '.json';
            
            return response()->json($exportData)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import billing configuration
     */
    public function importConfiguration(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'config_file' => 'required|file|mimes:json|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $file = $request->file('config_file');
            $configData = json_decode(file_get_contents($file->getPathname()), true);

            if (!$configData || !isset($configData['configuration'])) {
                throw new \Exception('Invalid configuration file format');
            }

            // Import configuration settings
            if (isset($configData['configuration'])) {
                foreach ($configData['configuration'] as $key => $value) {
                    if (str_starts_with($key, 'billing_')) {
                        SystemSetting::updateOrCreate(
                            ['key' => $key],
                            ['value' => $value]
                        );
                    }
                }
            }

            // Import billing rules
            if (isset($configData['rules'])) {
                foreach ($configData['rules'] as $rule) {
                    SystemSetting::create([
                        'key' => 'billing_rule_' . uniqid(),
                        'value' => json_encode($rule)
                    ]);
                }
            }

            // Log the import
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'billing_configuration_imported',
                'description' => 'Imported billing configuration from file',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'filename' => $file->getClientOriginalName(),
                    'imported_at' => now()->toISOString(),
                ]
            ]);

            // Clear configuration cache
            Cache::forget('billing_configuration');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Billing configuration imported successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to import configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get billing configuration
     */
    private function getBillingConfiguration(): array
    {
        return Cache::remember('billing_configuration', 300, function () {
            $settings = SystemSetting::whereIn('key', [
                'billing_default_increment',
                'billing_minimum_duration',
                'billing_grace_period',
                'billing_real_time_enabled',
                'billing_auto_terminate',
                'billing_balance_check_interval',
                'billing_precision',
                'billing_rounding_method',
                'billing_weekend_multiplier',
                'billing_holiday_multiplier',
                'billing_peak_hours_start',
                'billing_peak_hours_end',
                'billing_peak_multiplier',
                'billing_low_balance_threshold',
                'billing_critical_balance_threshold',
            ])->pluck('value', 'key');

            return [
                'default_billing_increment' => $settings['billing_default_increment'] ?? 60,
                'minimum_call_duration' => $settings['billing_minimum_duration'] ?? 0,
                'grace_period_seconds' => $settings['billing_grace_period'] ?? 5,
                'real_time_billing_enabled' => $settings['billing_real_time_enabled'] ?? true,
                'auto_terminate_on_zero_balance' => $settings['billing_auto_terminate'] ?? true,
                'balance_check_interval' => $settings['billing_balance_check_interval'] ?? 30,
                'billing_precision' => $settings['billing_precision'] ?? 4,
                'rounding_method' => $settings['billing_rounding_method'] ?? 'up',
                'weekend_rate_multiplier' => $settings['billing_weekend_multiplier'] ?? 1.0,
                'holiday_rate_multiplier' => $settings['billing_holiday_multiplier'] ?? 1.5,
                'peak_hours_start' => $settings['billing_peak_hours_start'] ?? '08:00',
                'peak_hours_end' => $settings['billing_peak_hours_end'] ?? '18:00',
                'peak_rate_multiplier' => $settings['billing_peak_multiplier'] ?? 1.2,
                'low_balance_threshold' => $settings['billing_low_balance_threshold'] ?? 10.0,
                'critical_balance_threshold' => $settings['billing_critical_balance_threshold'] ?? 2.0,
            ];
        });
    }

    /**
     * Helper method to get billing performance metrics
     */
    private function getBillingPerformanceMetrics(): array
    {
        return Cache::remember('billing_performance_metrics', 60, function () {
            $now = now();
            $hourAgo = $now->copy()->subHour();
            $dayAgo = $now->copy()->subDay();

            return [
                'calls_processed_last_hour' => CallRecord::where('created_at', '>=', $hourAgo)
                    ->whereNotNull('cost')
                    ->count(),
                'calls_processed_last_day' => CallRecord::where('created_at', '>=', $dayAgo)
                    ->whereNotNull('cost')
                    ->count(),
                'average_processing_time' => $this->getAverageProcessingTime(),
                'billing_accuracy' => $this->getBillingAccuracy(),
                'failed_billing_count' => CallRecord::where('billing_status', 'failed')
                    ->where('created_at', '>=', $dayAgo)
                    ->count(),
                'pending_billing_count' => CallRecord::where('billing_status', 'pending')
                    ->count(),
            ];
        });
    }

    /**
     * Helper method to get billing rules
     */
    private function getBillingRules(): array
    {
        $rules = SystemSetting::where('key', 'like', 'billing_rule_%')
            ->get()
            ->map(function ($setting) {
                $ruleData = json_decode($setting->value, true);
                $ruleData['id'] = $setting->id;
                return $ruleData;
            })
            ->sortBy('priority')
            ->values()
            ->toArray();

        return $rules;
    }

    /**
     * Helper method to get active calls data
     */
    private function getActiveCallsData(): array
    {
        $activeCalls = CallRecord::whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
            ->with('user:id,name,balance')
            ->get();

        return [
            'total_active' => $activeCalls->count(),
            'calls_by_status' => $activeCalls->groupBy('status')->map->count(),
            'total_estimated_cost' => $activeCalls->sum('estimated_cost'),
            'calls_at_risk' => $activeCalls->filter(function ($call) {
                return $call->user && $call->user->balance < 1.0;
            })->count(),
        ];
    }

    /**
     * Helper method to get billing queue data
     */
    private function getBillingQueueData(): array
    {
        return [
            'pending_count' => CallRecord::where('billing_status', 'pending')->count(),
            'processing_count' => CallRecord::where('billing_status', 'processing')->count(),
            'failed_count' => CallRecord::where('billing_status', 'failed')->count(),
            'queue_age' => CallRecord::where('billing_status', 'pending')
                ->orderBy('created_at')
                ->first()?->created_at?->diffInMinutes(now()) ?? 0,
        ];
    }

    /**
     * Helper method to get billing system health
     */
    private function getBillingSystemHealth(): array
    {
        $recentErrors = CallRecord::where('billing_status', 'failed')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $processingDelay = CallRecord::where('billing_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(5))
            ->count();

        return [
            'status' => $recentErrors > 10 || $processingDelay > 50 ? 'warning' : 'healthy',
            'recent_errors' => $recentErrors,
            'processing_delay' => $processingDelay,
            'last_health_check' => now()->toISOString(),
        ];
    }

    /**
     * Helper method to get recent billing activities
     */
    private function getRecentBillingActivities(): array
    {
        return CallRecord::with('user:id,name')
            ->whereNotNull('cost')
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($call) {
                return [
                    'id' => $call->id,
                    'user_name' => $call->user?->name ?? 'Unknown',
                    'destination' => $call->destination,
                    'duration' => $call->duration,
                    'cost' => $call->cost,
                    'billing_status' => $call->billing_status,
                    'processed_at' => $call->updated_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Helper method to get available rule conditions
     */
    private function getAvailableRuleConditions(): array
    {
        return [
            'user_balance' => 'User Balance',
            'call_duration' => 'Call Duration',
            'destination_prefix' => 'Destination Prefix',
            'time_of_day' => 'Time of Day',
            'day_of_week' => 'Day of Week',
            'user_role' => 'User Role',
            'call_rate' => 'Call Rate',
            'monthly_usage' => 'Monthly Usage',
        ];
    }

    /**
     * Helper method to get available rule actions
     */
    private function getAvailableRuleActions(): array
    {
        return [
            'apply_discount' => 'Apply Discount',
            'apply_surcharge' => 'Apply Surcharge',
            'block_call' => 'Block Call',
            'send_notification' => 'Send Notification',
            'adjust_increment' => 'Adjust Billing Increment',
            'set_minimum_duration' => 'Set Minimum Duration',
        ];
    }

    /**
     * Helper method to evaluate billing rule
     */
    private function evaluateBillingRule(array $rule, array $testData): array
    {
        $conditionsMet = 0;
        $totalConditions = count($rule['conditions']);
        
        foreach ($rule['conditions'] as $condition) {
            if ($this->evaluateCondition($condition, $testData)) {
                $conditionsMet++;
            }
        }
        
        $ruleMatches = $conditionsMet === $totalConditions;
        
        return [
            'rule_matches' => $ruleMatches,
            'conditions_met' => $conditionsMet,
            'total_conditions' => $totalConditions,
            'actions_to_execute' => $ruleMatches ? $rule['actions'] : [],
        ];
    }

    /**
     * Helper method to evaluate individual condition
     */
    private function evaluateCondition(array $condition, array $testData): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $expectedValue = $condition['value'];
        $actualValue = $testData[$field] ?? null;

        switch ($operator) {
            case 'equals':
                return $actualValue == $expectedValue;
            case 'not_equals':
                return $actualValue != $expectedValue;
            case 'greater_than':
                return $actualValue > $expectedValue;
            case 'less_than':
                return $actualValue < $expectedValue;
            case 'greater_or_equal':
                return $actualValue >= $expectedValue;
            case 'less_or_equal':
                return $actualValue <= $expectedValue;
            case 'contains':
                return str_contains($actualValue, $expectedValue);
            case 'starts_with':
                return str_starts_with($actualValue, $expectedValue);
            default:
                return false;
        }
    }

    /**
     * Helper method to generate billing analytics
     */
    private function generateBillingAnalytics(string $period, ?string $startDate, ?string $endDate): array
    {
        $query = CallRecord::whereNotNull('cost');
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            switch ($period) {
                case 'hour':
                    $query->where('created_at', '>=', now()->subHour());
                    break;
                case 'day':
                    $query->where('created_at', '>=', now()->subDay());
                    break;
                case 'week':
                    $query->where('created_at', '>=', now()->subWeek());
                    break;
                case 'month':
                    $query->where('created_at', '>=', now()->subMonth());
                    break;
            }
        }

        $calls = $query->get();
        
        return [
            'total_calls' => $calls->count(),
            'total_revenue' => $calls->sum('cost'),
            'average_call_cost' => $calls->avg('cost'),
            'average_call_duration' => $calls->avg('duration'),
            'billing_accuracy' => $this->calculateBillingAccuracy($calls),
            'cost_distribution' => $this->getCostDistribution($calls),
            'hourly_breakdown' => $this->getHourlyBreakdown($calls),
        ];
    }

    /**
     * Helper method to get average processing time
     */
    private function getAverageProcessingTime(): float
    {
        // This would need to be implemented based on your specific billing processing timestamps
        return 2.5; // Placeholder value in seconds
    }

    /**
     * Helper method to get billing accuracy
     */
    private function getBillingAccuracy(): float
    {
        $totalCalls = CallRecord::whereNotNull('cost')->count();
        $accurateCalls = CallRecord::where('billing_status', 'completed')->count();
        
        return $totalCalls > 0 ? ($accurateCalls / $totalCalls) * 100 : 100;
    }

    /**
     * Helper method to calculate billing accuracy for a collection
     */
    private function calculateBillingAccuracy($calls): float
    {
        $totalCalls = $calls->count();
        $accurateCalls = $calls->where('billing_status', 'completed')->count();
        
        return $totalCalls > 0 ? ($accurateCalls / $totalCalls) * 100 : 100;
    }

    /**
     * Helper method to get cost distribution
     */
    private function getCostDistribution($calls): array
    {
        $ranges = [
            '0-1' => [0, 1],
            '1-5' => [1, 5],
            '5-10' => [5, 10],
            '10+' => [10, PHP_FLOAT_MAX],
        ];

        $distribution = [];
        foreach ($ranges as $label => $range) {
            $count = $calls->whereBetween('cost', $range)->count();
            $distribution[$label] = $count;
        }

        return $distribution;
    }

    /**
     * Helper method to get hourly breakdown
     */
    private function getHourlyBreakdown($calls): array
    {
        return $calls->groupBy(function ($call) {
            return $call->created_at->format('H:00');
        })->map(function ($hourCalls) {
            return [
                'count' => $hourCalls->count(),
                'revenue' => $hourCalls->sum('cost'),
            ];
        })->toArray();
    }
}