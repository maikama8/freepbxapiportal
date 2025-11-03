<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FreePBX\ExtensionService;
use App\Models\User;

class TestCreateExtension extends Command
{
    protected $signature = 'freepbx:test-create-extension {extension} {name}';
    protected $description = 'Test creating a new extension via FreePBX API';

    public function handle()
    {
        $extension = $this->argument('extension');
        $name = $this->argument('name');
        
        $this->info("Testing extension creation for: {$extension} - {$name}");
        
        try {
            $extensionService = app(ExtensionService::class);
            
            // Test if extension already exists
            $this->info("Checking if extension {$extension} already exists...");
            
            if ($extensionService->extensionExists($extension)) {
                $this->warn("Extension {$extension} already exists!");
                try {
                    $existing = $extensionService->getExtension($extension);
                    $this->line("Existing extension data:");
                    $this->line(json_encode($existing, JSON_PRETTY_PRINT));
                } catch (\Exception $e) {
                    $this->warn("Could not retrieve extension details: " . $e->getMessage());
                }
                return;
            }
            
            $this->info("Extension {$extension} does not exist. Creating...");
            
            // Create a temporary user for testing
            $testUser = new User([
                'name' => $name,
                'email' => 'test@example.com'
            ]);
            
            // Try to create the extension
            $result = $extensionService->createExtension($testUser, $extension, 'test123456');
            
            $this->info("Extension creation result:");
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
        }
    }
}