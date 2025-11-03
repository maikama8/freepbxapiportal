<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\FreePBXApiClient;

class IntrospectGraphQL extends Command
{
    protected $signature = 'freepbx:introspect-graphql';
    protected $description = 'Introspect GraphQL schema to find extension fields';

    public function handle()
    {
        $apiClient = app(FreePBXApiClient::class);
        
        // Get query type fields
        $this->info("Getting Query type fields...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ __schema { queryType { fields { name description } } } }'
            ]);
            
            $this->line("Query fields:");
            if (isset($result['data']['__schema']['queryType']['fields'])) {
                foreach ($result['data']['__schema']['queryType']['fields'] as $field) {
                    $this->line("- {$field['name']}: {$field['description']}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to get query fields: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Get mutation type fields
        $this->info("Getting Mutation type fields...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ __schema { mutationType { fields { name description } } } }'
            ]);
            
            $this->line("Mutation fields:");
            if (isset($result['data']['__schema']['mutationType']['fields'])) {
                foreach ($result['data']['__schema']['mutationType']['fields'] as $field) {
                    if (str_contains(strtolower($field['name']), 'extension')) {
                        $this->line("- {$field['name']}: {$field['description']}");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to get mutation fields: " . $e->getMessage());
        }
        
        $this->line('');
        
        // Get addExtensionInput type details
        $this->info("Getting addExtensionInput type details...");
        try {
            $result = $apiClient->post('gql', [
                'query' => '{ __type(name: "addExtensionInput") { inputFields { name type { name } } } }'
            ]);
            
            $this->line("addExtensionInput fields:");
            if (isset($result['data']['__type']['inputFields'])) {
                foreach ($result['data']['__type']['inputFields'] as $field) {
                    $typeName = $field['type']['name'] ?? 'Unknown';
                    $this->line("- {$field['name']}: {$typeName}");
                }
            }
        } catch (\Exception $e) {
            $this->error("Failed to get addExtensionInput details: " . $e->getMessage());
        }
    }
}