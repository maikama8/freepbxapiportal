<?php

namespace App\Services\FreePBX;

use App\Models\User;
use App\Models\SipAccount;
use App\Exceptions\FreePBXApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class ExtensionService
{
    protected FreePBXApiClient $apiClient;

    public function __construct(FreePBXApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Create a new extension in FreePBX and return the extension details
     */
    public function createExtension(User $user, ?string $extension = null, ?string $password = null): array
    {
        try {
            // Generate extension number if not provided
            if (!$extension) {
                $extension = SipAccount::getNextAvailableExtension();
            }

            // Generate secure password if not provided
            if (!$password) {
                $password = $this->generateSecurePassword();
            }

            $vmPassword = $this->generateVoicemailPassword();

            Log::info('Creating FreePBX extension via GraphQL', [
                'user_id' => $user->id,
                'extension' => $extension,
                'user_name' => $user->name,
                'user_email' => $user->email
            ]);

            // Create extension via GraphQL API
            $response = $this->apiClient->createExtension($extension, $user->name, $user->email, [
                'tech' => 'pjsip',
                'vmEnable' => true,
                'vmPassword' => $vmPassword
            ]);

            if (!$response['status']) {
                throw new FreePBXApiException('Extension creation failed: ' . ($response['message'] ?? 'Unknown error'));
            }

            Log::info('FreePBX extension created successfully via GraphQL', [
                'user_id' => $user->id,
                'extension' => $extension,
                'response' => $response
            ]);

            return [
                'extension' => $extension,
                'password' => $password,
                'voicemail_password' => $vmPassword,
                'freepbx_response' => $response,
                'sip_server' => config('voip.freepbx.sip.domain', 'localhost'),
                'sip_port' => config('voip.freepbx.sip.port', 5060),
                'context' => config('voip.freepbx.default_context', 'from-internal')
            ];

        } catch (FreePBXApiException $e) {
            Log::error('Failed to create FreePBX extension', [
                'user_id' => $user->id,
                'extension' => $extension,
                'error' => $e->getMessage()
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error creating FreePBX extension', [
                'user_id' => $user->id,
                'extension' => $extension,
                'error' => $e->getMessage()
            ]);

            throw new FreePBXApiException('Failed to create extension: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing extension in FreePBX
     */
    public function updateExtension(string $extension, array $updateData): array
    {
        try {
            Log::info('Updating FreePBX extension via GraphQL', [
                'extension' => $extension,
                'update_data' => Arr::except($updateData, ['secret', 'vmpwd'])
            ]);

            $response = $this->apiClient->updateExtension($extension, $updateData);

            if (!$response['status']) {
                throw new FreePBXApiException('Extension update failed: ' . ($response['message'] ?? 'Unknown error'));
            }

            Log::info('FreePBX extension updated successfully via GraphQL', [
                'extension' => $extension,
                'response' => $response
            ]);

            return $response;

        } catch (FreePBXApiException $e) {
            Log::error('Failed to update FreePBX extension', [
                'extension' => $extension,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Delete an extension from FreePBX
     */
    public function deleteExtension(string $extension): bool
    {
        try {
            Log::info('Deleting FreePBX extension via GraphQL', ['extension' => $extension]);

            $response = $this->apiClient->deleteExtension($extension);

            if (!$response['status']) {
                Log::error('Failed to delete FreePBX extension', [
                    'extension' => $extension,
                    'error' => $response['message'] ?? 'Unknown error'
                ]);
                return false;
            }

            Log::info('FreePBX extension deleted successfully via GraphQL', ['extension' => $extension]);

            return true;

        } catch (FreePBXApiException $e) {
            Log::error('Failed to delete FreePBX extension', [
                'extension' => $extension,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get extension details from FreePBX
     */
    public function getExtension(string $extension): array
    {
        try {
            $result = $this->apiClient->getExtension($extension);
            
            if (!$result) {
                throw new FreePBXApiException("Extension {$extension} not found");
            }
            
            return $result;
        } catch (FreePBXApiException $e) {
            Log::error('Failed to get FreePBX extension', [
                'extension' => $extension,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Check if extension exists in FreePBX
     */
    public function extensionExists(string $extension): bool
    {
        try {
            $result = $this->apiClient->getExtension($extension);
            return $result !== null;
        } catch (FreePBXApiException $e) {
            return false;
        }
    }

    /**
     * Get all extensions from FreePBX
     */
    public function getAllExtensions(): array
    {
        try {
            return $this->apiClient->getAllExtensions();
        } catch (FreePBXApiException $e) {
            Log::error('Failed to get all FreePBX extensions', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Apply FreePBX configuration changes
     */
    protected function applyConfiguration(): void
    {
        try {
            // Apply and reload configuration
            $this->apiClient->post('config/apply');
            
            Log::info('FreePBX configuration applied successfully');
        } catch (FreePBXApiException $e) {
            Log::warning('Failed to apply FreePBX configuration', [
                'error' => $e->getMessage()
            ]);
            
            // Don't throw exception as the extension was created successfully
            // Configuration will be applied on next reload
        }
    }

    /**
     * Generate a secure SIP password
     */
    protected function generateSecurePassword(): string
    {
        // Generate a strong password with mixed case, numbers, and symbols
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < 16; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }

    /**
     * Generate a numeric voicemail password
     */
    protected function generateVoicemailPassword(): string
    {
        return str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Test FreePBX connection
     */
    public function testConnection(): bool
    {
        return $this->apiClient->testConnection();
    }
}