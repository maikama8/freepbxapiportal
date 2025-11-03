<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CountryRate;
use App\Models\AuditLog;
use App\Models\DidNumber;
use App\Models\CallRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CountryRateController extends Controller
{
    /**
     * Display country rate management interface
     */
    public function index(Request $request): View
    {
        return view('admin.rates.countries.index');
    }

    /**
     * Get country rates data for DataTables
     */
    public function getData(Request $request): JsonResponse
    {
        $query = CountryRate::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('country_name', 'like', "%{$searchValue}%")
                  ->orWhere('country_code', 'like', "%{$searchValue}%")
                  ->orWhere('country_prefix', 'like', "%{$searchValue}%");
            });
        }

        // Filter by active status
        if ($request->has('status_filter') && $request->status_filter !== '') {
            $query->where('is_active', $request->status_filter === '1');
        }

        // Filter by features
        if ($request->has('feature_filter') && !empty($request->feature_filter)) {
            $query->whereJsonContains('features', $request->feature_filter);
        }

        // Filter by price range
        if ($request->has('min_call_rate') && is_numeric($request->min_call_rate)) {
            $query->where('call_rate_per_minute', '>=', $request->min_call_rate);
        }
        if ($request->has('max_call_rate') && is_numeric($request->max_call_rate)) {
            $query->where('call_rate_per_minute', '<=', $request->max_call_rate);
        }

        // Ordering
        if ($request->has('order')) {
            $columns = [
                'id', 'country_name', 'country_code', 'country_prefix', 
                'call_rate_per_minute', 'did_setup_cost', 'did_monthly_cost', 
                'billing_increment', 'is_active'
            ];
            $orderColumn = $columns[$request->order[0]['column']] ?? 'country_name';
            $orderDirection = $request->order[0]['dir'] ?? 'asc';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderBy('country_name', 'asc');
        }

        $totalRecords = CountryRate::count();
        $filteredRecords = $query->count();

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 25;
        $rates = $query->skip($start)->take($length)->get();

        $data = $rates->map(function ($rate) {
            return [
                'id' => $rate->id,
                'country_name' => $rate->country_name,
                'country_code' => $rate->country_code,
                'country_prefix' => $rate->country_prefix,
                'call_rate_per_minute' => number_format($rate->call_rate_per_minute, 4),
                'did_setup_cost' => number_format($rate->did_setup_cost, 2),
                'did_monthly_cost' => number_format($rate->did_monthly_cost, 2),
                'billing_increment' => $rate->billing_increment_description,
                'features' => $rate->features ?? ['voice'],
                'is_active' => $rate->is_active,
                'did_count' => $rate->didNumbers()->count(),
                'created_at' => $rate->created_at->format('M d, Y'),
                'actions' => $rate->id
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
     * Store new country rate
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'country_code' => 'required|string|size:2|unique:country_rates,country_code',
            'country_name' => 'required|string|max:255',
            'country_prefix' => 'required|string|max:10|unique:country_rates,country_prefix',
            'call_rate_per_minute' => 'required|numeric|min:0|max:999.9999',
            'did_setup_cost' => 'required|numeric|min:0|max:9999.99',
            'did_monthly_cost' => 'required|numeric|min:0|max:9999.99',
            'sms_rate_per_message' => 'nullable|numeric|min:0|max:99.9999',
            'billing_increment' => 'required|integer|in:1,6,30,60',
            'minimum_duration' => 'required|integer|min:1|max:3600',
            'area_codes' => 'nullable|array',
            'area_codes.*' => 'string|max:10',
            'features' => 'nullable|array',
            'features.*' => 'string|in:voice,sms,fax,video',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $countryRate = CountryRate::create([
                'country_code' => strtoupper($request->country_code),
                'country_name' => $request->country_name,
                'country_prefix' => $request->country_prefix,
                'call_rate_per_minute' => $request->call_rate_per_minute,
                'did_setup_cost' => $request->did_setup_cost,
                'did_monthly_cost' => $request->did_monthly_cost,
                'sms_rate_per_message' => $request->sms_rate_per_message ?? 0,
                'billing_increment' => $request->billing_increment,
                'minimum_duration' => $request->minimum_duration,
                'area_codes' => $request->area_codes ?? [],
                'features' => $request->features ?? ['voice'],
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Log the creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'country_rate_created',
                'description' => "Created country rate for {$countryRate->country_name} ({$countryRate->country_code})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'country_rate_id' => $countryRate->id,
                    'country_code' => $countryRate->country_code,
                    'call_rate_per_minute' => $countryRate->call_rate_per_minute,
                    'did_setup_cost' => $countryRate->did_setup_cost,
                ]
            ]);

            // Clear cache
            Cache::forget('country_rates_active');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Country rate created successfully',
                'country_rate' => [
                    'id' => $countryRate->id,
                    'country_name' => $countryRate->country_name,
                    'country_code' => $countryRate->country_code,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create country rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show country rate details
     */
    public function show(CountryRate $countryRate): JsonResponse
    {
        $countryRate->load(['didNumbers' => function($query) {
            $query->select('id', 'did_number', 'status', 'user_id', 'country_code');
        }]);

        return response()->json([
            'success' => true,
            'country_rate' => [
                'id' => $countryRate->id,
                'country_code' => $countryRate->country_code,
                'country_name' => $countryRate->country_name,
                'country_prefix' => $countryRate->country_prefix,
                'call_rate_per_minute' => $countryRate->call_rate_per_minute,
                'did_setup_cost' => $countryRate->did_setup_cost,
                'did_monthly_cost' => $countryRate->did_monthly_cost,
                'sms_rate_per_message' => $countryRate->sms_rate_per_message,
                'billing_increment' => $countryRate->billing_increment,
                'minimum_duration' => $countryRate->minimum_duration,
                'area_codes' => $countryRate->area_codes,
                'features' => $countryRate->features,
                'is_active' => $countryRate->is_active,
                'created_at' => $countryRate->created_at,
                'updated_at' => $countryRate->updated_at,
                'statistics' => [
                    'total_dids' => $countryRate->didNumbers()->count(),
                    'assigned_dids' => $countryRate->didNumbers()->whereNotNull('user_id')->count(),
                    'available_dids' => $countryRate->didNumbers()->where('status', 'available')->count(),
                ]
            ]
        ]);
    }

    /**
     * Update country rate
     */
    public function update(Request $request, CountryRate $countryRate): JsonResponse
    {
        $request->validate([
            'country_code' => 'required|string|size:2|unique:country_rates,country_code,' . $countryRate->id,
            'country_name' => 'required|string|max:255',
            'country_prefix' => 'required|string|max:10|unique:country_rates,country_prefix,' . $countryRate->id,
            'call_rate_per_minute' => 'required|numeric|min:0|max:999.9999',
            'did_setup_cost' => 'required|numeric|min:0|max:9999.99',
            'did_monthly_cost' => 'required|numeric|min:0|max:9999.99',
            'sms_rate_per_message' => 'nullable|numeric|min:0|max:99.9999',
            'billing_increment' => 'required|integer|in:1,6,30,60',
            'minimum_duration' => 'required|integer|min:1|max:3600',
            'area_codes' => 'nullable|array',
            'area_codes.*' => 'string|max:10',
            'features' => 'nullable|array',
            'features.*' => 'string|in:voice,sms,fax,video',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $originalData = $countryRate->toArray();

            $countryRate->update([
                'country_code' => strtoupper($request->country_code),
                'country_name' => $request->country_name,
                'country_prefix' => $request->country_prefix,
                'call_rate_per_minute' => $request->call_rate_per_minute,
                'did_setup_cost' => $request->did_setup_cost,
                'did_monthly_cost' => $request->did_monthly_cost,
                'sms_rate_per_message' => $request->sms_rate_per_message ?? 0,
                'billing_increment' => $request->billing_increment,
                'minimum_duration' => $request->minimum_duration,
                'area_codes' => $request->area_codes ?? [],
                'features' => $request->features ?? ['voice'],
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Log the update
            $changes = array_diff_assoc($countryRate->toArray(), $originalData);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'country_rate_updated',
                'description' => "Updated country rate for {$countryRate->country_name} ({$countryRate->country_code})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'country_rate_id' => $countryRate->id,
                    'changes' => $changes,
                ]
            ]);

            // Clear cache
            Cache::forget('country_rates_active');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Country rate updated successfully',
                'country_rate' => [
                    'id' => $countryRate->id,
                    'country_name' => $countryRate->country_name,
                    'country_code' => $countryRate->country_code,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update country rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete country rate
     */
    public function destroy(Request $request, CountryRate $countryRate): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Check if country has assigned DIDs
            $assignedDids = $countryRate->didNumbers()->whereNotNull('user_id')->count();
            if ($assignedDids > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete country rate. {$assignedDids} DID numbers are currently assigned to customers."
                ], 422);
            }

            $countryName = $countryRate->country_name;
            $countryCode = $countryRate->country_code;
            $countryRateId = $countryRate->id;

            // Delete associated DID numbers
            $countryRate->didNumbers()->delete();

            $countryRate->delete();

            // Log the deletion
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'country_rate_deleted',
                'description' => "Deleted country rate for {$countryName} ({$countryCode})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'country_rate_id' => $countryRateId,
                    'country_code' => $countryCode,
                    'country_name' => $countryName,
                ]
            ]);

            // Clear cache
            Cache::forget('country_rates_active');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Country rate deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete country rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update country rates
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'country_ids' => 'required|array|min:1',
            'country_ids.*' => 'integer|exists:country_rates,id',
            'update_type' => 'required|string|in:call_rate,did_costs,billing_increment,status',
            'call_rate_adjustment' => 'required_if:update_type,call_rate|nullable|numeric',
            'call_rate_type' => 'required_if:update_type,call_rate|nullable|string|in:percentage,fixed',
            'did_setup_adjustment' => 'required_if:update_type,did_costs|nullable|numeric',
            'did_monthly_adjustment' => 'required_if:update_type,did_costs|nullable|numeric',
            'did_cost_type' => 'required_if:update_type,did_costs|nullable|string|in:percentage,fixed',
            'billing_increment' => 'required_if:update_type,billing_increment|nullable|integer|in:1,6,30,60',
            'is_active' => 'required_if:update_type,status|nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $countryRates = CountryRate::whereIn('id', $request->country_ids)->get();
            $updatedCount = 0;
            $errors = [];

            foreach ($countryRates as $countryRate) {
                try {
                    $originalData = $countryRate->toArray();
                    $updated = false;

                    switch ($request->update_type) {
                        case 'call_rate':
                            $adjustment = $request->call_rate_adjustment;
                            if ($request->call_rate_type === 'percentage') {
                                $newRate = $countryRate->call_rate_per_minute * (1 + ($adjustment / 100));
                            } else {
                                $newRate = $countryRate->call_rate_per_minute + $adjustment;
                            }
                            
                            if ($newRate >= 0 && $newRate <= 999.9999) {
                                $countryRate->call_rate_per_minute = round($newRate, 4);
                                $updated = true;
                            }
                            break;

                        case 'did_costs':
                            $setupAdjustment = $request->did_setup_adjustment;
                            $monthlyAdjustment = $request->did_monthly_adjustment;
                            
                            if ($request->did_cost_type === 'percentage') {
                                $newSetupCost = $countryRate->did_setup_cost * (1 + ($setupAdjustment / 100));
                                $newMonthlyCost = $countryRate->did_monthly_cost * (1 + ($monthlyAdjustment / 100));
                            } else {
                                $newSetupCost = $countryRate->did_setup_cost + $setupAdjustment;
                                $newMonthlyCost = $countryRate->did_monthly_cost + $monthlyAdjustment;
                            }
                            
                            if ($newSetupCost >= 0 && $newSetupCost <= 9999.99 && 
                                $newMonthlyCost >= 0 && $newMonthlyCost <= 9999.99) {
                                $countryRate->did_setup_cost = round($newSetupCost, 2);
                                $countryRate->did_monthly_cost = round($newMonthlyCost, 2);
                                $updated = true;
                            }
                            break;

                        case 'billing_increment':
                            $countryRate->billing_increment = $request->billing_increment;
                            $updated = true;
                            break;

                        case 'status':
                            $countryRate->is_active = $request->boolean('is_active');
                            $updated = true;
                            break;
                    }

                    if ($updated) {
                        $countryRate->save();
                        $updatedCount++;

                        // Log individual update
                        $changes = array_diff_assoc($countryRate->toArray(), $originalData);
                        AuditLog::create([
                            'user_id' => auth()->id(),
                            'action' => 'country_rate_bulk_updated',
                            'description' => "Bulk updated country rate for {$countryRate->country_name}",
                            'ip_address' => $request->ip(),
                            'user_agent' => $request->userAgent(),
                            'metadata' => [
                                'country_rate_id' => $countryRate->id,
                                'update_type' => $request->update_type,
                                'changes' => $changes,
                            ]
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to update {$countryRate->country_name}: {$e->getMessage()}";
                }
            }

            // Clear cache
            Cache::forget('country_rates_active');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk update completed. {$updatedCount} country rates updated.",
                'data' => [
                    'updated_count' => $updatedCount,
                    'total_count' => count($request->country_ids),
                    'errors' => $errors,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rate comparison data
     */
    public function comparison(Request $request): JsonResponse
    {
        $request->validate([
            'country_ids' => 'required|array|min:2|max:10',
            'country_ids.*' => 'integer|exists:country_rates,id',
        ]);

        try {
            $countryRates = CountryRate::whereIn('id', $request->country_ids)
                ->with(['didNumbers' => function($query) {
                    $query->selectRaw('country_code, COUNT(*) as total_count, 
                                      SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_count')
                          ->groupBy('country_code');
                }])
                ->get();

            $comparison = $countryRates->map(function ($rate) {
                $didStats = $rate->didNumbers->first();
                
                return [
                    'id' => $rate->id,
                    'country_name' => $rate->country_name,
                    'country_code' => $rate->country_code,
                    'country_prefix' => $rate->country_prefix,
                    'call_rate_per_minute' => $rate->call_rate_per_minute,
                    'did_setup_cost' => $rate->did_setup_cost,
                    'did_monthly_cost' => $rate->did_monthly_cost,
                    'billing_increment' => $rate->billing_increment,
                    'billing_increment_description' => $rate->billing_increment_description,
                    'features' => $rate->features,
                    'is_active' => $rate->is_active,
                    'statistics' => [
                        'total_dids' => $didStats->total_count ?? 0,
                        'assigned_dids' => $didStats->assigned_count ?? 0,
                        'utilization_rate' => $didStats && $didStats->total_count > 0 
                            ? round(($didStats->assigned_count / $didStats->total_count) * 100, 2) 
                            : 0,
                    ]
                ];
            });

            // Calculate comparison metrics
            $callRates = $comparison->pluck('call_rate_per_minute');
            $didSetupCosts = $comparison->pluck('did_setup_cost');
            $didMonthlyCosts = $comparison->pluck('did_monthly_cost');

            $metrics = [
                'call_rate' => [
                    'min' => $callRates->min(),
                    'max' => $callRates->max(),
                    'avg' => round($callRates->avg(), 4),
                    'difference' => round($callRates->max() - $callRates->min(), 4),
                ],
                'did_setup_cost' => [
                    'min' => $didSetupCosts->min(),
                    'max' => $didSetupCosts->max(),
                    'avg' => round($didSetupCosts->avg(), 2),
                    'difference' => round($didSetupCosts->max() - $didSetupCosts->min(), 2),
                ],
                'did_monthly_cost' => [
                    'min' => $didMonthlyCosts->min(),
                    'max' => $didMonthlyCosts->max(),
                    'avg' => round($didMonthlyCosts->avg(), 2),
                    'difference' => round($didMonthlyCosts->max() - $didMonthlyCosts->min(), 2),
                ],
            ];

            return response()->json([
                'success' => true,
                'comparison' => $comparison,
                'metrics' => $metrics,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate rate comparison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rate change history for audit tracking
     */
    public function changeHistory(Request $request, CountryRate $countryRate): JsonResponse
    {
        try {
            $history = AuditLog::where('metadata->country_rate_id', $countryRate->id)
                ->whereIn('action', ['country_rate_created', 'country_rate_updated', 'country_rate_bulk_updated'])
                ->with('user:id,name,email')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $formattedHistory = $history->getCollection()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user ? [
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'changes' => $log->metadata['changes'] ?? [],
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('M d, Y H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'country_rate' => [
                    'id' => $countryRate->id,
                    'country_name' => $countryRate->country_name,
                    'country_code' => $countryRate->country_code,
                ],
                'history' => $formattedHistory,
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve change history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export country rates to CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'active_only' => 'boolean',
            'include_statistics' => 'boolean',
        ]);

        try {
            $query = CountryRate::query();

            if ($request->boolean('active_only', false)) {
                $query->where('is_active', true);
            }

            if ($request->boolean('include_statistics', false)) {
                $query->withCount(['didNumbers as total_dids', 'didNumbers as assigned_dids' => function($q) {
                    $q->whereNotNull('user_id');
                }]);
            }

            $countryRates = $query->orderBy('country_name')->get();

            $filename = 'country_rates_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($countryRates, $request) {
                $file = fopen('php://output', 'w');
                
                // Write header
                $headerRow = [
                    'country_code', 'country_name', 'country_prefix',
                    'call_rate_per_minute', 'did_setup_cost', 'did_monthly_cost',
                    'sms_rate_per_message', 'billing_increment', 'minimum_duration',
                    'features', 'is_active'
                ];
                
                if ($request->boolean('include_statistics', false)) {
                    $headerRow = array_merge($headerRow, ['total_dids', 'assigned_dids', 'utilization_rate']);
                }
                
                fputcsv($file, $headerRow);

                // Write data
                foreach ($countryRates as $rate) {
                    $row = [
                        $rate->country_code,
                        $rate->country_name,
                        $rate->country_prefix,
                        $rate->call_rate_per_minute,
                        $rate->did_setup_cost,
                        $rate->did_monthly_cost,
                        $rate->sms_rate_per_message,
                        $rate->billing_increment,
                        $rate->minimum_duration,
                        implode(',', $rate->features ?? []),
                        $rate->is_active ? 'true' : 'false'
                    ];
                    
                    if ($request->boolean('include_statistics', false)) {
                        $totalDids = $rate->total_dids ?? 0;
                        $assignedDids = $rate->assigned_dids ?? 0;
                        $utilizationRate = $totalDids > 0 ? round(($assignedDids / $totalDids) * 100, 2) : 0;
                        
                        $row = array_merge($row, [$totalDids, $assignedDids, $utilizationRate]);
                    }
                    
                    fputcsv($file, $row);
                }

                fclose($file);
            };

            // Log the export
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'country_rates_exported',
                'description' => "Exported {$countryRates->count()} country rates to CSV",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'exported_count' => $countryRates->count(),
                    'active_only' => $request->boolean('active_only', false),
                    'include_statistics' => $request->boolean('include_statistics', false),
                ]
            ]);

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export country rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pricing analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $analytics = Cache::remember('country_rates_analytics', 300, function () {
                $countryRates = CountryRate::active()->get();
                
                return [
                    'total_countries' => $countryRates->count(),
                    'call_rate_statistics' => [
                        'min' => $countryRates->min('call_rate_per_minute'),
                        'max' => $countryRates->max('call_rate_per_minute'),
                        'avg' => round($countryRates->avg('call_rate_per_minute'), 4),
                        'median' => $countryRates->median('call_rate_per_minute'),
                    ],
                    'did_cost_statistics' => [
                        'setup_cost' => [
                            'min' => $countryRates->min('did_setup_cost'),
                            'max' => $countryRates->max('did_setup_cost'),
                            'avg' => round($countryRates->avg('did_setup_cost'), 2),
                        ],
                        'monthly_cost' => [
                            'min' => $countryRates->min('did_monthly_cost'),
                            'max' => $countryRates->max('did_monthly_cost'),
                            'avg' => round($countryRates->avg('did_monthly_cost'), 2),
                        ],
                    ],
                    'billing_increment_distribution' => $countryRates->groupBy('billing_increment')
                        ->map(function ($group, $increment) {
                            return [
                                'increment' => $increment,
                                'count' => $group->count(),
                                'percentage' => round(($group->count() / CountryRate::active()->count()) * 100, 2),
                            ];
                        })->values(),
                    'feature_distribution' => $this->getFeatureDistribution($countryRates),
                ];
            });

            return response()->json([
                'success' => true,
                'analytics' => $analytics,
                'generated_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get feature distribution
     */
    private function getFeatureDistribution($countryRates)
    {
        $allFeatures = ['voice', 'sms', 'fax', 'video'];
        $distribution = [];

        foreach ($allFeatures as $feature) {
            $count = $countryRates->filter(function ($rate) use ($feature) {
                return in_array($feature, $rate->features ?? []);
            })->count();

            $distribution[$feature] = [
                'count' => $count,
                'percentage' => round(($count / $countryRates->count()) * 100, 2),
            ];
        }

        return $distribution;
    }
}