<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;

class GetExtensionSchema extends Command
{
    protected $signature = 'freepbx:get-extension-schema';
    protected $description = 'Get the correct GraphQL schema for extensions';

    public function handle()
    {
        $apiClient = app(FreePBXApiClient::class);
        
        // Get extension type fields
        $this->info("Getting extension type fields...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ __type(name: "extension") { fields { name type { name } } } }'
            ]);
            
            $this->line("Extension type fields:");
            if (isset($result['data']['__type']['fields'])) {
                foreach ($result['data']['__type']['fields'] as $field) {
                    $typeName = $field['type']['name'] ?? 'Unknown';
                    $this->line("- {$field['name']}: {$typeName}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to get extension type: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Get ExtensionConnection type fields
        $this->info("Getting ExtensionConnection type fields...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ __type(name: "ExtensionConnection") { fields { name type { name } } } }'
            ]);
            
            $this->line("ExtensionConnection type fields:");
            if (isset($result['data']['__type']['fields'])) {
                foreach ($result['data']['__type']['fields'] as $field) {
                    $typeName = $field['type']['name'] ?? 'Unknown';
                    $this->line("- {$field['name']}: {$typeName}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to get ExtensionConnection type: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Get addExtensionPayload type fields
        $this->info("Getting addExtensionPayload type fields...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ __type(name: "addExtensionPayload") { fields { name type { name } } } }'
            ]);
            
            $this->line("addExtensionPayload type fields:");
            if (isset($result['data']['__type']['fields'])) {
                foreach ($result['data']['__type']['fields'] as $field) {
                    $typeName = $field['type']['name'] ?? 'Unknown';
                    $this->line("- {$field['name']}: {$typeName}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to get addExtensionPayload type: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Get fetchExtension field arguments
        $this->info("Getting fetchExtension field arguments...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ __schema { queryType { fields(includeDeprecated: true) { name args { name type { name } } } } } }'
            ]);
            
            if (isset($result['data']['__schema']['queryType']['fields'])) {
                foreach ($result['data']['__schema']['queryType']['fields'] as $field) {
                    if ($field['name'] === 'fetchExtension') {
                        $this->line("fetchExtension arguments:");
                        foreach ($field['args'] as $arg) {
                            $typeName = $arg['type']['name'] ?? 'Unknown';
                            $this->line("- {$arg['name']}: {$typeName}");
                        }
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to get fetchExtension arguments: " . $e->getMessage());
        }
    }
}