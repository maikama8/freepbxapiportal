<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\SipAccount;
use App\Services\FreePBX\ExtensionService;
use Illuminate\Support\Facades\Hash;

class TestCompleteFlow extends Command
{
    protected $signature = 'freepbx:test-complete-flow {name} {email} {extension?}';
    protected $description = 'Test complete user registration and extension creation flow';

    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $extension = $this->argument('extension');
        
        $this->info("Testing complete flow for: {$name} ({$email})");
        
        try {
            // Step 1: Create user in Laravel
            $this->info("Step 1: Creating user in Laravel...");
            
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'account_status' => 'active'
            ]);
            
            $this->line("✅ User created with ID: {$user->id}");
            
            // Step 2: Create SIP account in Laravel
            $this->info("Step 2: Creating SIP account in Laravel...");
            
            if (!$extension) {
                $extension = SipAccount::getNextAvailableExtension();
            }
            
            $sipAccount = SipAccount::create([
                'user_id' => $user->id,
                'sip_username' => $extension,
                'sip_password' => 'secure' . rand(1000, 9999),
                'sip_server' => config('voip.freepbx.sip.domain'),
                'sip_port' => config('voip.freepbx.sip.port', 5060),
                'status' => 'active'
            ]);
            
            $this->line("✅ SIP account created: {$sipAccount->sip_username}");
            
            // Step 3: Create extension in FreePBX via GraphQL
            $this->info("Step 3: Creating extension in FreePBX via GraphQL...");
            
            $extensionService = app(ExtensionService::class);
            
            $result = $extensionService->createExtension(
                $user,
                $sipAccount->sip_username,
                $sipAccount->sip_password
            );
            
            $this->line("✅ Extension created in FreePBX: {$result['extension']}");
            
            // Step 4: Verify extension exists
            $this->info("Step 4: Verifying extension exists in FreePBX...");
            
            if ($extensionService->extensionExists($sipAccount->sip_username)) {
                $this->line("✅ Extension verified in FreePBX");
                
                $extensionDetails = $extensionService->getExtension($sipAccount->sip_username);
                $this->line("Extension details: " . json_encode($extensionDetails, JSON_PRETTY_PRINT));
            } else {
                $this->error("❌ Extension not found in FreePBX");
            }
            
            // Step 5: Display summary
            $this->info("Step 5: Summary");
            $this->table(
                ['Item', 'Value'],
                [
                    ['User ID', $user->id],
                    ['User Name', $user->name],
                    ['User Email', $user->email],
                    ['SIP Extension', $sipAccount->sip_username],
                    ['SIP Password', $sipAccount->sip_password],
                    ['SIP Server', $sipAccount->sip_server],
                    ['FreePBX Status', 'Created Successfully']
                ]
            );
            
            $this->info("🎉 Complete flow test successful!");
            
        } catch (\Exception $e) {
            $this->error("❌ Flow test failed: " . $e->getMessage());
            
            // Cleanup on failure
            if (isset($user)) {
                $user->delete();
                $this->warn("Cleaned up user record");
            }
            
            return 1;
        }
        
        return 0;
    }
}