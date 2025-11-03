<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedCDRService;
use App\Services\FreePBX\CDRService;
use App\Models\CallRecord;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AutomatedCDRProcessing extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cdr:automated-processing 
                            {--batch-size=50 : Number of records to process in each batch}
                            {--max-retries=3 : Maximum number of retry attempts for failed records}
                            {--timeout=300 : Maximum execution time in seconds}
                            {--dry-run : Show what would be processed without taking action}
                            {--force : Force processing even if another instance is running}';

    /**
     * The console command description.
     */
    protected $description = 'Automated CDR processing with error handling, retry logic, and monitoring';

    protected $enhancedCDRService;
    protected $cdrService;
    protected $startTime;
    protected $lockKey = 'cdr_processing_lock';

    public function __construct(
        EnhancedCDRService $enhancedCDRService,
        CDRService $cdrService
    ) {
        parent::__construct();
        $this->enhancedCDRService = $enhancedCDRService;
        $this->cdrService = $cdrService;
        $this->startTime = now();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if processing is already running
        if (!$this->acquireLock()) {
            $this->warn('CDR processing is already running. Use --force to override.');
            return 1;
        }

        try {
            $this->info('Starting automated CDR processing...');
            
            // Set execution timeout
            $timeout = (int) $this->option('timeout');
            set_time_limit($timeout);

            // Process in phases
            $results = [
                'new_cdr_records' => $this->processNewCDRRecords(),
                'unprocessed_records' => $this->processUnprocessedRecords(),
                'retry_failed_records' => $this->retryFailedRecords()
            ];

            // Log processing summary
            $this->logProcessingSummary($results);

            // Update system metrics
            $this->updateSystemMetrics($results);

            $this->info('Automated CDR processing completed successfully.');
            return 0;

        } catch (\Exception $e) {
            $this->error('CDR processing failed: ' . $e->getMessage());
            Log::error('Automated CDR processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Acquire processing lock
     */
    protected function acquireLock(): bool
    {
        if ($this->option('force')) {
            Cache::forget($this->lockKey);
        }

        return Cache::add($this->lockKey, [
            'started_at' => now()->toISOString(),
            'pid' => getmypid(),
            'command' => $this->signature
        ], 3600); // 1 hour lock
    }

    /**
     * Release processing lock
     */
    protected function releaseLock(): void
    {
        Cache::forget($this->lockKey);
    }

    /**
     * Process new CDR records from FreePBX
     */
    protected function processNewCDRRecords(): array
    {
        $this->info('Processing new CDR records from FreePBX...');
        
        try {
            // Get processing window from system settings
            $processingWindow = SystemSetting::get('cdr_processing_window_minutes', 10);
            $batchSize = (int) $this->option('batch-size');
            
            $startTime = now()->subMinutes($processingWindow);
            $endTime = now();
            
            // Fetch CDR records
            $cdrRecords = $this->cdrService->getCDRRecords($startTime, $endTime, $batchSize * 2);
            
            if (empty($cdrRecords)) {
                $this->info('No new CDR records found.');
                return ['processed' => 0, 'failed' => 0, 'total' => 0];
            }

            $this->info('Found ' . count($cdrRecords) . ' new CDR records');

            // Process in batches
            $totalProcessed = 0;
            $totalFailed = 0;
            $batches = array_chunk($cdrRecords, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                $this->info("Processing batch " . ($batchIndex + 1) . " of " . count($batches));
                
                $result = $this->processCDRBatch($batch);
                $totalProcessed += $result['processed'];
                $totalFailed += $result['failed'];

                // Brief pause between batches to prevent system overload
                usleep(100000); // 0.1 seconds
            }

            return [
                'processed' => $totalProcessed,
                'failed' => $totalFailed,
                'total' => count($cdrRecords)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to process new CDR records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'processed' => 0,
                'failed' => 0,
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process unprocessed call records
     */
    protected function processUnprocessedRecords(): array
    {
        $this->info('Processing unprocessed call records...');
        
        try {
            $batchSize = (int) $this->option('batch-size');
            $result = $this->enhancedCDRService->processUnprocessedCDRs($batchSize);
            
            if ($result['success']) {
                $this->info("Processed {$result['processed']} unprocessed records");
                if ($result['failed'] > 0) {
                    $this->warn("Failed to process {$result['failed']} records");
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to process unprocessed records', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'processed' => 0,
                'failed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Retry failed records with exponential backoff
     */
    protected function retryFailedRecords(): array
    {
        $this->info('Retrying failed CDR records...');
        
        try {
            $maxRetries = (int) $this->option('max-retries');
            $batchSize = (int) $this->option('batch-size');
            
            // Get records that failed processing and are eligible for retry
            $failedRecords = CallRecord::where('billing_status', 'failed')
                ->where(function ($query) use ($maxRetries) {
                    $query->whereNull('retry_count')
                          ->orWhere('retry_count', '<', $maxRetries);
                })
                ->where('updated_at', '<', now()->subMinutes(5)) // Wait 5 minutes before retry
                ->limit($batchSize)
                ->get();

            if ($failedRecords->isEmpty()) {
                $this->info('No failed records eligible for retry.');
                return ['processed' => 0, 'failed' => 0, 'total' => 0];
            }

            $this->info('Found ' . $failedRecords->count() . ' failed records to retry');

            $processed = 0;
            $failed = 0;

            foreach ($failedRecords as $callRecord) {
                try {
                    // Increment retry count
                    $retryCount = ($callRecord->retry_count ?? 0) + 1;
                    $callRecord->update(['retry_count' => $retryCount]);

                    // Reset billing status to pending for retry
                    $callRecord->update(['billing_status' => 'pending']);

                    // Enhance and process the record
                    $enhancement = $this->enhancedCDRService->enhanceCallRecord($callRecord, []);
                    
                    if ($this->shouldProcessBilling($callRecord)) {
                        $billingResult = $this->enhancedCDRService->processCallBilling($callRecord, $enhancement);
                        
                        if ($billingResult['success']) {
                            $processed++;
                            $this->line("✓ Retry successful for call {$callRecord->call_id}");
                        } else {
                            $failed++;
                            $this->markRecordAsFailed($callRecord, $billingResult['error'], $retryCount, $maxRetries);
                        }
                    } else {
                        $callRecord->update([
                            'cost' => 0,
                            'billing_status' => 'no_billing_required'
                        ]);
                        $processed++;
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $this->markRecordAsFailed($callRecord, $e->getMessage(), $retryCount, $maxRetries);
                    Log::error("Retry failed for call record {$callRecord->id}", [
                        'error' => $e->getMessage(),
                        'retry_count' => $retryCount
                    ]);
                }
            }

            return [
                'processed' => $processed,
                'failed' => $failed,
                'total' => $failedRecords->count()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to retry failed records', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'processed' => 0,
                'failed' => 0,
                'total' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process a batch of CDR records
     */
    protected function processCDRBatch(array $cdrBatch): array
    {
        try {
            $result = $this->enhancedCDRService->processEnhancedCDR($cdrBatch);
            
            if ($result['success']) {
                return [
                    'processed' => $result['processed'],
                    'failed' => $result['failed']
                ];
            } else {
                Log::error('CDR batch processing failed', [
                    'error' => $result['error'],
                    'batch_size' => count($cdrBatch)
                ]);
                
                return [
                    'processed' => 0,
                    'failed' => count($cdrBatch)
                ];
            }

        } catch (\Exception $e) {
            Log::error('CDR batch processing exception', [
                'error' => $e->getMessage(),
                'batch_size' => count($cdrBatch)
            ]);
            
            return [
                'processed' => 0,
                'failed' => count($cdrBatch)
            ];
        }
    }

    /**
     * Check if record should be processed for billing
     */
    protected function shouldProcessBilling(CallRecord $callRecord): bool
    {
        return in_array($callRecord->status, ['completed', 'answered']) && 
               ($callRecord->actual_duration ?? 0) > 0;
    }

    /**
     * Mark record as permanently failed if max retries exceeded
     */
    protected function markRecordAsFailed(CallRecord $callRecord, string $error, int $retryCount, int $maxRetries): void
    {
        $status = $retryCount >= $maxRetries ? 'permanently_failed' : 'failed';
        
        $callRecord->update([
            'billing_status' => $status,
            'billing_details' => json_encode(array_merge(
                json_decode($callRecord->billing_details ?? '{}', true),
                [
                    'last_error' => $error,
                    'retry_count' => $retryCount,
                    'last_retry_at' => now()->toISOString()
                ]
            ))
        ]);

        if ($status === 'permanently_failed') {
            $this->warn("✗ Call {$callRecord->call_id} marked as permanently failed after {$retryCount} retries");
        }
    }

    /**
     * Log processing summary
     */
    protected function logProcessingSummary(array $results): void
    {
        $duration = now()->diffInSeconds($this->startTime);
        
        $summary = [
            'execution_time_seconds' => $duration,
            'new_cdr_records' => $results['new_cdr_records'],
            'unprocessed_records' => $results['unprocessed_records'],
            'retry_results' => $results['retry_failed_records'],
            'total_processed' => ($results['new_cdr_records']['processed'] ?? 0) + 
                               ($results['unprocessed_records']['processed'] ?? 0) + 
                               ($results['retry_failed_records']['processed'] ?? 0),
            'total_failed' => ($results['new_cdr_records']['failed'] ?? 0) + 
                             ($results['unprocessed_records']['failed'] ?? 0) + 
                             ($results['retry_failed_records']['failed'] ?? 0)
        ];

        Log::info('Automated CDR processing summary', $summary);

        // Display summary to console
        $this->info('Processing Summary:');
        $this->line('- Execution time: ' . $duration . ' seconds');
        $this->line('- Total processed: ' . $summary['total_processed']);
        $this->line('- Total failed: ' . $summary['total_failed']);
        
        if (isset($results['new_cdr_records']['total'])) {
            $this->line('- New CDR records found: ' . $results['new_cdr_records']['total']);
        }
    }

    /**
     * Update system metrics
     */
    protected function updateSystemMetrics(array $results): void
    {
        try {
            $metrics = [
                'last_cdr_processing_at' => now()->toISOString(),
                'last_cdr_processing_duration' => now()->diffInSeconds($this->startTime),
                'last_cdr_processing_results' => json_encode($results)
            ];

            foreach ($metrics as $key => $value) {
                SystemSetting::set($key, $value);
            }

            // Update processing statistics
            $stats = $this->enhancedCDRService->getCDRProcessingStats();
            SystemSetting::set('cdr_processing_stats', json_encode($stats));

        } catch (\Exception $e) {
            Log::warning('Failed to update system metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }
}