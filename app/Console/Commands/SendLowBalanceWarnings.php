<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Email\PaymentNotificationService;

class SendLowBalanceWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:low-balance-warnings {--threshold= : Balance threshold for warnings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send low balance warning emails to customers with low account balances';

    protected PaymentNotificationService $notificationService;

    /**
     * Create a new command instance.
     */
    public function __construct(PaymentNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = $this->option('threshold') 
            ? (float) $this->option('threshold') 
            : config('voip.low_balance_threshold', 5.00);
        
        $this->info("Checking for users with balance below {$threshold}...");
        
        $results = $this->notificationService->checkAndSendLowBalanceWarnings($threshold);
        
        $totalUsers = count($results);
        $warningsSent = count(array_filter($results));
        
        $this->info("✅ Low balance check completed!");
        $this->info("Users checked: {$totalUsers}");
        $this->info("Warnings sent: {$warningsSent}");
        
        if ($warningsSent > 0) {
            $this->info("Low balance warning emails have been sent to customers.");
        } else {
            $this->info("No low balance warnings needed at this time.");
        }
        
        return 0;
    }
}
