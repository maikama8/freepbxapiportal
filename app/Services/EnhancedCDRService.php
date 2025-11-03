<?php

namespace App\Services;

use App\Models\CallRecord;
use App\Models\CountryRate;
use App\Models\CallRate;
use App\Models\User;
use App\Services\AdvancedBillingService;
use App\Services\FreePBX\CDRService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EnhancedCDRService
{
    protected $advancedBillingService;
    protected $cdrService;

    public function __construct(
        AdvancedBillingService $advancedBillingService,
        CDRService $cdrService
    ) {
        $this->advancedBillingService = $advancedBillingService;
        $this->cdrService = $cdrService;
    }

    /**
     * Process CDR records with ASTPP-style billing logic
     */
    public function processEnhancedCDR(array $cdrData): array
    {
        $processed = 0;
        $failed = 0;
        $results = [];

        DB::beginTransaction();
        
        try {
            foreach ($cdrData as $cdr) {
                $result = $this->processSingleCDR($cdr);
                $results[] = $result;
                
                if ($result['success']) {
                    $processed++;
                } else {
                    $failed++;
                }
            }
            
            DB::commit();
            
            Log::info("Enhanced CDR processing completed: {$processed} processed, {$failed} failed");
            
            return [
                'success' => true,
                'processed' => $processed,
                'failed' => $failed,
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Enhanced CDR processing failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => 0,
                'failed' => count($cdrData)
            ];
        }
    }

    /**
     * Process a single CDR record
     */
    protected function processSingleCDR(array $cdr): array
    {
        try {
            // Extract CDR data
            $callId = $cdr['uniqueid'] ?? $cdr['call_id'] ?? null;
            $src = $cdr['src'] ?? $cdr['caller_id'] ?? null;
            $dst = $cdr['dst'] ?? $cdr['destination'] ?? null;
            $startTime = $this->parseCDRDateTime($cdr['start'] ?? $cdr['start_time'] ?? null);
            $endTime = $this->parseCDRDateTime($cdr['end'] ?? $cdr['end_time'] ?? null);
            $duration = (int) ($cdr['billsec'] ?? $cdr['duration'] ?? 0);
            $disposition = $cdr['disposition'] ?? $cdr['status'] ?? 'UNKNOWN';

            if (!$callId || !$dst) {
                return [
                    'success' => false,
                    'error' => 'Missing required CDR fields (call_id or destination)',
                    'cdr' => $cdr
                ];
            }

            // Find or create call record
            $callRecord = $this->findOrCreateCallRecord($callId, $src, $dst, $startTime, $endTime, $duration, $disposition);
            
            if (!$callRecord) {
                return [
                    'success' => false,
                    'error' => 'Failed to create call record',
                    'cdr' => $cdr
                ];
            }

            // Enhance CDR with country detection and rate information
            $enhancement = $this->enhanceCallRecord($callRecord, $cdr);
            
            // Process billing if call was answered and has duration
            if ($this->shouldProcessBilling($callRecord, $disposition, $duration)) {
                $billingResult = $this->processCallBilling($callRecord, $enhancement);
                
                if (!$billingResult['success']) {
                    Log::warning("Billing failed for call {$callId}: " . $billingResult['error']);
                }
            } else {
                // Mark as no billing required
                $callRecord->update([
                    'cost' => 0,
                    'billing_status' => 'no_billing_required',
                    'billing_details' => json_encode([
                        'reason' => $this->getNoBillingReason($disposition, $duration),
                        'disposition' => $disposition,
                        'duration' => $duration
                    ])
                ]);
            }

            return [
                'success' => true,
                'call_record_id' => $callRecord->id,
                'call_id' => $callId,
                'billing_status' => $callRecord->billing_status,
                'cost' => $callRecord->cost,
                'enhancement' => $enhancement
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to process CDR: " . $e->getMessage(), ['cdr' => $cdr]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'cdr' => $cdr
            ];
        }
    }

    /**
     * Find or create call record from CDR data
     */
    protected function findOrCreateCallRecord(string $callId, ?string $src, string $dst, ?Carbon $startTime, ?Carbon $endTime, int $duration, string $disposition): ?CallRecord
    {
        // Try to find existing call record
        $callRecord = CallRecord::where('call_id', $callId)->first();
        
        if ($callRecord) {
            // Update existing record with CDR data
            $callRecord->update([
                'end_time' => $endTime,
                'duration' => $duration,
                'status' => $this->mapDispositionToStatus($disposition),
                'actual_duration' => $duration
            ]);
            
            return $callRecord;
        }

        // Find user by caller ID or extension
        $user = $this->findUserByCallerId($src);
        
        if (!$user) {
            Log::warning("No user found for caller ID: {$src}");
            // Create a system user or skip this CDR
            return null;
        }

        // Create new call record
        return CallRecord::create([
            'user_id' => $user->id,
            'call_id' => $callId,
            'caller_id' => $src,
            'destination' => $dst,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $duration,
            'actual_duration' => $duration,
            'status' => $this->mapDispositionToStatus($disposition),
            'billing_status' => 'pending'
        ]);
    }

    /**
     * Enhance call record with country detection and rate information
     */
    protected function enhanceCallRecord(CallRecord $callRecord, array $cdr): array
    {
        $enhancement = [
            'country_detected' => false,
            'country_info' => null,
            'rate_info' => null,
            'call_type' => 'unknown'
        ];

        try {
            // Detect country from destination
            $countryRate = CountryRate::getByPhoneNumber($callRecord->destination);
            
            if ($countryRate) {
                $enhancement['country_detected'] = true;
                $enhancement['country_info'] = [
                    'country_code' => $countryRate->country_code,
                    'country_name' => $countryRate->country_name,
                    'country_prefix' => $countryRate->country_prefix
                ];
                
                // Determine call type
                $enhancement['call_type'] = $this->determineCallType($callRecord->destination, $countryRate);
            }

            // Get rate information
            try {
                $billingInfo = $this->advancedBillingService->calculateAdvancedCallCost(
                    $callRecord->destination,
                    $callRecord->actual_duration ?? 0
                );
                
                $enhancement['rate_info'] = [
                    'rate_per_minute' => $billingInfo['rate_per_minute'],
                    'billing_config' => $billingInfo['billing_config'],
                    'rate_source' => $billingInfo['rate_source'],
                    'destination_name' => $billingInfo['destination_name'] ?? 'Unknown'
                ];
            } catch (\Exception $e) {
                Log::warning("Failed to get rate info for {$callRecord->destination}: " . $e->getMessage());
            }

            // Update call record with enhancement data
            $callRecord->update([
                'billing_details' => json_encode(array_merge(
                    json_decode($callRecord->billing_details ?? '{}', true),
                    ['enhancement' => $enhancement]
                ))
            ]);

            return $enhancement;
            
        } catch (\Exception $e) {
            Log::error("Failed to enhance call record {$callRecord->id}: " . $e->getMessage());
            return $enhancement;
        }
    }

    /**
     * Process billing for the call record
     */
    protected function processCallBilling(CallRecord $callRecord, array $enhancement): array
    {
        try {
            $success = $this->advancedBillingService->processAdvancedCallBilling($callRecord);
            
            if ($success) {
                return [
                    'success' => true,
                    'cost' => $callRecord->fresh()->cost,
                    'billing_status' => $callRecord->fresh()->billing_status
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Advanced billing processing failed'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine if billing should be processed for this call
     */
    protected function shouldProcessBilling(CallRecord $callRecord, string $disposition, int $duration): bool
    {
        // Only bill answered calls with duration > 0
        $billableDispositions = ['ANSWERED', 'answered', 'completed'];
        
        return in_array($disposition, $billableDispositions) && $duration > 0;
    }

    /**
     * Get reason for no billing
     */
    protected function getNoBillingReason(string $disposition, int $duration): string
    {
        if ($duration <= 0) {
            return 'Zero duration call';
        }
        
        switch (strtoupper($disposition)) {
            case 'NO ANSWER':
            case 'NOANSWER':
                return 'Call not answered';
            case 'BUSY':
                return 'Destination busy';
            case 'FAILED':
                return 'Call failed';
            case 'CANCELLED':
                return 'Call cancelled';
            default:
                return 'Non-billable disposition: ' . $disposition;
        }
    }

    /**
     * Map CDR disposition to call status
     */
    protected function mapDispositionToStatus(string $disposition): string
    {
        $mapping = [
            'ANSWERED' => 'completed',
            'answered' => 'completed',
            'NO ANSWER' => 'no_answer',
            'NOANSWER' => 'no_answer',
            'BUSY' => 'busy',
            'FAILED' => 'failed',
            'CANCELLED' => 'cancelled',
            'completed' => 'completed'
        ];
        
        return $mapping[$disposition] ?? 'unknown';
    }

    /**
     * Find user by caller ID
     */
    protected function findUserByCallerId(?string $callerId): ?User
    {
        if (!$callerId) {
            return null;
        }

        // Try to find by extension first
        $user = User::where('extension', $callerId)->first();
        
        if ($user) {
            return $user;
        }

        // Try to find by phone number
        $user = User::where('phone', $callerId)->first();
        
        if ($user) {
            return $user;
        }

        // Try to find by SIP account
        $sipAccount = \App\Models\SipAccount::where('username', $callerId)->first();
        
        if ($sipAccount) {
            return $sipAccount->user;
        }

        return null;
    }

    /**
     * Determine call type based on destination and country
     */
    protected function determineCallType(string $destination, CountryRate $countryRate): string
    {
        // Remove non-numeric characters
        $cleanDestination = preg_replace('/[^0-9]/', '', $destination);
        
        // Check if it's a local call (same country as system)
        $systemCountryPrefix = config('voip.platform.country_prefix', '1');
        
        if (str_starts_with($cleanDestination, $systemCountryPrefix)) {
            return 'domestic';
        }
        
        // Check if it's a mobile number (this would need country-specific logic)
        if ($this->isMobileNumber($cleanDestination, $countryRate)) {
            return 'international_mobile';
        }
        
        return 'international';
    }

    /**
     * Check if number is mobile (simplified logic)
     */
    protected function isMobileNumber(string $number, CountryRate $countryRate): bool
    {
        // This would need country-specific mobile prefix logic
        // For now, return false as a placeholder
        return false;
    }

    /**
     * Parse CDR datetime string
     */
    protected function parseCDRDateTime(?string $datetime): ?Carbon
    {
        if (!$datetime) {
            return null;
        }

        try {
            return Carbon::parse($datetime);
        } catch (\Exception $e) {
            Log::warning("Failed to parse CDR datetime: {$datetime}");
            return null;
        }
    }

    /**
     * Process unprocessed CDR records
     */
    public function processUnprocessedCDRs(int $limit = 100): array
    {
        try {
            // Get unprocessed call records
            $unprocessedCalls = CallRecord::where('billing_status', 'pending')
                ->whereIn('status', ['completed', 'answered'])
                ->whereNotNull('end_time')
                ->limit($limit)
                ->get();

            $processed = 0;
            $failed = 0;

            foreach ($unprocessedCalls as $callRecord) {
                try {
                    $enhancement = $this->enhanceCallRecord($callRecord, []);
                    
                    if ($this->shouldProcessBilling($callRecord, $callRecord->status, $callRecord->actual_duration ?? 0)) {
                        $billingResult = $this->processCallBilling($callRecord, $enhancement);
                        
                        if ($billingResult['success']) {
                            $processed++;
                        } else {
                            $failed++;
                        }
                    } else {
                        $callRecord->update([
                            'cost' => 0,
                            'billing_status' => 'no_billing_required'
                        ]);
                        $processed++;
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to process call record {$callRecord->id}: " . $e->getMessage());
                    $failed++;
                }
            }

            return [
                'success' => true,
                'processed' => $processed,
                'failed' => $failed,
                'total_found' => $unprocessedCalls->count()
            ];
            
        } catch (\Exception $e) {
            Log::error("Failed to process unprocessed CDRs: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => 0,
                'failed' => 0
            ];
        }
    }

    /**
     * Get CDR processing statistics
     */
    public function getCDRProcessingStats(): array
    {
        $today = now()->startOfDay();
        
        return [
            'total_calls_today' => CallRecord::whereDate('created_at', $today)->count(),
            'processed_today' => CallRecord::whereDate('created_at', $today)
                ->whereNotIn('billing_status', ['pending'])->count(),
            'pending_processing' => CallRecord::where('billing_status', 'pending')->count(),
            'billing_status_breakdown' => CallRecord::selectRaw('billing_status, COUNT(*) as count')
                ->groupBy('billing_status')
                ->pluck('count', 'billing_status')
                ->toArray(),
            'call_type_breakdown' => $this->getCallTypeBreakdown(),
            'revenue_today' => CallRecord::whereDate('created_at', $today)->sum('cost'),
            'average_call_duration' => CallRecord::whereDate('created_at', $today)
                ->whereNotNull('actual_duration')
                ->avg('actual_duration')
        ];
    }

    /**
     * Get call type breakdown from billing details
     */
    protected function getCallTypeBreakdown(): array
    {
        $calls = CallRecord::whereNotNull('billing_details')
            ->whereDate('created_at', today())
            ->get();

        $breakdown = [
            'domestic' => 0,
            'international' => 0,
            'international_mobile' => 0,
            'unknown' => 0
        ];

        foreach ($calls as $call) {
            $details = json_decode($call->billing_details, true);
            $callType = $details['enhancement']['call_type'] ?? 'unknown';
            
            if (isset($breakdown[$callType])) {
                $breakdown[$callType]++;
            } else {
                $breakdown['unknown']++;
            }
        }

        return $breakdown;
    }
}