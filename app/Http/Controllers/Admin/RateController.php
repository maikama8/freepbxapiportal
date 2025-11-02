<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallRate;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RateController extends Controller
{
    /**
     * Display rate management interface
     */
    public function index(Request $request): View
    {
        return view('admin.rates.index');
    }

    /**
     * Get rates data for DataTables
     */
    public function getData(Request $request): JsonResponse
    {
        $query = CallRate::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('destination_prefix', 'like', "%{$searchValue}%")
                  ->orWhere('destination_name', 'like', "%{$searchValue}%");
            });
        }

        // Filter by active status
        if ($request->has('status_filter') && $request->status_filter !== '') {
            $query->where('is_active', $request->status_filter === '1');
        }

        // Filter by effective date range
        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->where('effective_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->where('effective_date', '<=', $request->date_to . ' 23:59:59');
        }

        // Ordering
        if ($request->has('order')) {
            $columns = ['id', 'destination_prefix', 'destination_name', 'rate_per_minute', 'minimum_duration', 'billing_increment', 'effective_date', 'is_active'];
            $orderColumn = $columns[$request->order[0]['column']] ?? 'destination_prefix';
            $orderDirection = $request->order[0]['dir'] ?? 'asc';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderBy('destination_prefix', 'asc');
        }

        $totalRecords = CallRate::count();
        $filteredRecords = $query->count();

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 25;
        $rates = $query->skip($start)->take($length)->get();

        $data = $rates->map(function ($rate) {
            return [
                'id' => $rate->id,
                'destination_prefix' => $rate->destination_prefix,
                'destination_name' => $rate->destination_name,
                'rate_per_minute' => number_format($rate->rate_per_minute, 6),
                'minimum_duration' => $rate->minimum_duration,
                'billing_increment' => $rate->billing_increment,
                'effective_date' => $rate->effective_date->format('M d, Y H:i'),
                'is_active' => $rate->is_active,
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
     * Store new rate
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'destination_prefix' => 'required|string|max:20',
            'destination_name' => 'required|string|max:255',
            'rate_per_minute' => 'required|numeric|min:0|max:999.999999',
            'minimum_duration' => 'required|integer|min:1|max:3600',
            'billing_increment' => 'required|integer|min:1|max:3600',
            'effective_date' => 'required|date',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Check for existing active rate with same prefix and effective date
            $existingRate = CallRate::where('destination_prefix', $request->destination_prefix)
                ->where('effective_date', $request->effective_date)
                ->where('is_active', true)
                ->first();

            if ($existingRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active rate already exists for this prefix and effective date'
                ], 422);
            }

            $rate = CallRate::create([
                'destination_prefix' => $request->destination_prefix,
                'destination_name' => $request->destination_name,
                'rate_per_minute' => $request->rate_per_minute,
                'minimum_duration' => $request->minimum_duration,
                'billing_increment' => $request->billing_increment,
                'effective_date' => $request->effective_date,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Log the creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'rate_created',
                'description' => "Created rate for {$rate->destination_name} ({$rate->destination_prefix})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'rate_id' => $rate->id,
                    'destination_prefix' => $rate->destination_prefix,
                    'rate_per_minute' => $rate->rate_per_minute,
                    'effective_date' => $rate->effective_date,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rate created successfully',
                'rate' => [
                    'id' => $rate->id,
                    'destination_prefix' => $rate->destination_prefix,
                    'destination_name' => $rate->destination_name,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show rate details
     */
    public function show(CallRate $rate): JsonResponse
    {
        return response()->json([
            'success' => true,
            'rate' => [
                'id' => $rate->id,
                'destination_prefix' => $rate->destination_prefix,
                'destination_name' => $rate->destination_name,
                'rate_per_minute' => $rate->rate_per_minute,
                'minimum_duration' => $rate->minimum_duration,
                'billing_increment' => $rate->billing_increment,
                'effective_date' => $rate->effective_date->format('Y-m-d\TH:i'),
                'is_active' => $rate->is_active,
                'created_at' => $rate->created_at,
                'updated_at' => $rate->updated_at,
            ]
        ]);
    }

    /**
     * Update rate
     */
    public function update(Request $request, CallRate $rate): JsonResponse
    {
        $request->validate([
            'destination_prefix' => 'required|string|max:20',
            'destination_name' => 'required|string|max:255',
            'rate_per_minute' => 'required|numeric|min:0|max:999.999999',
            'minimum_duration' => 'required|integer|min:1|max:3600',
            'billing_increment' => 'required|integer|min:1|max:3600',
            'effective_date' => 'required|date',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Check for existing active rate with same prefix and effective date (excluding current rate)
            $existingRate = CallRate::where('destination_prefix', $request->destination_prefix)
                ->where('effective_date', $request->effective_date)
                ->where('is_active', true)
                ->where('id', '!=', $rate->id)
                ->first();

            if ($existingRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active rate already exists for this prefix and effective date'
                ], 422);
            }

            $originalData = $rate->toArray();

            $rate->update([
                'destination_prefix' => $request->destination_prefix,
                'destination_name' => $request->destination_name,
                'rate_per_minute' => $request->rate_per_minute,
                'minimum_duration' => $request->minimum_duration,
                'billing_increment' => $request->billing_increment,
                'effective_date' => $request->effective_date,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Log the update
            $changes = array_diff_assoc($rate->toArray(), $originalData);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'rate_updated',
                'description' => "Updated rate for {$rate->destination_name} ({$rate->destination_prefix})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'rate_id' => $rate->id,
                    'changes' => $changes,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rate updated successfully',
                'rate' => [
                    'id' => $rate->id,
                    'destination_prefix' => $rate->destination_prefix,
                    'destination_name' => $rate->destination_name,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete rate
     */
    public function destroy(Request $request, CallRate $rate): JsonResponse
    {
        try {
            DB::beginTransaction();

            $rateName = $rate->destination_name;
            $ratePrefix = $rate->destination_prefix;
            $rateId = $rate->id;

            $rate->delete();

            // Log the deletion
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'rate_deleted',
                'description' => "Deleted rate for {$rateName} ({$ratePrefix})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'rate_id' => $rateId,
                    'destination_prefix' => $ratePrefix,
                    'destination_name' => $rateName,
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rate deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk import rates from CSV
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'effective_date' => 'required|date',
            'replace_existing' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('csv_file');
            $effectiveDate = $request->effective_date;
            $replaceExisting = $request->boolean('replace_existing', false);

            // Read CSV file
            $csvData = array_map('str_getcsv', file($file->getPathname()));
            $header = array_shift($csvData); // Remove header row

            // Validate CSV format
            $expectedHeaders = ['destination_prefix', 'destination_name', 'rate_per_minute', 'minimum_duration', 'billing_increment'];
            $headerMap = [];
            
            foreach ($expectedHeaders as $expectedHeader) {
                $headerIndex = array_search($expectedHeader, $header);
                if ($headerIndex === false) {
                    return response()->json([
                        'success' => false,
                        'message' => "Missing required column: {$expectedHeader}"
                    ], 422);
                }
                $headerMap[$expectedHeader] = $headerIndex;
            }

            $importedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($csvData as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 because we removed header and arrays are 0-indexed
                
                try {
                    $destinationPrefix = trim($row[$headerMap['destination_prefix']] ?? '');
                    $destinationName = trim($row[$headerMap['destination_name']] ?? '');
                    $ratePerMinute = floatval($row[$headerMap['rate_per_minute']] ?? 0);
                    $minimumDuration = intval($row[$headerMap['minimum_duration']] ?? 60);
                    $billingIncrement = intval($row[$headerMap['billing_increment']] ?? 60);

                    // Validate row data
                    if (empty($destinationPrefix) || empty($destinationName) || $ratePerMinute <= 0) {
                        $errors[] = "Row {$rowNumber}: Invalid data (prefix, name, or rate missing/invalid)";
                        $skippedCount++;
                        continue;
                    }

                    // Check if rate already exists
                    $existingRate = CallRate::where('destination_prefix', $destinationPrefix)
                        ->where('effective_date', $effectiveDate)
                        ->first();

                    if ($existingRate) {
                        if ($replaceExisting) {
                            $existingRate->update([
                                'destination_name' => $destinationName,
                                'rate_per_minute' => $ratePerMinute,
                                'minimum_duration' => $minimumDuration,
                                'billing_increment' => $billingIncrement,
                                'is_active' => true,
                            ]);
                            $importedCount++;
                        } else {
                            $errors[] = "Row {$rowNumber}: Rate for prefix {$destinationPrefix} already exists";
                            $skippedCount++;
                        }
                    } else {
                        CallRate::create([
                            'destination_prefix' => $destinationPrefix,
                            'destination_name' => $destinationName,
                            'rate_per_minute' => $ratePerMinute,
                            'minimum_duration' => $minimumDuration,
                            'billing_increment' => $billingIncrement,
                            'effective_date' => $effectiveDate,
                            'is_active' => true,
                        ]);
                        $importedCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: {$e->getMessage()}";
                    $skippedCount++;
                }
            }

            // Log the bulk import
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'rates_bulk_imported',
                'description' => "Bulk imported {$importedCount} rates, skipped {$skippedCount}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                    'effective_date' => $effectiveDate,
                    'replace_existing' => $replaceExisting,
                    'errors' => array_slice($errors, 0, 10), // Limit errors in log
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import completed. {$importedCount} rates imported, {$skippedCount} skipped.",
                'data' => [
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                    'errors' => $errors,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to import rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export rates to CSV
     */
    public function export(Request $request)
    {
        $request->validate([
            'active_only' => 'boolean',
            'effective_date_from' => 'nullable|date',
            'effective_date_to' => 'nullable|date',
        ]);

        try {
            $query = CallRate::query();

            if ($request->boolean('active_only', false)) {
                $query->where('is_active', true);
            }

            if ($request->effective_date_from) {
                $query->where('effective_date', '>=', $request->effective_date_from);
            }

            if ($request->effective_date_to) {
                $query->where('effective_date', '<=', $request->effective_date_to . ' 23:59:59');
            }

            $rates = $query->orderBy('destination_prefix')->get();

            $filename = 'call_rates_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($rates) {
                $file = fopen('php://output', 'w');
                
                // Write header
                fputcsv($file, [
                    'destination_prefix',
                    'destination_name', 
                    'rate_per_minute',
                    'minimum_duration',
                    'billing_increment',
                    'effective_date',
                    'is_active'
                ]);

                // Write data
                foreach ($rates as $rate) {
                    fputcsv($file, [
                        $rate->destination_prefix,
                        $rate->destination_name,
                        $rate->rate_per_minute,
                        $rate->minimum_duration,
                        $rate->billing_increment,
                        $rate->effective_date->format('Y-m-d H:i:s'),
                        $rate->is_active ? 'true' : 'false'
                    ]);
                }

                fclose($file);
            };

            // Log the export
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'rates_exported',
                'description' => "Exported {$rates->count()} rates to CSV",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'exported_count' => $rates->count(),
                    'active_only' => $request->boolean('active_only', false),
                    'date_range' => [
                        'from' => $request->effective_date_from,
                        'to' => $request->effective_date_to,
                    ]
                ]
            ]);

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export rates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rate history for a specific prefix
     */
    public function history(Request $request, string $prefix): JsonResponse
    {
        try {
            $rates = CallRate::where('destination_prefix', $prefix)
                ->orderBy('effective_date', 'desc')
                ->get();

            $history = $rates->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'rate_per_minute' => number_format($rate->rate_per_minute, 6),
                    'minimum_duration' => $rate->minimum_duration,
                    'billing_increment' => $rate->billing_increment,
                    'effective_date' => $rate->effective_date->format('M d, Y H:i'),
                    'is_active' => $rate->is_active,
                    'created_at' => $rate->created_at->format('M d, Y H:i'),
                ];
            });

            return response()->json([
                'success' => true,
                'prefix' => $prefix,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rate history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test rate calculation
     */
    public function testRate(Request $request): JsonResponse
    {
        $request->validate([
            'destination' => 'required|string',
            'duration' => 'required|integer|min:1',
        ]);

        try {
            $destination = $request->destination;
            $duration = $request->duration;

            $rate = CallRate::findRateForDestination($destination);

            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rate found for destination',
                    'destination' => $destination
                ]);
            }

            $cost = $rate->calculateCost($duration);

            return response()->json([
                'success' => true,
                'destination' => $destination,
                'duration_seconds' => $duration,
                'matched_rate' => [
                    'id' => $rate->id,
                    'destination_prefix' => $rate->destination_prefix,
                    'destination_name' => $rate->destination_name,
                    'rate_per_minute' => number_format($rate->rate_per_minute, 6),
                    'minimum_duration' => $rate->minimum_duration,
                    'billing_increment' => $rate->billing_increment,
                ],
                'calculated_cost' => number_format($cost, 4),
                'billing_details' => [
                    'raw_duration' => $duration,
                    'minimum_duration' => $rate->minimum_duration,
                    'billing_increment' => $rate->billing_increment,
                    'billable_duration' => max($duration, $rate->minimum_duration),
                    'billable_minutes' => round(max($duration, $rate->minimum_duration) / 60, 4),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test rate calculation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}