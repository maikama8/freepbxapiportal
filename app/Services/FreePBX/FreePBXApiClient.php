<?php

namespace App\Services\FreePBX;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\FreePBXApiException;

class FreePBXApiClient
{
    protected string $apiUrl;
    protected string $username;
    protected string $password;
    protected string $version;
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct(
        string $apiUrl,
        string $username,
        string $password,
        string $version = 'v17'
    ) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->version = $version;
        $this->timeout = config('voip.freepbx.timeout', 30);
        $this->retryAttempts = config('voip.freepbx.retry_attempts', 3);
        $this->retryDelay = config('voip.freepbx.retry_delay', 1000);
    }

    /**
     * Make authenticated HTTP request to FreePBX API
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->buildUrl($endpoint);
        
        Log::info('FreePBX API Request', [
            'method' => $method,
            'url' => $url,
            'data' => $data
        ]);

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout($this->timeout)
            ->retry($this->retryAttempts, $this->retryDelay)
            ->acceptJson()
            ->asJson()
            ->$method($url, $data);

        $this->handleResponse($response, $method, $endpoint);

        return $response;
    }

    /**
     * Build full API URL
     */
    protected function buildUrl(string $endpoint): string
    {
        return $this->apiUrl . '/admin/api/' . $this->version . '/' . ltrim($endpoint, '/');
    }

    /**
     * Handle API response and throw exceptions for errors
     */
    protected function handleResponse(Response $response, string $method, string $endpoint): void
    {
        if ($response->failed()) {
            $errorData = [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ];

            Log::error('FreePBX API Error', $errorData);

            throw new FreePBXApiException(
                "FreePBX API request failed: {$response->status()} - {$response->body()}",
                $response->status(),
                $errorData
            );
        }

        // Check for FreePBX-specific error responses
        $responseData = $response->json();
        if (isset($responseData['status']) && $responseData['status'] === false) {
            $message = $responseData['message'] ?? 'Unknown FreePBX API error';
            
            Log::error('FreePBX API Business Logic Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'message' => $message,
                'response' => $responseData
            ]);

            throw new FreePBXApiException($message, 400, $responseData);
        }
    }

    /**
     * GET request to FreePBX API
     */
    public function get(string $endpoint, array $params = []): array
    {
        $url = $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->makeRequest('get', $url);
        return $response->json();
    }

    /**
     * POST request to FreePBX API
     */
    public function post(string $endpoint, array $data = []): array
    {
        $response = $this->makeRequest('post', $endpoint, $data);
        return $response->json();
    }

    /**
     * PUT request to FreePBX API
     */
    public function put(string $endpoint, array $data = []): array
    {
        $response = $this->makeRequest('put', $endpoint, $data);
        return $response->json();
    }

    /**
     * DELETE request to FreePBX API
     */
    public function delete(string $endpoint): array
    {
        $response = $this->makeRequest('delete', $endpoint);
        return $response->json();
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->get('system/status');
            return isset($response['status']) && $response['status'] === true;
        } catch (FreePBXApiException $e) {
            Log::error('FreePBX API Connection Test Failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return false;
        }
    }

    /**
     * Get API version information
     */
    public function getVersion(): array
    {
        return $this->get('system/version');
    }

    /**
     * Get system status
     */
    public function getSystemStatus(): array
    {
        return $this->get('system/status');
    }
}