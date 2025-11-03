<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;

class TestGraphQLCorrect extends Command
{
    protected $signature = 'freepbx:test-graphql-correct {extension} {name} {email}';
    protected $description = 'Test GraphQL with correct schema';

    public function handle()
    {
        $extension = $this->argument('extension');
        $name = $this->argument('name');
        $email = $this->argument('email');
        
        $apiClient = app(FreePBXApiClient::class);
        
        // Test fetching all extensions with correct schema
        $this->info("Testing fetchAllExtensions with correct schema...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ fetchAllExtensions { extension { extensionId tech status } } }'
            ]);
            
            $this->line("✅ Current extensions:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch extensions: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Test creating an extension via GraphQL mutation
        $this->info("Testing: Create Extension {$extension} via GraphQL");
        
        try {
            $mutation = 'mutation {
                addExtension(input: {
                    extensionId: "' . $extension . '"
                    name: "' . $name . '"
                    email: "' . $email . '"
                    tech: "pjsip"
                    vmEnable: true
                    vmPassword: "1234"
                }) {
                    status
                    message
                }
            }';
            
            $result = $apiClient->post('gql', [
                'query' => $mutation
            ]);
            
            $this->line("✅ Extension creation result:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
        } catch (\Exception $e) {
            $this->error("❌ Extension creation failed: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Test fetching the specific extension
        $this->info("Testing: Fetch Extension {$extension}");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ fetchExtension(extensionId: "' . $extension . '") { extensionId tech status } }'
            ]);
            
            $this->line("✅ Extension details:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch extension: " . $e->getMessage());
        }
    }
}