<?php

namespace App\Services\FreePBX;

use App\Models\User;
use App\Models\CallRecord;
use App\Exceptions\FreePBXApiException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CallManagementService
{
    protected FreePBXApiClient $apiClient;

    public function __construct(FreePBXApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * Initiate a new call
     */
    public function initiateCall(User $user, string $destination, string $callerId = null): array
    {
        try {
            // Generate unique call ID
            $callId = $this->generateCallId();
            
            // Use user's extension or create temporary one
            $extension = $user->extension ?? $this->getOrCreateUserExtension($user);
            
            // Prepare call data
            $callData = [
                'extension' => $extension,
                'destination' => $this->formatDestination($destination),
                'caller_id' => $callerId ?? $user->phone ?? $extension,
                'call_id' => $callId,
                'timeout' => config('voip.platform.call_timeout', 3600)
            ];

            Log::info('Initiating call', [
                'user_id' => $user->id,
                'call_data' => $callData
            ]);

            // Make API call to FreePBX
            $response = $this->apiClient->post('calls/originate', $callData);

            // Create call record
            $callRecord = $this->createCallRecord($user, $callData, $response);

            return [
                'success' => true,
                'call_id' => $callId,
                'call_record_id' => $callRecord->id,
                'status' => 'initiated',
                'message' => 'Call initiated successfully'
            ];

        } catch (FreePBXApiException $e) {
            Log::error('Call initiation failed', [
                'user_id' => $user->id,
                'destination' => $destination,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Terminate an active call
     */
    public function terminateCall(string $callId): array
    {
        try {
            Log::info('Terminating call', ['call_id' => $callId]);

            $response = $this->apiClient->post("calls/{$callId}/hangup");

            // Update call record
            $this->updateCallRecordOnTermination($callId);

            return [
                'success' => true,
                'call_id' => $callId,
                'status' => 'terminated',
                'message' => 'Call terminated successfully'
            ];

        } catch (FreePBXApiException $e) {
            Log::error('Call termination failed', [
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get call status and monitoring information
     */
    public function getCallStatus(string $callId): array
    {
        try {
            $response = $this->apiClient->get("calls/{$callId}/status");

            // Update local call record with latest status
            $this->updateCallRecordStatus($callId, $response);

            return $response;

        } catch (FreePBXApiException $e) {
            Log::error('Failed to get call status', [
                'call_id' => $callId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get all active calls
     */
    public function getActiveCalls(User $user = null): array
    {
        try {
            $endpoint = 'calls/active';
            $params = [];

            if ($user && $user->extension) {
                $params['extension'] = $user->extension;
            }

            return $this->apiClient->get($endpoint, $params);

        } catch (FreePBXApiException $e) {
            Log::error('Failed to get active calls', [
                'user_id' => $user?->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get or create SIP extension for user
     */
    public function getOrCreateUserExtension(User $user): string
    {
        if ($user->extension) {
            return $user->extension;
        }

        try {
            // Generate extension number (starting from 1000)
            $extension = $this->generateExtensionNumber();

            // Create extension in FreePBX
            $extensionData = [
                'extension' => $extension,
                'name' => $user->name,
                'email' => $user->email,
                'secret' => Str::random(16),
                'context' => 'from-internal',
                'codec' => 'ulaw,alaw,g729',
                'nat' => 'yes'
            ];

            $response = $this->apiClient->post('extensions', $extensionData);

            // Update user with extension
            $user->update(['extension' => $extension]);

            Log::info('Created extension for user', [
                'user_id' => $user->id,
                'extension' => $extension
            ]);

            return $extension;

        } catch (FreePBXApiException $e) {
            Log::error('Failed to create extension', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Update extension configuration
     */
    public function updateExtension(User $user, array $config): array
    {
        if (!$user->extension) {
            throw new FreePBXApiException('User does not have an extension');
        }

        try {
            $response = $this->apiClient->put("extensions/{$user->extension}", $config);

            Log::info('Updated extension configuration', [
                'user_id' => $user->id,
                'extension' => $user->extension,
                'config' => $config
            ]);

            return $response;

        } catch (FreePBXApiException $e) {
            Log::error('Failed to update extension', [
                'user_id' => $user->id,
                'extension' => $user->extension,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Delete user extension
     */
    public function deleteExtension(User $user): bool
    {
        if (!$user->extension) {
            return true;
        }

        try {
            $this->apiClient->delete("extensions/{$user->extension}");

            // Clear extension from user
            $user->update(['extension' => null]);

            Log::info('Deleted extension', [
                'user_id' => $user->id,
                'extension' => $user->extension
            ]);

            return true;

        } catch (FreePBXApiException $e) {
            Log::error('Failed to delete extension', [
                'user_id' => $user->id,
                'extension' => $user->extension,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate unique call ID
     */
    protected function generateCallId(): string
    {
        return 'call_' . time() . '_' . Str::random(8);
    }

    /**
     * Generate extension number
     */
    protected function generateExtensionNumber(): string
    {
        // Find the highest existing extension number
        $lastExtension = User::whereNotNull('extension')
            ->orderBy('extension', 'desc')
            ->value('extension');

        $nextNumber = $lastExtension ? (int)$lastExtension + 1 : 1000;

        return (string)$nextNumber;
    }

    /**
     * Format destination number
     */
    protected function formatDestination(string $destination): string
    {
        // Remove non-numeric characters except +
        $formatted = preg_replace('/[^\d+]/', '', $destination);

        // Ensure proper international format
        if (!str_starts_with($formatted, '+') && strlen($formatted) > 10) {
            $formatted = '+' . $formatted;
        }

        return $formatted;
    }

    /**
     * Create call record in database
     */
    protected function createCallRecord(User $user, array $callData, array $response): CallRecord
    {
        return CallRecord::create([
            'user_id' => $user->id,
            'call_id' => $callData['call_id'],
            'caller_id' => $callData['caller_id'],
            'destination' => $callData['destination'],
            'start_time' => now(),
            'status' => 'initiated',
            'freepbx_response' => $response
        ]);
    }

    /**
     * Update call record on termination
     */
    protected function updateCallRecordOnTermination(string $callId): void
    {
        CallRecord::where('call_id', $callId)
            ->update([
                'end_time' => now(),
                'status' => 'completed'
            ]);
    }

    /**
     * Update call record status
     */
    protected function updateCallRecordStatus(string $callId, array $statusData): void
    {
        $updateData = ['status' => $statusData['status'] ?? 'unknown'];

        if (isset($statusData['duration'])) {
            $updateData['duration'] = $statusData['duration'];
        }

        if (isset($statusData['end_time'])) {
            $updateData['end_time'] = $statusData['end_time'];
        }

        CallRecord::where('call_id', $callId)->update($updateData);
    }
}