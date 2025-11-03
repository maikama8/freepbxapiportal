<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedCDRService;
use App\Services\FreePBX\CDRService;
use App\Models\CallRecord;
use Illuminate\Support\Facades\Log;

class ProcessEnhancedCDR extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cdr:process-enhanced 
                            {--limit=100 : Maximum number of records to process}
                            {--hours=1 : Process CDRs from the last N hours}
                            {--unprocessed : Process only unprocessed call records}
                            {--dry-run : Show what would be processed without taking action}';

    /**
     * The console command description.
     */
    protected $description = 'Process CDR records with enhanced ASTPP-style billing logic';

    protected $enhancedCDRService;
    protected $cdrService;

    public function __construct(
        EnhancedCDRService $enhancedCDRService,
        CDRService $cdrService
    ) {
        parent::__construct();
        $this->enhancedCDRService = $enhancedCDRService;
        $this->cdrService = $cdrService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting enhanced CDR processing...');

        $limit = (int) $this->option('limit');
        $hours = (int) $this->option('hours');
        $unprocessedOnly = $this->option('unprocessed');
        $dryRun = $this->option('dry-run');

        if ($unprocessedOnly) {
            return $this->processUnprocessedRecords($limit, $dryRun);
        } else {
            return $this->processNewCDRRecords($hours, $limit, $dryRun);
        }
    }

    /**
     * Process unprocessed call records
     */
    protected function processUnprocessedRecords(int $limit, bool $dryRun): int
    {
        $this->info("Processing unprocessed call records (limit: {$limit})...");

        // Get unprocessed records
        $unprocessedCalls = CallRecord::where('billing_status', 'pending')
            ->whereIn('status', ['completed', 'answered'])
            ->whereNotNull('end_time')
            ->limit($limit)
            ->get();

        if ($unprocessedCalls->isEmpty()) {
            $this->info('No unprocessed call records found.');
            return 0;
        }

        $this->info('Found ' . $unprocessedCalls->count() . ' unprocessed call records');

        if ($dryRun) {
            $this->displayUnprocessedCallsTable($unprocessedCalls);
            $this->info('Dry run completed. No records were processed.');
            return 0;
        }

        // Process the records
        $result = $this->enhancedCDRService->processUnprocessedCDRs($limit);

        if ($result['success']) {
            $this->info("Successfully processed {$result['processed']} records");
            if ($result['failed'] > 0) {
                $this->warn("Failed to process {$result['failed']} records");
            }
        } else {
            $this->error("CDR processing failed: " . $result['error']);
            return 1;
        }

        // Display statistics
        $this->displayProcessingStatistics();

        return 0;
    }

    /**
     * Process new CDR records from FreePBX
     */
    protected function processNewCDRRecords(int $hours, int $limit, bool $dryRun): int
    {
        $this->info("Fetching CDR records from the last {$hours} hour(s)...");

        try {
            // Fetch CDR records from FreePBX
            $startTime = now()->subHours($hours);
            $endTime = now();
            
            $cdrRecords = $this->cdrService->getCDRRecords($startTime, $endTime, $limit);
            
            if (empty($cdrRecords)) {
                $this->info('No new CDR records found.');
                return 0;
            }

            $this->info('Found ' . count($cdrRecords) . ' CDR records to process');

            if ($dryRun) {
                $this->displayCDRTable($cdrRecords);
                $this->info('Dry run completed. No records were processed.');
                return 0;
            }

            // Process the CDR records
            $result = $this->enhancedCDRService->processEnhancedCDR($cdrRecords);

            if ($result['success']) {
                $this->info("Successfully processed {$result['processed']} CDR records");
                if ($result['failed'] > 0) {
                    $this->warn("Failed to process {$result['failed']} CDR records");
                }
                
                // Display detailed results if there are failures
                if ($result['failed'] > 0) {
                    $this->displayFailedRecords($result['results']);
                }
            } else {
                $this->error("CDR processing failed: " . $result['error']);
                return 1;
            }

            // Display statistics
            $this->displayProcessingStatistics();

            return 0;
            
        } catch (\Exception $e) {
            $this->error("Failed to fetch or process CDR records: " . $e->getMessage());
            Log::error("Enhanced CDR processing command failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display unprocessed calls table
     */
    protected function displayUnprocessedCallsTable($calls): void
    {
        $headers = ['ID', 'Call ID', 'User', 'Destination', 'Duration', 'Status', 'Created'];
        $rows = [];

        foreach ($calls as $call) {
            $rows[] = [
                $call->id,
                $call->call_id,
                $call->user->name ?? 'Unknown',
                $call->destination,
                gmdate('H:i:s', $call->actual_duration ?? 0),
                $call->status,
                $call->created_at->format('Y-m-d H:i:s')
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Display CDR records table
     */
    protected function displayCDRTable(array $cdrRecords): void
    {
        $headers = ['Call ID', 'Source', 'Destination', 'Duration', 'Disposition', 'Start Time'];
        $rows = [];

        foreach ($cdrRecords as $cdr) {
            $rows[] = [
                $cdr['uniqueid'] ?? $cdr['call_id'] ?? 'N/A',
                $cdr['src'] ?? $cdr['caller_id'] ?? 'N/A',
                $cdr['dst'] ?? $cdr['destination'] ?? 'N/A',
                gmdate('H:i:s', (int) ($cdr['billsec'] ?? $cdr['duration'] ?? 0)),
                $cdr['disposition'] ?? $cdr['status'] ?? 'N/A',
                $cdr['start'] ?? $cdr['start_time'] ?? 'N/A'
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Display failed records
     */
    protected function displayFailedRecords(array $results): void
    {
        $failedResults = array_filter($results, function ($result) {
            return !$result['success'];
        });

        if (empty($failedResults)) {
            return;
        }

        $this->warn('Failed CDR Records:');
        
        foreach ($failedResults as $result) {
            $this->line("- Error: {$result['error']}");
            if (isset($result['cdr']['uniqueid'])) {
                $this->line("  Call ID: {$result['cdr']['uniqueid']}");
            }
            if (isset($result['cdr']['dst'])) {
                $this->line("  Destination: {$result['cdr']['dst']}");
            }
        }
    }

    /**
     * Display processing statistics
     */
    protected function displayProcessingStatistics(): void
    {
        $stats = $this->enhancedCDRService->getCDRProcessingStats();

        $this->info('CDR Processing Statistics:');
        $this->line('- Total calls today: ' . $stats['total_calls_today']);
        $this->line('- Processed today: ' . $stats['processed_today']);
        $this->line('- Pending processing: ' . $stats['pending_processing']);
        $this->line('- Revenue today: $' . number_format($stats['revenue_today'], 2));
        $this->line('- Average call duration: ' . gmdate('H:i:s', (int) $stats['average_call_duration']));

        $this->info('Billing Status Breakdown:');
        foreach ($stats['billing_status_breakdown'] as $status => $count) {
            $this->line("- {$status}: {$count}");
        }

        $this->info('Call Type Breakdown:');
        foreach ($stats['call_type_breakdown'] as $type => $count) {
            $this->line("- {$type}: {$count}");
        }
    }
}