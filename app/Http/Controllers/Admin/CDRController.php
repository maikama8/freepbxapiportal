<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EnhancedCDRService;
use App\Models\CallRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CDRController extends Controller
{
    protected $enhancedCDRService;

    public function __construct(EnhancedCDRService $enhancedCDRService)
    {
        $this->enhancedCDRService = $enhancedCDRService;
    }

    /**
     * Display CDR management dashboard
     */
    public function index()
    {
        $stats = $this->enhancedCDRService->getCDRProcessingStats();
        
        // Get recent call records
        $recentCalls = CallRecord::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('admin.cdr.index', compact('stats', 'recentCalls'));
    }

    /**
     * Get CDR data for DataTables
     */
    public function getData(Request $request)
    {
        $query = CallRecord::with('user');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('billing_status')) {
            $query->where('billing_status', $request->billing_status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('destination')) {
            $query->where('destination', 'like', '%' . $request->destination . '%');
        }

        // DataTables parameters
        $start = $request->input('start', 0);
        $length = $request->input('length', 25);
        $search = $request->input('search.value');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('call_id', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%")
                  ->orWhere('caller_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $totalRecords = $query->count();
        
        $records = $query->skip($start)
            ->take($length)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $records->map(function ($record) {
            return [
                'id' => $record->id,
                'call_id' => $record->call_id,
                'user' => $record->user->name ?? 'Unknown',
                'caller_id' => $record->caller_id,
                'destination' => $record->destination,
                'start_time' => $record->start_time ? $record->start_time->format('Y-m-d H:i:s') : '-',
                'end_time' => $record->end_time ? $record->end_time->format('Y-m-d H:i:s') : '-',
                'duration' => $record->getFormattedDuration(),
                'actual_duration' => $record->actual_duration ? gmdate('H:i:s', $record->actual_duration) : '-',
                'billable_duration' => $record->billable_duration ? gmdate('H:i:s', $record->billable_duration) : '-',
                'cost' => $record->cost ? '$' . number_format($record->cost, 4) : '$0.00',
                'status' => $record->status,
                'billing_status' => $record->billing_status,
                'created_at' => $record->created_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'draw' => $request->input('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data
        ]);
    }

    /**
     * Show detailed CDR record
     */
    public function show(CallRecord $callRecord)
    {
        $callRecord->load('user');
        
        // Parse billing details
        $billingDetails = json_decode($callRecord->billing_details, true) ?? [];
        
        return view('admin.cdr.show', compact('callRecord', 'billingDetails'));
    }

    /**
     * Process unprocessed CDR records
     */
    public function processUnprocessed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $limit = $request->input('limit', 100);
            $result = $this->enhancedCDRService->processUnprocessedCDRs($limit);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] 
                    ? "Processed {$result['processed']} records" . ($result['failed'] > 0 ? ", {$result['failed']} failed" : '')
                    : $result['error'],
                'processed' => $result['processed'],
                'failed' => $result['failed']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process CDR records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reprocess billing for a specific call record
     */
    public function reprocessBilling(CallRecord $callRecord)
    {
        try {
            // Reset billing status
            $callRecord->update([
                'billing_status' => 'pending',
                'cost' => null,
                'billing_details' => null
            ]);

            // Process billing again
            $result = $this->enhancedCDRService->processUnprocessedCDRs(1);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] 
                    ? 'Billing reprocessed successfully'
                    : 'Failed to reprocess billing',
                'call_record' => $callRecord->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reprocess billing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CDR processing statistics
     */
    public function getStatistics()
    {
        try {
            $stats = $this->enhancedCDRService->getCDRProcessingStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export CDR records
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|in:csv,json',
            'date_from' => 'date',
            'date_to' => 'date',
            'status' => 'string',
            'billing_status' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = CallRecord::with('user');

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('billing_status')) {
                $query->where('billing_status', $request->billing_status);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $records = $query->orderBy('created_at', 'desc')->get();

            if ($request->format === 'csv') {
                return $this->exportCSV($records);
            } else {
                return $this->exportJSON($records);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export CDR records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export records as CSV
     */
    protected function exportCSV($records)
    {
        $filename = 'cdr_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($records) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'Call ID', 'User', 'Caller ID', 'Destination', 'Start Time', 'End Time',
                'Duration', 'Actual Duration', 'Billable Duration', 'Cost', 'Status',
                'Billing Status', 'Created At'
            ]);

            foreach ($records as $record) {
                fputcsv($file, [
                    $record->call_id,
                    $record->user->name ?? 'Unknown',
                    $record->caller_id,
                    $record->destination,
                    $record->start_time ? $record->start_time->format('Y-m-d H:i:s') : '',
                    $record->end_time ? $record->end_time->format('Y-m-d H:i:s') : '',
                    $record->getFormattedDuration(),
                    $record->actual_duration ? gmdate('H:i:s', $record->actual_duration) : '',
                    $record->billable_duration ? gmdate('H:i:s', $record->billable_duration) : '',
                    $record->cost ?? 0,
                    $record->status,
                    $record->billing_status,
                    $record->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export records as JSON
     */
    protected function exportJSON($records)
    {
        $filename = 'cdr_export_' . now()->format('Y-m-d_H-i-s') . '.json';
        
        $data = $records->map(function ($record) {
            return [
                'call_id' => $record->call_id,
                'user' => $record->user->name ?? 'Unknown',
                'caller_id' => $record->caller_id,
                'destination' => $record->destination,
                'start_time' => $record->start_time ? $record->start_time->toISOString() : null,
                'end_time' => $record->end_time ? $record->end_time->toISOString() : null,
                'duration' => $record->getDurationInSeconds(),
                'actual_duration' => $record->actual_duration,
                'billable_duration' => $record->billable_duration,
                'cost' => $record->cost,
                'status' => $record->status,
                'billing_status' => $record->billing_status,
                'billing_details' => json_decode($record->billing_details, true),
                'created_at' => $record->created_at->toISOString()
            ];
        });

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}