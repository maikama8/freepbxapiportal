<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;

class TestFreePBXEndpoints extends Command
{
    protected $signature = 'freepbx:test-endpoints';
    protected $description = 'Test various FreePBX API endpoints to see what works';

    public function handle()
    {
        $apiClient = app(FreePBXApiClient::class);
        
        $endpoints = [
            'system/version' => 'GET',
            'system/status' => 'GET',
            'extensions' => 'GET',
            'extensions/2000' => 'GET',
            'gql' => 'POST'
        ];
        
        foreach ($endpoints as $endpoint => $method) {
            $this->info("Testing {$method} {$endpoint}...");
            
            try {
                if ($method === 'POST' && $endpoint === 'gql') {
                    $result = $apiClient->post($endpoint, [
                        'query' => '{ system { version } }'
                    ]);
                } else {
                    $result = $apiClient->get($endpoint);
                }
                
                $this->line("✅ Success: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                
            } catch (\Exception $e) {
                $this->error("❌ Failed: " . $e->getMessage());
            }
            
            $this->line('');
        }
    }
}