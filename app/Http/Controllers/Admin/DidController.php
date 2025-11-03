<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DidNumber;
use App\Models\User;
use App\Models\AuditLog;
use App\Http\Requests\Admin\BulkUploadDidRequest;
use App\Http\Requests\Admin\BulkUpdateDidPricesRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DidController extends Controller
{
    /**
     * Display DID management interface
     */
    public function index(): View
    {
        $countries = \App\Models\CountryRate::active()->orderBy('country_name')->get();
        return view('admin.dids.index', compact('countries'));
    }

    /**
     * Get DIDs data for DataTables
     */
    public function getData(Request $request): JsonResponse
    {
        $query = DidNumber::with('user');

        // Search functionality
        if ($request->has('search') && !empty($request->search['value'])) {
            $searchValue = $request->search['value'];
            $query->where(function ($q) use ($searchValue) {
                $q->where('did_number', 'like', "%{$searchValue}%")
                  ->orWhere('country_code', 'like', "%{$searchValue}%")
                  ->orWhere('area_code', 'like', "%{$searchValue}%")
                  ->orWhereHas('user', function($uq) use ($searchValue) {
                      $uq->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('email', 'like', "%{$searchValue}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status_filter') && !empty($request->status_filter)) {
            $query->where('status', $request->status_filter);
        }

        // Filter by country
        if ($request->has('country_filter') && !empty($request->country_filter)) {
            $query->where('country_code', $request->country_filter);
        }

        // Filter by area code
        if ($request->has('area_code_filter') && !empty($request->area_code_filter)) {
            $query->where('area_code', $request->area_code_filter);
        }

        // Ordering
        if ($request->has('order')) {
            $columns = ['id', 'did_number', 'country_code', 'area_code', 'monthly_cost', 'status', 'user_id', 'assigned_at'];
            $orderColumn = $columns[$request->order[0]['column']] ?? 'created_at';
            $orderDirection = $request->order[0]['dir'] ?? 'desc';
            $query->orderBy($orderColumn, $orderDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $totalRecords = DidNumber::count();
        $filteredRecords = $query->count();

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 25;
        $dids = $query->skip($start)->take($length)->get();

        $data = $dids->map(function ($did) {
            return [
                'id' => $did->id,
                'did_number' => $did->formatted_number,
                'country_code' => $did->country_code,
                'area_code' => $did->area_code ?? 'N/A',
                'monthly_cost' => $did->formatted_monthly_cost,
                'setup_cost' => $did->formatted_setup_cost,
                'status' => ucfirst($did->status),
                'user_name' => $did->user ? $did->user->name : 'Unassigned',
                'user_email' => $did->user ? $did->user->email : 'N/A',
                'assigned_extension' => $did->assigned_extension ?? 'N/A',
                'assigned_at' => $did->assigned_at ? $did->assigned_at->format('M d, Y') : 'N/A',
                'expires_at' => $did->expires_at ? $did->expires_at->format('M d, Y') : 'N/A',
                'features' => $did->features ? implode(', ', $did->features) : 'Voice',
                'actions' => $did->id
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
     * Store new DID number
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'did_number' => 'required|string|unique:did_numbers',
            'country_code' => 'required|string|max:3',
            'area_code' => 'nullable|string|max:10',
            'provider' => 'nullable|string|max:255',
            'monthly_cost' => 'required|numeric|min:0',
            'setup_cost' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'features.*' => 'in:voice,sms,fax',
            'expires_at' => 'nullable|date|after:today'
        ]);

        try {
            DB::beginTransaction();

            $did = DidNumber::create([
                'did_number' => $request->did_number,
                'country_code' => strtoupper($request->country_code),
                'area_code' => $request->area_code,
                'provider' => $request->provider,
                'monthly_cost' => $request->monthly_cost,
                'setup_cost' => $request->setup_cost ?? 0,
                'status' => 'available',
                'features' => $request->features ?? ['voice'],
                'expires_at' => $request->expires_at
            ]);

            // Log the creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => 'did_created',
                'auditable_type' => DidNumber::class,
                'auditable_id' => $did->id,
                'old_values' => null,
                'new_values' => [
                    'did_number' => $did->did_number,
                    'country_code' => $did->country_code
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'DID number added successfully',
                'did' => [
                    'id' => $did->id,
                    'did_number' => $did->formatted_number,
                    'country_code' => $did->country_code
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add DID number',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign DID to customer
     */
    public function assign(Request $request, DidNumber $did): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'extension' => 'nullable|string|max:10'
        ]);

        if ($did->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'DID number is not available for assignment'
            ], 422);
        }

        $user = User::findOrFail($request->user_id);

        if (!in_array($user->role, ['customer', 'operator'])) {
            return response()->json([
                'success' => false,
                'message' => 'Can only assign DIDs to customers or operators'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $did->assignToUser($user, $request->extension);

            // Charge setup cost if applicable
            if ($did->setup_cost > 0) {
                $balanceService = app(\App\Services\BalanceService::class);
                $balanceService->deductBalance($user->id, $did->setup_cost, 'did_setup_fee', [
                    'did_number' => $did->did_number,
                    'setup_cost' => $did->setup_cost
                ]);
            }

            // Log the assignment
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => 'did_assigned',
                'auditable_type' => DidNumber::class,
                'auditable_id' => $did->id,
                'old_values' => ['status' => 'available'],
                'new_values' => [
                    'status' => 'assigned',
                    'customer_id' => $user->id,
                    'extension' => $request->extension
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "DID {$did->formatted_number} assigned to {$user->name} successfully"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign DID: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Release DID from customer
     */
    public function release(Request $request, DidNumber $did): JsonResponse
    {
        if ($did->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'DID is not currently assigned'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $userName = $did->user ? $did->user->name : 'Unknown';
            $didNumber = $did->did_number;

            $did->release();

            // Log the release
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => 'did_released',
                'auditable_type' => DidNumber::class,
                'auditable_id' => $did->id,
                'old_values' => ['status' => 'assigned'],
                'new_values' => ['status' => 'available'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "DID {$did->formatted_number} released successfully"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to release DID: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update DID number
     */
    public function update(Request $request, DidNumber $did): JsonResponse
    {
        $request->validate([
            'monthly_cost' => 'required|numeric|min:0',
            'setup_cost' => 'nullable|numeric|min:0',
            'provider' => 'nullable|string|max:255',
            'features' => 'nullable|array',
            'features.*' => 'in:voice,sms,fax',
            'expires_at' => 'nullable|date'
        ]);

        try {
            DB::beginTransaction();

            $originalData = $did->toArray();

            $did->update([
                'monthly_cost' => $request->monthly_cost,
                'setup_cost' => $request->setup_cost ?? $did->setup_cost,
                'provider' => $request->provider,
                'features' => $request->features ?? $did->features,
                'expires_at' => $request->expires_at
            ]);

            // Log the update
            $changes = array_diff_assoc($did->toArray(), $originalData);
            
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => 'did_updated',
                'auditable_type' => DidNumber::class,
                'auditable_id' => $did->id,
                'old_values' => $originalData,
                'new_values' => $changes,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'DID updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update DID: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete DID number
     */
    public function destroy(Request $request, DidNumber $did): JsonResponse
    {
        if ($did->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete assigned DID number. Release it first.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $didNumber = $did->did_number;
            $did->delete();

            // Log the deletion
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => 'did_deleted',
                'old_values' => ['did_number' => $didNumber],
                'new_values' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'DID deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete DID: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available DIDs for assignment
     */
    public function getAvailable(Request $request): JsonResponse
    {
        $query = DidNumber::available();

        if ($request->filled('country')) {
            $query->byCountry($request->country);
        }

        if ($request->filled('area_code')) {
            $query->byAreaCode($request->area_code);
        }

        $dids = $query->orderBy('did_number')->get()->map(function ($did) {
            return [
                'id' => $did->id,
                'did_number' => $did->formatted_number,
                'country_code' => $did->country_code,
                'area_code' => $did->area_code,
                'monthly_cost' => $did->formatted_monthly_cost,
                'setup_cost' => $did->formatted_setup_cost,
                'features' => $did->features
            ];
        });

        return response()->json([
            'success' => true,
            'dids' => $dids
        ]);
    }

    /**
     * Download CSV template for bulk upload
     */
    public function downloadTemplate(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $countryCode = $request->get('country', 'US');
        $countryRate = \App\Models\CountryRate::where('country_code', $countryCode)->first();
        
        $filename = "did_template_{$countryCode}_" . date('Y-m-d') . ".csv";
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($countryRate, $countryCode) {
            $handle = fopen('php://output', 'w');
            
            // Write CSV header
            fputcsv($handle, [
                'did_number',
                'country_code', 
                'area_code',
                'provider',
                'monthly_cost',
                'setup_cost',
                'features',
                'expires_at'
            ]);
            
            // Write sample rows with default values from country rate
            $defaultMonthlyCost = $countryRate ? $countryRate->did_monthly_cost : '5.00';
            $defaultSetupCost = $countryRate ? $countryRate->did_setup_cost : '0.00';
            
            // Add 3 sample rows
            for ($i = 1; $i <= 3; $i++) {
                fputcsv($handle, [
                    "1555123456{$i}", // Sample DID number
                    $countryCode,
                    '555', // Sample area code
                    'Sample Provider',
                    $defaultMonthlyCost,
                    $defaultSetupCost,
                    'voice,sms', // Sample features
                    '' // No expiry date
                ]);
            }
            
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Bulk upload DID numbers from CSV
     */
    public function bulkUpload(BulkUploadDidRequest $request): JsonResponse
    {

        try {
            $file = $request->file('csv_file');
            $path = $file->getRealPath();
            
            if (!file_exists($path)) {
                throw new \Exception('Uploaded file not found');
            }

            $csvData = array_map(function($line) { return str_getcsv($line, ',', '"', '\\'); }, file($path));
            $header = array_shift($csvData); // Remove header row
            
            // Validate CSV header
            $expectedHeaders = ['did_number', 'country_code', 'area_code', 'provider', 'monthly_cost', 'setup_cost', 'features', 'expires_at'];
            $headerDiff = array_diff($expectedHeaders, $header);
            
            if (!empty($headerDiff)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid CSV format. Missing columns: ' . implode(', ', $headerDiff)
                ], 422);
            }

            $results = [
                'total' => count($csvData),
                'success' => 0,
                'errors' => 0,
                'error_details' => []
            ];

            DB::beginTransaction();

            foreach ($csvData as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 because we removed header and arrays are 0-indexed
                
                try {
                    // Skip empty rows
                    if (empty(array_filter($row))) {
                        continue;
                    }
                    
                    // Map CSV row to array
                    $data = array_combine($header, $row);
                    
                    // Validate required fields
                    if (empty($data['did_number'])) {
                        throw new \Exception("DID number is required");
                    }
                    
                    // Use default country if not provided in CSV
                    $countryCode = !empty($data['country_code']) ? strtoupper($data['country_code']) : $request->country_code;
                    
                    // Validate country exists
                    $countryRate = \App\Models\CountryRate::where('country_code', $countryCode)->first();
                    if (!$countryRate) {
                        throw new \Exception("Invalid country code: {$countryCode}");
                    }
                    
                    // Check if DID already exists
                    if (DidNumber::where('did_number', $data['did_number'])->exists()) {
                        throw new \Exception("DID number already exists: {$data['did_number']}");
                    }
                    
                    // Parse features
                    $features = ['voice']; // Default feature
                    if (!empty($data['features'])) {
                        $featuresArray = array_map('trim', explode(',', $data['features']));
                        $validFeatures = array_intersect($featuresArray, ['voice', 'sms', 'fax']);
                        if (!empty($validFeatures)) {
                            $features = array_values($validFeatures);
                        }
                    }
                    
                    // Use country defaults if costs not provided
                    $monthlyCost = !empty($data['monthly_cost']) ? (float)$data['monthly_cost'] : $countryRate->did_monthly_cost;
                    $setupCost = !empty($data['setup_cost']) ? (float)$data['setup_cost'] : $countryRate->did_setup_cost;
                    
                    // Parse expiry date
                    $expiresAt = null;
                    if (!empty($data['expires_at'])) {
                        try {
                            $expiresAt = \Illuminate\Support\Carbon::parse($data['expires_at']);
                        } catch (\Exception $e) {
                            // Invalid date format, ignore
                        }
                    }
                    
                    // Create DID number
                    DidNumber::create([
                        'did_number' => $data['did_number'],
                        'country_code' => $countryCode,
                        'area_code' => $data['area_code'] ?? null,
                        'provider' => $data['provider'] ?? null,
                        'monthly_cost' => $monthlyCost,
                        'setup_cost' => $setupCost,
                        'status' => 'available',
                        'features' => $features,
                        'expires_at' => $expiresAt
                    ]);
                    
                    $results['success']++;
                    
                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['error_details'][] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }

            // Log the bulk upload
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => 'did_bulk_upload',
                'old_values' => null,
                'new_values' => [
                    'filename' => $file->getClientOriginalName(),
                    'total_rows' => $results['total'],
                    'success_count' => $results['success'],
                    'error_count' => $results['errors'],
                    'country_code' => $request->country_code
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Bulk upload completed. {$results['success']} DIDs added successfully.",
                'results' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Bulk upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update DID prices
     */
    public function bulkUpdatePrices(BulkUpdateDidPricesRequest $request): JsonResponse
    {

        try {
            DB::beginTransaction();

            $query = DidNumber::query();

            // Apply filters
            if ($request->filled('filter_country')) {
                $query->where('country_code', $request->filter_country);
            }

            if ($request->filled('filter_status')) {
                $query->where('status', $request->filter_status);
            }

            if ($request->filled('filter_area_code')) {
                $query->where('area_code', $request->filter_area_code);
            }

            $dids = $query->get();
            $updateCount = 0;

            foreach ($dids as $did) {
                $updates = [];
                
                if ($request->input('update_monthly_cost')) {
                    switch ($request->update_type) {
                        case 'set':
                            $updates['monthly_cost'] = $request->update_amount;
                            break;
                        case 'increase':
                            $updates['monthly_cost'] = $did->monthly_cost + $request->update_amount;
                            break;
                        case 'decrease':
                            $updates['monthly_cost'] = max(0, $did->monthly_cost - $request->update_amount);
                            break;
                    }
                }

                if ($request->input('update_setup_cost')) {
                    switch ($request->update_type) {
                        case 'set':
                            $updates['setup_cost'] = $request->update_amount;
                            break;
                        case 'increase':
                            $updates['setup_cost'] = $did->setup_cost + $request->update_amount;
                            break;
                        case 'decrease':
                            $updates['setup_cost'] = max(0, $did->setup_cost - $request->update_amount);
                            break;
                    }
                }

                if (!empty($updates)) {
                    $did->update($updates);
                    $updateCount++;
                }
            }

            // Log the bulk update
            AuditLog::create([
                'user_id' => auth()->id(),
                'event' => 'did_bulk_price_update',
                'old_values' => null,
                'new_values' => [
                    'update_count' => $updateCount,
                    'filters' => $request->only(['filter_country', 'filter_status', 'filter_area_code']),
                    'updates' => $request->only(['update_monthly_cost', 'update_setup_cost', 'update_type', 'update_amount'])
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated prices for {$updateCount} DID numbers",
                'updated_count' => $updateCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Bulk price update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DID statistics for dashboard
     */
    public function getStatistics(): JsonResponse
    {
        $stats = [
            'total' => DidNumber::count(),
            'available' => DidNumber::where('status', 'available')->count(),
            'assigned' => DidNumber::where('status', 'assigned')->count(),
            'suspended' => DidNumber::where('status', 'suspended')->count(),
            'expired' => DidNumber::where('status', 'expired')->count(),
            'monthly_revenue' => DidNumber::where('status', 'assigned')->sum('monthly_cost')
        ];

        return response()->json($stats);
    }
}