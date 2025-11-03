<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;
use App\Services\FreePBX\CDRService;
use Exception;

class TestFreePBXConnection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'freepbx:test-connection';

    /**
     * The console command description.
     */
    protected $description = 'Test FreePBX API and database connections';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing FreePBX Integration...');
        $this->newLine();

        // Test configuration
        $this->testConfiguration();
        
        // Test API connection
        $this->testApiConnection();
        
        // Test database connection
        $this->testDatabaseConnection();
        
        $this->newLine();
        $this->info('FreePBX connection test completed!');
    }

    /**
     * Test FreePBX configuration
     */
    private function testConfiguration()
    {
        $this->info('1. Testing Configuration...');
        
        $config = config('voip.freepbx');
        
        $this->table(['Setting', 'Value', 'Status'], [
            ['API URL', $config['api_url'], $config['api_url'] !== 'http://localhost' ? '✅ Configured' : '❌ Default'],
            ['API Username', $config['username'] ? '***' : 'Not Set', $config['username'] ? '✅ Set' : '❌ Missing'],
            ['API Password', $config['password'] ? '***' : 'Not Set', $config['password'] ? '✅ Set' : '❌ Missing'],
            ['API Version', $config['version'], '✅ Set'],
            ['DB Host', $config['database']['host'] ?? 'Not Set', isset($config['database']['host']) ? '✅ Set' : '❌ Missing'],
            ['DB Database', $config['database']['database'] ?? 'Not Set', isset($config['database']['database']) ? '✅ Set' : '❌ Missing'],
            ['SIP Domain', $config['sip']['domain'] ?? 'Not Set', isset($config['sip']['domain']) ? '✅ Set' : '❌ Missing'],
        ]);
    }

    /**
     * Test FreePBX API connection
     */
    private function testApiConnection()
    {
        $this->info('2. Testing FreePBX API Connection...');
        
        try {
            $client = app(FreePBXApiClient::class);
            
            // Test basic connection
            $this->line('   → Testing API endpoint...');
            $response = $client->get('extensions');
            
            if ($response && isset($response['status']) && $response['status'] === true) {
                $this->line('   ✅ API connection successful');
                $this->line('   ✅ Authentication successful');
                
                if (isset($response['data']) && is_array($response['data'])) {
                    $extensionCount = count($response['data']);
                    $this->line("   ✅ Found {$extensionCount} extensions");
                }
            } else {
                $this->line('   ❌ API connection failed - Invalid response');
            }
            
        } catch (Exception $e) {
            $this->line('   ❌ API connection failed: ' . $e->getMessage());
            $this->warn('   Check your FREEPBX_API_* settings in .env file');
        }
    }

    /**
     * Test FreePBX database connection
     */
    private function testDatabaseConnection()
    {
        $this->info('3. Testing FreePBX Database Connection...');
        
        try {
            $cdrService = app(CDRService::class);
            
            $this->line('   → Testing CDR database connection...');
            $recentCalls = $cdrService->getRecentCalls(5);
            
            if ($recentCalls !== null) {
                $this->line('   ✅ CDR database connection successful');
                $callCount = count($recentCalls);
                $this->line("   ✅ Found {$callCount} recent call records");
                
                if ($callCount > 0) {
                    $this->line('   ✅ CDR data is accessible');
                }
            } else {
                $this->line('   ❌ CDR database connection failed');
            }
            
        } catch (Exception $e) {
            $this->line('   ❌ Database connection failed: ' . $e->getMessage());
            $this->warn('   Check your FREEPBX_DB_* settings in .env file');
        }
    }
}