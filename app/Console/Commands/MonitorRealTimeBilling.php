<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RealTimeBillingService;
use App\Services\CallTerminationService;
use App\Models\CallRecord;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;

class MonitorRealTimeBilling extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:monitor-realtime 
                            {--terminate : Terminate calls with insufficient balance}
                            {--max-duration=480 : Maximum call duration in minutes}
                            {--dry-run : Show what would be done without taking action}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor real-time billing and optionally terminate calls with insufficient balance';

    protected $realTimeBillingService;
    protected $callTerminationService;

    public function __construct(
        RealTimeBillingService $realTimeBillingService,
        CallTerminationService $callTerminationService
    ) {
        parent::__construct();
        $this->realTimeBillingService = $realTimeBillingService;
        $this->callTerminationService = $callTerminationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting real-time billing monitoring...');

        // Check if real-time billing is enabled
        if (!SystemSetting::get('billing.enable_real_time', true)) {
            $this->warn('Real-time billing is disabled. Exiting.');
            return 0;
        }

        // Get active calls with billing
        $activeCalls = $this->realTimeBillingService->getActiveCallsWithBilling();
        
        if (empty($activeCalls)) {
            $this->info('No active calls with real-time billing found.');
            return 0;
        }

        $this->info('Found ' . count($activeCalls) . ' active calls with real-time billing');

        // Display active calls table
        $this->displayActiveCallsTable($activeCalls);

        // Process periodic billing for all active calls
        $this->processPeriodicBilling($activeCalls);

        // Check for calls with insufficient balance
        $callsAtRisk = $this->identifyCallsAtRisk($activeCalls);
        
        if (!empty($callsAtRisk)) {
            $this->warn('Found ' . count($callsAtRisk) . ' calls at risk due to insufficient balance');
            $this->displayCallsAtRiskTable($callsAtRisk);

            if ($this->option('terminate')) {
                $this->terminateCallsWithInsufficientBalance($callsAtRisk);
            }
        }

        // Check for calls exceeding maximum duration
        if ($this->option('terminate')) {
            $maxDuration = (int) $this->option('max-duration');
            $this->terminateExcessiveDurationCalls($maxDuration);
        }

        // Display final statistics
        $this->displayBillingStatistics();

        $this->info('Real-time billing monitoring completed.');
        return 0;
    }

    /**
     * Display active calls table
     */
    protected function displayActiveCallsTable(array $activeCalls): void
    {
        $headers = ['Call ID', 'User', 'Destination', 'Duration', 'Current Cost', 'User Balance', 'Status'];
        $rows = [];

        foreach ($activeCalls as $callData) {
            $call = $callData['call_record'];
            $duration = gmdate('H:i:s', $callData['current_duration']);
            $cost = '$' . number_format($callData['current_cost'], 4);
            $balance = '$' . number_format($callData['user_balance'], 2);
            
            $status = $callData['user_balance'] >= $callData['current_cost'] ? 'OK' : 'AT RISK';
            
            $rows[] = [
                $call->call_id,
                $call->user->name,
                $call->destination,
                $duration,
                $cost,
                $balance,
                $status
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Display calls at risk table
     */
    protected function displayCallsAtRiskTable(array $callsAtRisk): void
    {
        $this->warn('Calls at risk of termination:');
        
        $headers = ['Call ID', 'User', 'Destination', 'Duration', 'Cost', 'Balance', 'Deficit'];
        $rows = [];

        foreach ($callsAtRisk as $callData) {
            $call = $callData['call_record'];
            $duration = gmdate('H:i:s', $callData['current_duration']);
            $cost = '$' . number_format($callData['current_cost'], 4);
            $balance = '$' . number_format($callData['user_balance'], 2);
            $deficit = '$' . number_format($callData['current_cost'] - $callData['user_balance'], 4);
            
            $rows[] = [
                $call->call_id,
                $call->user->name,
                $call->destination,
                $duration,
                $cost,
                $balance,
                $deficit
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Process periodic billing for active calls
     */
    protected function processPeriodicBilling(array $activeCalls): void
    {
        $this->info('Processing periodic billing for active calls...');
        
        $processed = 0;
        $failed = 0;

        foreach ($activeCalls as $callData) {
            $call = $callData['call_record'];
            
            if ($this->option('dry-run')) {
                $this->line("Would process billing for call {$call->call_id}");
                $processed++;
            } else {
                if ($this->realTimeBillingService->processPeriodicBilling($call)) {
                    $processed++;
                } else {
                    $failed++;
                }
            }
        }

        $this->info("Processed billing for {$processed} calls" . ($failed > 0 ? ", {$failed} failed" : ''));
    }

    /**
     * Identify calls at risk of termination
     */
    protected function identifyCallsAtRisk(array $activeCalls): array
    {
        return array_filter($activeCalls, function ($callData) {
            return $callData['user_balance'] < $callData['current_cost'];
        });
    }

    /**
     * Terminate calls with insufficient balance
     */
    protected function terminateCallsWithInsufficientBalance(array $callsAtRisk): void
    {
        if (!SystemSetting::get('billing.auto_terminate_on_zero_balance', true)) {
            $this->warn('Auto-termination is disabled. Skipping termination.');
            return;
        }

        $this->warn('Terminating calls with insufficient balance...');
        
        $terminated = 0;
        $failed = 0;

        foreach ($callsAtRisk as $callData) {
            $call = $callData['call_record'];
            
            if ($this->option('dry-run')) {
                $this->line("Would terminate call {$call->call_id} (insufficient balance)");
                $terminated++;
            } else {
                if ($this->callTerminationService->terminateForInsufficientBalance($call)) {
                    $this->line("Terminated call {$call->call_id}");
                    $terminated++;
                } else {
                    $this->error("Failed to terminate call {$call->call_id}");
                    $failed++;
                }
            }
        }

        $this->info("Terminated {$terminated} calls" . ($failed > 0 ? ", {$failed} failed" : ''));
    }

    /**
     * Terminate calls exceeding maximum duration
     */
    protected function terminateExcessiveDurationCalls(int $maxDurationMinutes): void
    {
        $this->info("Checking for calls exceeding {$maxDurationMinutes} minutes...");
        
        if ($this->option('dry-run')) {
            $longCalls = CallRecord::whereIn('status', ['answered', 'in_progress'])
                ->whereNull('end_time')
                ->where('start_time', '<=', now()->subMinutes($maxDurationMinutes))
                ->count();
            
            $this->line("Would terminate {$longCalls} calls exceeding maximum duration");
        } else {
            $terminated = $this->callTerminationService->terminateExcessiveDurationCalls($maxDurationMinutes);
            
            if ($terminated > 0) {
                $this->info("Terminated {$terminated} calls exceeding maximum duration");
            } else {
                $this->info('No calls exceeded maximum duration');
            }
        }
    }

    /**
     * Display billing statistics
     */
    protected function displayBillingStatistics(): void
    {
        $stats = $this->realTimeBillingService->getRealTimeBillingStats();
        $terminationStats = $this->callTerminationService->getTerminationStats();

        $this->info('Real-time Billing Statistics:');
        $this->line('- Active calls with billing: ' . $stats['active_calls_count']);
        $this->line('- Total active cost: $' . number_format($stats['total_active_cost'], 2));
        $this->line('- Calls at risk: ' . count($stats['calls_at_risk']));
        $this->line('- Real-time billing enabled: ' . ($stats['real_time_enabled'] ? 'Yes' : 'No'));
        $this->line('- Auto-termination enabled: ' . ($stats['auto_termination_enabled'] ? 'Yes' : 'No'));
        $this->line('- Grace period: ' . $stats['grace_period'] . ' seconds');

        $this->info('Termination Statistics (Today):');
        $this->line('- Calls terminated: ' . $terminationStats['terminated_today']);
        $this->line('- Terminated for insufficient balance: ' . $terminationStats['terminated_insufficient_balance']);
        $this->line('- Currently active calls: ' . $terminationStats['active_calls']);
        $this->line('- Calls at risk: ' . $terminationStats['calls_at_risk']);
    }
}