<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DiagnoseFreePBXAPI extends Command
{
    protected $signature = 'freepbx:diagnose-api';
    protected $description = 'Diagnose FreePBX API connection and permissions';

    public function handle()
    {
        $this->info('🔍 Diagnosing FreePBX API Connection...');
        $this->newLine();
        
        $apiUrl = config('voip.freepbx.api_url');
        $username = config('voip.freepbx.username');
        $password = config('voip.freepbx.password');
        $clientId = config('voip.freepbx.client_id');
        $clientSecret = config('voip.freepbx.client_secret');
        
        // Step 1: Check if FreePBX is reachable
        $this->info('1. Testing FreePBX Server Connectivity...');
        try {
            $response = Http::timeout(10)->get($apiUrl);
            if ($response->successful()) {
                $this->info("   ✅ FreePBX server is reachable at {$apiUrl}");
            } else {
                $this->error("   ❌ FreePBX server returned status: {$response->status()}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Cannot reach FreePBX server: " . $e->getMessage());
            return 1;
        }
        
        // Step 2: Test OAuth2 endpoint
        $this->info('2. Testing OAuth2 Token Endpoint...');
        try {
            $tokenResponse = Http::asForm()->post("{$apiUrl}/admin/api/api/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $username,
                'password' => $password
            ]);
            
            if ($tokenResponse->successful()) {
                $tokenData = $tokenResponse->json();
                $this->info("   ✅ OAuth2 authentication successful");
                $this->info("   📝 Token type: " . ($tokenData['token_type'] ?? 'unknown'));
                $this->info("   ⏰ Expires in: " . ($tokenData['expires_in'] ?? 'unknown') . " seconds");
                
                $accessToken = $tokenData['access_token'] ?? null;
                
                if ($accessToken) {
                    // Step 3: Test API endpoints with token
                    $this->info('3. Testing API Endpoints...');
                    
                    // Test different API paths
                    $apiPaths = [
                        '/admin/api/api/',
                        '/admin/api/api',
                        '/api/api/',
                        '/api/'
                    ];
                    
                    $workingPath = null;
                    foreach ($apiPaths as $path) {
                        $testUrl = rtrim($apiUrl, '/') . $path;
                        $apiResponse = Http::withToken($accessToken)->get($testUrl);
                        
                        if ($apiResponse->successful()) {
                            $this->info("   ✅ API access successful at: {$path}");
                            $workingPath = $path;
                            break;
                        } else {
                            $this->line("   ⚠️  Path {$path} returned: {$apiResponse->status()}");
                        }
                    }
                    
                    if ($workingPath) {
                        // Test extensions endpoint
                        $extResponse = Http::withToken($accessToken)
                            ->get(rtrim($apiUrl, '/') . $workingPath . 'extensions');
                        
                        if ($extResponse->successful()) {
                            $this->info("   ✅ Extensions API access successful");
                            $extensions = $extResponse->json();
                            $this->info("   📊 Found " . count($extensions) . " extensions");
                            
                            // Test creating an extension (dry run)
                            $createResponse = Http::withToken($accessToken)
                                ->post(rtrim($apiUrl, '/') . $workingPath . 'extensions', [
                                    'extension' => '9999',
                                    'name' => 'Test Extension',
                                    'secret' => 'testpass123'
                                ]);
                            
                            if ($createResponse->successful()) {
                                $this->info("   ✅ Extension creation permissions OK");
                                // Clean up test extension
                                Http::withToken($accessToken)
                                    ->delete(rtrim($apiUrl, '/') . $workingPath . 'extensions/9999');
                            } else {
                                $this->error("   ❌ Extension creation failed: " . $createResponse->status());
                                $this->error("   📝 Response: " . $createResponse->body());
                            }
                            
                        } else {
                            $this->error("   ❌ Extensions API failed: " . $extResponse->status());
                            $this->error("   📝 Response: " . $extResponse->body());
                        }
                        
                    } else {
                        $this->error("   ❌ No working API path found");
                        $this->warn("   💡 The API might be disabled or using a different path");
                    }
                }
                
            } else {
                $this->error("   ❌ OAuth2 authentication failed: " . $tokenResponse->status());
                $this->error("   📝 Response: " . $tokenResponse->body());
                
                // Provide specific guidance based on error
                $errorBody = $tokenResponse->body();
                if (str_contains($errorBody, 'invalid_client')) {
                    $this->warn("   💡 This suggests the OAuth2 client ID/secret is incorrect");
                } elseif (str_contains($errorBody, 'invalid_grant')) {
                    $this->warn("   💡 This suggests the username/password is incorrect");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ OAuth2 test failed: " . $e->getMessage());
        }
        
        // Step 4: Provide configuration guidance
        $this->newLine();
        $this->info('📋 Current Configuration:');
        $this->table(
            ['Setting', 'Value', 'Status'],
            [
                ['API URL', $apiUrl, $apiUrl ? '✅' : '❌'],
                ['Username', $username ? '***' : 'Not set', $username ? '✅' : '❌'],
                ['Password', $password ? '***' : 'Not set', $password ? '✅' : '❌'],
                ['Client ID', $clientId ? substr($clientId, 0, 8) . '...' : 'Not set', $clientId ? '✅' : '❌'],
                ['Client Secret', $clientSecret ? '***' : 'Not set', $clientSecret ? '✅' : '❌'],
            ]
        );
        
        $this->newLine();
        $this->info('🔧 Next Steps:');
        $this->line('1. Log into FreePBX Admin Panel: ' . $apiUrl);
        $this->line('2. Go to: Connectivity → API');
        $this->line('3. Ensure API is enabled');
        $this->line('4. Check OAuth2 client permissions');
        $this->line('5. Verify user has admin privileges');
        
        return 0;
    }
}