<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SipAccount;
use App\Services\FreePBX\ExtensionService;
use App\Services\FreePBX\FreePBXApiClient;

class SyncSipAccountsToFreePBX extends Command
{
    protected $signature = 'freepbx:sync-sip-accounts {--force : Force sync even if extension exists}';
    protected $description = 'Sync SIP accounts from Laravel to FreePBX';

    public function handle()
    {
        $this->info('Starting SIP accounts sync to FreePBX...');
        
        $extensionService = app(ExtensionService::class);
        $apiClient = app(FreePBXApiClient::class);
        
        // Get all SIP accounts
        $sipAccounts = SipAccount::with('user')->get();
        
        if ($sipAccounts->isEmpty()) {
            $this->warn('No SIP accounts found to sync.');
            return 0;
        }
        
        $this->info("Found {$sipAccounts->count()} SIP accounts to sync.");
        
        $synced = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($sipAccounts as $sipAccount) {
            $this->line("Processing SIP account: {$sipAccount->sip_username} for user {$sipAccount->user->name}");
            
            try {
                // Check if extension already exists in FreePBX using GraphQL
                $extensionExists = $extensionService->extensionExists($sipAccount->sip_username);
                
                if ($extensionExists && !$this->option('force')) {
                    $this->warn("  → Extension {$sipAccount->sip_username} already exists in FreePBX. Use --force to overwrite.");
                    $skipped++;
                    continue;
                }
                
                if ($extensionExists && $this->option('force')) {
                    // Update existing extension
                    $result = $extensionService->updateExtension($sipAccount->sip_username, [
                        'name' => $sipAccount->user->name,
                        'email' => $sipAccount->user->email
                    ]);
                    $this->info("  ✅ Successfully updated extension {$sipAccount->sip_username}");
                } else {
                    // Create new extension in FreePBX
                    $result = $extensionService->createExtension(
                        $sipAccount->user,
                        $sipAccount->sip_username,
                        $sipAccount->sip_password
                    );
                    $this->info("  ✅ Successfully created extension {$sipAccount->sip_username}");
                }
                
                $synced++;
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed to sync extension {$sipAccount->sip_username}: " . $e->getMessage());
                $errors++;
            }
        }
        
        $this->newLine();
        $this->info("Sync completed!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Synced', $synced],
                ['Skipped', $skipped],
                ['Errors', $errors],
                ['Total', $sipAccounts->count()]
            ]
        );
        
        if ($errors > 0) {
            $this->warn("Some extensions failed to sync. Check the logs for details.");
            return 1;
        }
        
        return 0;
    }
}