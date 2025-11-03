<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;

class TestGraphQLCreateExtension extends Command
{
    protected $signature = 'freepbx:test-graphql-create {extension} {name} {email}';
    protected $description = 'Test creating extension via GraphQL with correct schema';

    public function handle()
    {
        $extension = $this->argument('extension');
        $name = $this->argument('name');
        $email = $this->argument('email');
        
        $apiClient = app(FreePBXApiClient::class);
        
        // First, check if we can fetch extensions
        $this->info("Testing fetchAllExtensions query...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ fetchAllExtensions { extension name } }'
            ]);
            
            $this->line("✅ Current extensions:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch extensions: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Test creating an extension via GraphQL mutation with correct fields
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
                    extension {
                        extension
                        name
                    }
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
                'query' => '{ fetchExtension(extension: "' . $extension . '") { extension name email } }'
            ]);
            
            $this->line("✅ Extension details:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch extension: " . $e->getMessage());
        }
    }
}