<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\CDRService;

class SyncCDRRecords extends Command
{
    protected $signature = 'cdr:sync {--hours=1 : Number of hours to sync back}';
    protected $description = 'Sync CDR records from FreePBX for billing';

    public function handle()
    {
        $hours = $this->option('hours');
        
        $this->info("Syncing CDR records from the last {$hours} hour(s)...");
        
        try {
            $cdrService = app(CDRService::class);
            $syncedCount = $cdrService->syncCDRFromDatabase();
            
            $this->info("Successfully synced {$syncedCount} CDR records.");
            
            if ($syncedCount > 0) {
                $this->info("Billing calculations completed for synced calls.");
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Failed to sync CDR records: " . $e->getMessage());
            return 1;
        }
    }
}