<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestFreePBXAuth extends Command
{
    protected $signature = 'freepbx:test-auth {--method=oauth2 : Authentication method (oauth2, basic, session)}';
    protected $description = 'Test different FreePBX authentication methods';

    public function handle()
    {
        $method = $this->option('method');
        $apiUrl = config('voip.freepbx.api_url');
        $username = config('voip.freepbx.username');
        $password = config('voip.freepbx.password');
        $clientId = config('voip.freepbx.client_id');
        $clientSecret = config('voip.freepbx.client_secret');
        
        $this->info("🔐 Testing FreePBX Authentication Method: {$method}");
        $this->newLine();
        
        switch ($method) {
            case 'oauth2':
                $this->testOAuth2($apiUrl, $username, $password, $clientId, $clientSecret);
                break;
                
            case 'basic':
                $this->testBasicAuth($apiUrl, $username, $password);
                break;
                
            case 'session':
                $this->testSessionAuth($apiUrl, $username, $password);
                break;
                
            default:
                $this->error("Unknown method: {$method}");
                $this->info("Available methods: oauth2, basic, session");
                return 1;
        }
        
        return 0;
    }
    
    private function testOAuth2($apiUrl, $username, $password, $clientId, $clientSecret)
    {
        $this->info('Testing OAuth2 Authentication...');
        
        try {
            // Get token
            $tokenResponse = Http::asForm()->post("{$apiUrl}/admin/api/api/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $username,
                'password' => $password
            ]);
            
            if ($tokenResponse->successful()) {
                $token = $tokenResponse->json()['access_token'];
                $this->info('✅ OAuth2 token obtained');
                
                // Test API call
                $response = Http::withToken($token)
                    ->get("{$apiUrl}/admin/api/api/extensions");
                
                $this->info("API Response: {$response->status()}");
                if ($response->failed()) {
                    $this->error("Response: " . $response->body());
                } else {
                    $this->info("✅ Extensions API accessible");
                }
            } else {
                $this->error("❌ OAuth2 failed: " . $tokenResponse->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ OAuth2 error: " . $e->getMessage());
        }
    }
    
    private function testBasicAuth($apiUrl, $username, $password)
    {
        $this->info('Testing Basic Authentication...');
        
        try {
            $response = Http::withBasicAuth($username, $password)
                ->get("{$apiUrl}/admin/api/api/extensions");
            
            $this->info("API Response: {$response->status()}");
            if ($response->failed()) {
                $this->error("Response: " . $response->body());
            } else {
                $this->info("✅ Basic auth works!");
            }
        } catch (\Exception $e) {
            $this->error("❌ Basic auth error: " . $e->getMessage());
        }
    }
    
    private function testSessionAuth($apiUrl, $username, $password)
    {
        $this->info('Testing Session-based Authentication...');
        
        try {
            // First login to get session
            $loginResponse = Http::asForm()->post("{$apiUrl}/admin/config.php", [
                'username' => $username,
                'password' => $password,
                'login' => 'Login'
            ]);
            
            if ($loginResponse->successful()) {
                $cookies = $loginResponse->cookies();
                $this->info('✅ Session login successful');
                
                // Test API with session cookies
                $response = Http::withCookies($cookies->toArray(), parse_url($apiUrl, PHP_URL_HOST))
                    ->get("{$apiUrl}/admin/api/api/extensions");
                
                $this->info("API Response: {$response->status()}");
                if ($response->failed()) {
                    $this->error("Response: " . $response->body());
                } else {
                    $this->info("✅ Session auth works!");
                }
            } else {
                $this->error("❌ Session login failed");
            }
        } catch (\Exception $e) {
            $this->error("❌ Session auth error: " . $e->getMessage());
        }
    }
}