<?php

namespace App\Services\FreePBX;

use App\Models\User;
use App\Models\CallRecord;
use App\Exceptions\FreePBXApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class CDRService
{
    protected FreePBXApiClient $apiClient;

    public function __construct(FreePBXApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Retrieve CDR data from FreePBX for a specific date range
     */
    public function retrieveCDRData(Carbon $startDate, Carbon $endDate, array $filters = []): Collection
    {
        try {
            $params = [
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s'),
                'limit' => $filters['limit'] ?? 1000,
                'offset' => $filters['offset'] ?? 0
            ];

            // Add optional filters
            if (isset($filters['extension'])) {
                $params['extension'] = $filters['extension'];
            }

            if (isset($filters['destination'])) {
                $params['destination'] = $filters['destination'];
            }

            if (isset($filters['disposition'])) {
                $params['disposition'] = $filters['disposition'];
            }

            Log::info('Retrieving CDR data from FreePBX', $params);

            $response = $this->apiClient->get('cdr', $params);

            return collect($response['data'] ?? []);

        } catch (FreePBXApiException $e) {
            Log::error('Failed to retrieve CDR data', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Process and store CDR records in local database
     */
    public function processCDRRecords(Collection $cdrData): array
    {
        $processed = 0;
        $updated = 0;
        $errors = [];

        foreach ($cdrData as $cdrRecord) {
            try {
                $result = $this->processSingleCDRRecord($cdrRecord);
                
                if ($result['action'] === 'created') {
                    $processed++;
                } elseif ($result['action'] === 'updated') {
                    $updated++;
                }

            } catch (\Exception $e) {
                $errors[] = [
                    'cdr_record' => $cdrRecord,
                    'error' => $e->getMessage()
                ];

                Log::error('Failed to process CDR record', [
                    'cdr_record' => $cdrRecord,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('CDR processing completed', [
            'processed' => $processed,
            'updated' => $updated,
            'errors' => count($errors)
        ]);

        return [
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors
        ];
    }

    /**
     * Process a single CDR record
     */
    protected function processSingleCDRRecord(array $cdrRecord): array
    {
        // Map FreePBX CDR fields to our CallRecord model
        $callData = $this->mapCDRFields($cdrRecord);

        // Find existing call record by call_id or create new one
        $callRecord = CallRecord::where('call_id', $callData['call_id'])->first();

        if ($callRecord) {
            // Update existing record with CDR data
            $callRecord->update($callData);
            return ['action' => 'updated', 'record' => $callRecord];
        } else {
            // Create new call record
            $callRecord = CallRecord::create($callData);
            return ['action' => 'created', 'record' => $callRecord];
        }
    }

    /**
     * Map FreePBX CDR fields to CallRecord model fields
     */
    protected function mapCDRFields(array $cdrRecord): array
    {
        // Find user by extension or SIP username
        $user = $this->findUserByExtension($cdrRecord['src'] ?? $cdrRecord['extension'] ?? null);

        return [
            'user_id' => $user?->id,
            'call_id' => $cdrRecord['uniqueid'] ?? $cdrRecord['call_id'] ?? null,
            'caller_id' => $cdrRecord['clid'] ?? $cdrRecord['caller_id'] ?? $cdrRecord['src'] ?? null,
            'destination' => $cdrRecord['dst'] ?? $cdrRecord['destination'] ?? null,
            'start_time' => $this->parseDateTime($cdrRecord['calldate'] ?? $cdrRecord['start_time'] ?? null),
            'end_time' => $this->parseDateTime($cdrRecord['end_time'] ?? null) ?? 
                         $this->calculateEndTime($cdrRecord),
            'duration' => (int)($cdrRecord['duration'] ?? $cdrRecord['billsec'] ?? 0),
            'cost' => $this->calculateCallCost($cdrRecord, $user),
            'status' => $this->mapCallStatus($cdrRecord['disposition'] ?? 'UNKNOWN'),
            'freepbx_response' => $cdrRecord
        ];
    }

    /**
     * Find user by extension number
     */
    protected function findUserByExtension(?string $extension): ?User
    {
        if (!$extension) {
            return null;
        }

        return User::where('extension', $extension)
            ->orWhere('sip_username', $extension)
            ->first();
    }

    /**
     * Parse datetime from various formats
     */
    protected function parseDateTime(?string $datetime): ?Carbon
    {
        if (!$datetime) {
            return null;
        }

        try {
            return Carbon::parse($datetime);
        } catch (\Exception $e) {
            Log::warning('Failed to parse datetime', [
                'datetime' => $datetime,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Calculate end time from start time and duration
     */
    protected function calculateEndTime(array $cdrRecord): ?Carbon
    {
        $startTime = $this->parseDateTime($cdrRecord['calldate'] ?? $cdrRecord['start_time'] ?? null);
        $duration = (int)($cdrRecord['duration'] ?? $cdrRecord['billsec'] ?? 0);

        if ($startTime && $duration > 0) {
            return $startTime->copy()->addSeconds($duration);
        }

        return null;
    }

    /**
     * Calculate call cost based on duration and rates
     */
    protected function calculateCallCost(array $cdrRecord, ?User $user): ?float
    {
        $duration = (int)($cdrRecord['billsec'] ?? $cdrRecord['duration'] ?? 0);
        
        if ($duration <= 0) {
            return 0.0;
        }

        // Get rate for destination (this would integrate with rate management)
        $destination = $cdrRecord['dst'] ?? $cdrRecord['destination'] ?? '';
        $rate = $this->getDestinationRate($destination);

        // Calculate cost based on billing increment
        $billingIncrement = config('voip.rating.billing_increment', 60);
        $billedDuration = ceil($duration / $billingIncrement) * $billingIncrement;
        
        return round(($billedDuration / 60) * $rate, 4);
    }

    /**
     * Get rate for destination (placeholder - would integrate with rate management)
     */
    protected function getDestinationRate(string $destination): float
    {
        // This is a placeholder - in a real implementation, this would
        // look up rates from a rate table based on destination prefix
        return config('voip.rating.default_rate', 0.05);
    }

    /**
     * Map FreePBX call disposition to our status
     */
    protected function mapCallStatus(string $disposition): string
    {
        return match (strtoupper($disposition)) {
            'ANSWERED' => 'completed',
            'BUSY' => 'busy',
            'NO ANSWER', 'NOANSWER' => 'no_answer',
            'FAILED' => 'failed',
            'CONGESTION' => 'failed',
            'CANCEL' => 'cancelled',
            default => 'unknown'
        };
    }

    /**
     * Get call history for a user
     */
    public function getCallHistory(User $user, array $filters = []): Collection
    {
        $query = $user->callRecords()->orderBy('start_time', 'desc');

        // Apply filters
        if (isset($filters['start_date'])) {
            $query->where('start_time', '>=', Carbon::parse($filters['start_date']));
        }

        if (isset($filters['end_date'])) {
            $query->where('start_time', '<=', Carbon::parse($filters['end_date']));
        }

        if (isset($filters['destination'])) {
            $query->where('destination', 'like', '%' . $filters['destination'] . '%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;

        return $query->limit($limit)->offset($offset)->get();
    }

    /**
     * Generate call history report
     */
    public function generateCallReport(array $filters = []): array
    {
        $query = CallRecord::query();

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['start_date'])) {
            $query->where('start_time', '>=', Carbon::parse($filters['start_date']));
        }

        if (isset($filters['end_date'])) {
            $query->where('start_time', '<=', Carbon::parse($filters['end_date']));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Get statistics
        $totalCalls = $query->count();
        $totalDuration = $query->sum('duration');
        $totalCost = $query->sum('cost');
        $averageDuration = $totalCalls > 0 ? $totalDuration / $totalCalls : 0;

        // Get status breakdown
        $statusBreakdown = $query->groupBy('status')
            ->selectRaw('status, count(*) as count, sum(duration) as total_duration, sum(cost) as total_cost')
            ->get()
            ->keyBy('status');

        return [
            'summary' => [
                'total_calls' => $totalCalls,
                'total_duration' => $totalDuration,
                'total_cost' => $totalCost,
                'average_duration' => round($averageDuration, 2)
            ],
            'status_breakdown' => $statusBreakdown,
            'filters_applied' => $filters
        ];
    }

    /**
     * Sync CDR data for a specific time period
     */
    public function syncCDRData(Carbon $startDate, Carbon $endDate): array
    {
        Log::info('Starting CDR sync', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        // Retrieve CDR data from FreePBX
        $cdrData = $this->retrieveCDRData($startDate, $endDate);

        // Process and store the records
        $result = $this->processCDRRecords($cdrData);

        Log::info('CDR sync completed', $result);

        return $result;
    }

    /**
     * Get recent call activity
     */
    public function getRecentActivity(int $limit = 10): Collection
    {
        return CallRecord::with('user')
            ->orderBy('start_time', 'desc')
            ->limit($limit)
            ->get();
    }
}