<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DidNumber;
use App\Models\User;
use App\Models\BalanceTransaction;
use App\Models\SystemSetting;
use App\Services\BalanceService;
use App\Services\Email\EmailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonthlyDidBilling extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:monthly-did-charges 
                            {--month= : Process specific month (YYYY-MM format)}
                            {--batch-size=50 : Number of DIDs to process in each batch}
                            {--dry-run : Show what would be charged without taking action}
                            {--force : Force processing even if already processed this month}
                            {--suspend-insufficient : Suspend DIDs for users with insufficient balance}';

    /**
     * The console command description.
     */
    protected $description = 'Process monthly DID charges and manage DID suspensions for insufficient balance';

    protected $balanceService;
    protected $emailService;
    protected $startTime;
    protected $lockKey = 'monthly_did_billing_lock';

    public function __construct(
        BalanceService $balanceService,
        EmailService $emailService
    ) {
        parent::__construct();
        $this->balanceService = $balanceService;
        $this->emailService = $emailService;
        $this->startTime = now();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Acquire processing lock
        if (!$this->acquireLock()) {
            $this->warn('Monthly DID billing is already running. Use --force to override.');
            return 1;
        }

        try {
            $this->info('Starting monthly DID billing process...');
            
            // Determine processing month
            $processingMonth = $this->getProcessingMonth();
            $this->info('Processing month: ' . $processingMonth->format('Y-m'));

            // Check if already processed
            if (!$this->shouldProcessMonth($processingMonth)) {
                $this->warn('Monthly DID billing already processed for ' . $processingMonth->format('Y-m'));
                return 0;
            }

            $dryRun = $this->option('dry-run');
            $suspendInsufficient = $this->option('suspend-insufficient');

            // Process monthly charges
            $results = $this->processMonthlyCharges($processingMonth, $dryRun, $suspendInsufficient);

            // Log processing summary
            $this->logBillingSummary($results, $processingMonth);

            // Update system metrics
            $this->updateBillingMetrics($results, $processingMonth);

            // Mark month as processed
            if (!$dryRun) {
                $this->markMonthAsProcessed($processingMonth);
            }

            $this->info('Monthly DID billing completed successfully.');
            return 0;

        } catch (\Exception $e) {
            $this->error('Monthly DID billing failed: ' . $e->getMessage());
            Log::error('Monthly DID billing failed', [
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
        ], 7200); // 2 hour lock
    }

    /**
     * Release processing lock
     */
    protected function releaseLock(): void
    {
        Cache::forget($this->lockKey);
    }

    /**
     * Get processing month
     */
    protected function getProcessingMonth(): Carbon
    {
        $monthOption = $this->option('month');
        
        if ($monthOption) {
            try {
                return Carbon::createFromFormat('Y-m', $monthOption)->startOfMonth();
            } catch (\Exception $e) {
                $this->error('Invalid month format. Use YYYY-MM format.');
                exit(1);
            }
        }

        // Default to current month
        return now()->startOfMonth();
    }

    /**
     * Check if month should be processed
     */
    protected function shouldProcessMonth(Carbon $month): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $lastProcessedMonth = SystemSetting::get('last_did_billing_month');
        
        if (!$lastProcessedMonth) {
            return true;
        }

        $lastProcessed = Carbon::parse($lastProcessedMonth);
        
        return $month->gt($lastProcessed);
    }

    /**
     * Process monthly charges for all DIDs
     */
    protected function processMonthlyCharges(Carbon $month, bool $dryRun, bool $suspendInsufficient): array
    {
        $this->info('Processing monthly DID charges...');
        
        try {
            $batchSize = (int) $this->option('batch-size');
            
            // Get all assigned DIDs that should be charged
            $assignedDids = DidNumber::with(['user', 'countryRate'])
                ->where('status', 'assigned')
                ->whereNotNull('user_id')
                ->where('assigned_at', '<=', $month->endOfMonth())
                ->get();

            if ($assignedDids->isEmpty()) {
                $this->info('No assigned DIDs found for billing.');
                return [
                    'processed' => 0,
                    'charged' => 0,
                    'suspended' => 0,
                    'failed' => 0,
                    'total_revenue' => 0,
                    'notifications_sent' => 0
                ];
            }

            $this->info('Found ' . $assignedDids->count() . ' assigned DIDs to process');

            if ($dryRun) {
                $this->displayBillingPreview($assignedDids, $month);
                return [
                    'processed' => 0,
                    'charged' => 0,
                    'suspended' => 0,
                    'failed' => 0,
                    'total_revenue' => 0,
                    'notifications_sent' => 0,
                    'dry_run' => true,
                    'total_dids' => $assignedDids->count()
                ];
            }

            // Process in batches
            return $this->processDidBatches($assignedDids, $month, $batchSize, $suspendInsufficient);

        } catch (\Exception $e) {
            Log::error('Monthly DID charges processing failed', [
                'error' => $e->getMessage(),
                'month' => $month->format('Y-m')
            ]);
            
            return [
                'processed' => 0,
                'charged' => 0,
                'suspended' => 0,
                'failed' => 0,
                'total_revenue' => 0,
                'notifications_sent' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process DIDs in batches
     */
    protected function processDidBatches($assignedDids, Carbon $month, int $batchSize, bool $suspendInsufficient): array
    {
        $processed = 0;
        $charged = 0;
        $suspended = 0;
        $failed = 0;
        $totalRevenue = 0;
        $notificationsSent = 0;

        $batches = $assignedDids->chunk($batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $this->info("Processing batch " . ($batchIndex + 1) . " of " . $batches->count());
            
            DB::beginTransaction();
            
            try {
                foreach ($batch as $didNumber) {
                    $result = $this->processDidBilling($didNumber, $month, $suspendInsufficient);
                    
                    $processed++;
                    
                    switch ($result['status']) {
                        case 'charged':
                            $charged++;
                            $totalRevenue += $result['amount'];
                            break;
                        case 'suspended':
                            $suspended++;
                            if ($result['notification_sent']) {
                                $notificationsSent++;
                            }
                            break;
                        case 'failed':
                            $failed++;
                            break;
                    }
                }
                
                DB::commit();
                
                // Brief pause between batches
                usleep(100000); // 0.1 seconds
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('DID billing batch failed', [
                    'batch_index' => $batchIndex,
                    'error' => $e->getMessage()
                ]);
                $failed += $batch->count();
            }
        }

        return [
            'processed' => $processed,
            'charged' => $charged,
            'suspended' => $suspended,
            'failed' => $failed,
            'total_revenue' => $totalRevenue,
            'notifications_sent' => $notificationsSent,
            'total_dids' => $assignedDids->count()
        ];
    }

    /**
     * Process billing for individual DID
     */
    protected function processDidBilling(DidNumber $didNumber, Carbon $month, bool $suspendInsufficient): array
    {
        try {
            $user = $didNumber->user;
            $monthlyCharge = $didNumber->monthly_cost;
            
            $this->line("Processing DID {$didNumber->did_number} for user {$user->name} (${$monthlyCharge})");

            // Check if user has sufficient balance
            if ($user->balance < $monthlyCharge) {
                if ($suspendInsufficient) {
                    return $this->suspendDidForInsufficientBalance($didNumber, $user, $monthlyCharge, $month);
                } else {
                    // Still charge but mark as overdue
                    return $this->chargeDidWithOverdue($didNumber, $user, $monthlyCharge, $month);
                }
            }

            // Process the charge
            $success = $this->balanceService->deductBalance(
                $user,
                $monthlyCharge,
                'did_monthly_charge',
                "Monthly DID charge for {$didNumber->did_number} ({$month->format('Y-m')})",
                [
                    'did_number_id' => $didNumber->id,
                    'did_number' => $didNumber->did_number,
                    'billing_month' => $month->format('Y-m'),
                    'charge_type' => 'monthly_did'
                ]
            );

            if ($success) {
                // Update DID billing history
                $this->updateDidBillingHistory($didNumber, $monthlyCharge, $month, 'charged');
                
                $this->info("  ✓ Charged ${$monthlyCharge} for DID {$didNumber->did_number}");
                
                return [
                    'status' => 'charged',
                    'amount' => $monthlyCharge,
                    'did_number' => $didNumber->did_number
                ];
            } else {
                Log::error('Failed to deduct balance for DID charge', [
                    'user_id' => $user->id,
                    'did_number' => $didNumber->did_number,
                    'amount' => $monthlyCharge
                ]);
                
                return [
                    'status' => 'failed',
                    'error' => 'Balance deduction failed',
                    'did_number' => $didNumber->did_number
                ];
            }

        } catch (\Exception $e) {
            Log::error('DID billing processing failed', [
                'did_number_id' => $didNumber->id,
                'user_id' => $didNumber->user_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'did_number' => $didNumber->did_number
            ];
        }
    }

    /**
     * Suspend DID for insufficient balance
     */
    protected function suspendDidForInsufficientBalance(DidNumber $didNumber, User $user, float $monthlyCharge, Carbon $month): array
    {
        try {
            // Suspend the DID
            $didNumber->update([
                'status' => 'suspended',
                'suspension_reason' => 'insufficient_balance',
                'suspended_at' => now(),
                'suspension_details' => json_encode([
                    'required_amount' => $monthlyCharge,
                    'user_balance' => $user->balance,
                    'billing_month' => $month->format('Y-m'),
                    'suspended_by' => 'system'
                ])
            ]);

            // Update billing history
            $this->updateDidBillingHistory($didNumber, $monthlyCharge, $month, 'suspended_insufficient_balance');

            // Send notification to user
            $notificationSent = $this->sendDidSuspensionNotification($user, $didNumber, $monthlyCharge);

            $this->warn("  ⚠ Suspended DID {$didNumber->did_number} - insufficient balance (${$user->balance} < ${$monthlyCharge})");

            return [
                'status' => 'suspended',
                'did_number' => $didNumber->did_number,
                'required_amount' => $monthlyCharge,
                'user_balance' => $user->balance,
                'notification_sent' => $notificationSent
            ];

        } catch (\Exception $e) {
            Log::error('Failed to suspend DID for insufficient balance', [
                'did_number_id' => $didNumber->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'failed',
                'error' => 'Suspension failed: ' . $e->getMessage(),
                'did_number' => $didNumber->did_number
            ];
        }
    }

    /**
     * Charge DID but mark as overdue
     */
    protected function chargeDidWithOverdue(DidNumber $didNumber, User $user, float $monthlyCharge, Carbon $month): array
    {
        try {
            // Create negative balance transaction (overdue charge)
            BalanceTransaction::create([
                'user_id' => $user->id,
                'amount' => -$monthlyCharge,
                'type' => 'did_monthly_charge_overdue',
                'description' => "Overdue monthly DID charge for {$didNumber->did_number} ({$month->format('Y-m')})",
                'reference_id' => $didNumber->id,
                'reference_type' => DidNumber::class,
                'balance_before' => $user->balance,
                'balance_after' => $user->balance - $monthlyCharge,
                'processed_at' => now(),
                'metadata' => json_encode([
                    'did_number' => $didNumber->did_number,
                    'billing_month' => $month->format('Y-m'),
                    'charge_type' => 'monthly_did_overdue',
                    'original_balance' => $user->balance
                ])
            ]);

            // Update user balance
            $user->decrement('balance', $monthlyCharge);

            // Update DID billing history
            $this->updateDidBillingHistory($didNumber, $monthlyCharge, $month, 'charged_overdue');

            // Send overdue notification
            $this->sendOverdueNotification($user, $didNumber, $monthlyCharge);

            $this->warn("  ⚠ Charged ${$monthlyCharge} for DID {$didNumber->did_number} (overdue - negative balance)");

            return [
                'status' => 'charged',
                'amount' => $monthlyCharge,
                'did_number' => $didNumber->did_number,
                'overdue' => true
            ];

        } catch (\Exception $e) {
            Log::error('Failed to charge DID with overdue', [
                'did_number_id' => $didNumber->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'failed',
                'error' => 'Overdue charge failed: ' . $e->getMessage(),
                'did_number' => $didNumber->did_number
            ];
        }
    }

    /**
     * Update DID billing history
     */
    protected function updateDidBillingHistory(DidNumber $didNumber, float $amount, Carbon $month, string $status): void
    {
        $billingHistory = json_decode($didNumber->billing_history ?? '[]', true);
        
        $billingHistory[] = [
            'month' => $month->format('Y-m'),
            'amount' => $amount,
            'status' => $status,
            'processed_at' => now()->toISOString(),
            'user_balance_before' => $didNumber->user->balance + ($status === 'charged' ? $amount : 0),
            'user_balance_after' => $didNumber->user->balance
        ];

        $didNumber->update([
            'billing_history' => json_encode($billingHistory),
            'last_billed_at' => now()
        ]);
    }

    /**
     * Send DID suspension notification
     */
    protected function sendDidSuspensionNotification(User $user, DidNumber $didNumber, float $requiredAmount): bool
    {
        try {
            $this->emailService->sendEmail(
                $user->email,
                'DID Number Suspended - Insufficient Balance',
                'emails.did.suspension',
                [
                    'user' => $user,
                    'did_number' => $didNumber->did_number,
                    'required_amount' => $requiredAmount,
                    'current_balance' => $user->balance,
                    'shortfall' => $requiredAmount - $user->balance
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send DID suspension notification', [
                'user_id' => $user->id,
                'did_number' => $didNumber->did_number,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send overdue notification
     */
    protected function sendOverdueNotification(User $user, DidNumber $didNumber, float $amount): bool
    {
        try {
            $this->emailService->sendEmail(
                $user->email,
                'DID Monthly Charge - Account Overdue',
                'emails.did.overdue',
                [
                    'user' => $user,
                    'did_number' => $didNumber->did_number,
                    'charge_amount' => $amount,
                    'current_balance' => $user->balance
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send overdue notification', [
                'user_id' => $user->id,
                'did_number' => $didNumber->did_number,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Display billing preview for dry run
     */
    protected function displayBillingPreview($assignedDids, Carbon $month): void
    {
        $this->info('Monthly DID Billing Preview for ' . $month->format('Y-m') . ':');
        
        $headers = ['DID Number', 'User', 'Monthly Cost', 'User Balance', 'Status', 'Action'];
        $rows = [];
        $totalRevenue = 0;

        foreach ($assignedDids->take(20) as $didNumber) {
            $user = $didNumber->user;
            $monthlyCharge = $didNumber->monthly_cost;
            $totalRevenue += $monthlyCharge;
            
            $status = $user->balance >= $monthlyCharge ? 'Sufficient' : 'Insufficient';
            $action = $user->balance >= $monthlyCharge ? 'Charge' : 'Suspend/Overdue';
            
            $rows[] = [
                $didNumber->did_number,
                $user->name,
                '$' . number_format($monthlyCharge, 2),
                '$' . number_format($user->balance, 2),
                $status,
                $action
            ];
        }

        $this->table($headers, $rows);
        
        if ($assignedDids->count() > 20) {
            $this->info('... and ' . ($assignedDids->count() - 20) . ' more DIDs');
        }
        
        $this->info('Total potential revenue: $' . number_format($totalRevenue, 2));
    }

    /**
     * Mark month as processed
     */
    protected function markMonthAsProcessed(Carbon $month): void
    {
        SystemSetting::set('last_did_billing_month', $month->format('Y-m'));
        SystemSetting::set('last_did_billing_processed_at', now()->toISOString());
    }

    /**
     * Log billing summary
     */
    protected function logBillingSummary(array $results, Carbon $month): void
    {
        $duration = now()->diffInSeconds($this->startTime);
        
        $summary = [
            'billing_month' => $month->format('Y-m'),
            'execution_time_seconds' => $duration,
            'results' => $results,
            'timestamp' => now()->toISOString()
        ];

        Log::info('Monthly DID billing summary', $summary);

        // Display summary to console
        $this->info('Monthly DID Billing Summary:');
        $this->line('- Billing month: ' . $month->format('Y-m'));
        $this->line('- Execution time: ' . $duration . ' seconds');
        $this->line('- Total DIDs processed: ' . ($results['processed'] ?? 0));
        $this->line('- Successfully charged: ' . ($results['charged'] ?? 0));
        $this->line('- Suspended: ' . ($results['suspended'] ?? 0));
        $this->line('- Failed: ' . ($results['failed'] ?? 0));
        $this->line('- Total revenue: $' . number_format($results['total_revenue'] ?? 0, 2));
        $this->line('- Notifications sent: ' . ($results['notifications_sent'] ?? 0));
        
        if (isset($results['dry_run'])) {
            $this->info('- Mode: Dry Run (no charges processed)');
        }
    }

    /**
     * Update billing metrics
     */
    protected function updateBillingMetrics(array $results, Carbon $month): void
    {
        try {
            $metrics = [
                'last_did_billing_results' => json_encode($results),
                'last_did_billing_duration' => now()->diffInSeconds($this->startTime),
                'monthly_did_revenue_' . $month->format('Y_m') => $results['total_revenue'] ?? 0
            ];

            foreach ($metrics as $key => $value) {
                SystemSetting::set($key, $value);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to update billing metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }
}