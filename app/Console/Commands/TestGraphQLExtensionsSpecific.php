<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;

class TestGraphQLExtensionsSpecific extends Command
{
    protected $signature = 'freepbx:test-graphql-specific';
    protected $description = 'Test specific GraphQL queries for extensions';

    public function handle()
    {
        $apiClient = app(FreePBXApiClient::class);
        
        $queries = [
            'List All Extensions' => '{ extensions { extension displayname secret } }',
            'Get Extension 2000' => '{ extension(extension: "2000") { extension displayname secret } }',
            'Get Extension 2001' => '{ extension(extension: "2001") { extension displayname secret } }',
            'Get Extension 2002' => '{ extension(extension: "2002") { extension displayname secret } }'
        ];
        
        foreach ($queries as $name => $query) {
            $this->info("Testing: {$name}");
            
            try {
                $result = $apiClient->post('gql', [
                    'query' => $query
                ]);
                
                $this->line("✅ Success:");
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                
            } catch (\Exception $e) {
                $this->error("❌ Failed: " . $e->getMessage());
            }
            
            $this->line('');
        }
        
        // Test creating an extension via GraphQL mutation
        $this->info("Testing: Create Extension 2003 via GraphQL");
        
        try {
            $mutation = 'mutation {
                addExtension(input: {
                    extension: "2003"
                    displayname: "Test Extension 2003"
                    secret: "test123456"
                }) {
                    extension
                    displayname
                }
            }';
            
            $result = $apiClient->post('gql', [
                'query' => $mutation
            ]);
            
            $this->line("✅ Extension creation success:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
        } catch (\Exception $e) {
            $this->error("❌ Extension creation failed: " . $e->getMessage());
        }
    }
}