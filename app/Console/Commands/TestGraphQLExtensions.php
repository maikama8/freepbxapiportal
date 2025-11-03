<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;

class TestGraphQLExtensions extends Command
{
    protected $signature = 'freepbx:test-graphql-extensions';
    protected $description = 'Test GraphQL queries for extensions';

    public function handle()
    {
        $apiClient = app(FreePBXApiClient::class);
        
        $queries = [
            'List Extensions' => '{ extensions { extension displayname } }',
            'System Info' => '{ system { version hostname } }',
            'Extension Details' => '{ extension(extension: "2000") { extension displayname secret } }',
            'Schema Info' => '{ __schema { types { name } } }'
        ];
        
        foreach ($queries as $name => $query) {
            $this->info("Testing: {$name}");
            $this->line("Query: {$query}");
            
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
    }
}