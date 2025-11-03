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
    protected ?string $clientId;
    protected ?string $clientSecret;
    protected string $version;
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;
    protected ?string $accessToken = null;
    protected ?int $tokenExpiry = null;

    public function __construct(
        string $apiUrl,
        string $username,
        string $password,
        string $version = 'v17',
        ?string $clientId = null,
        ?string $clientSecret = null
    ) {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->version = $version;
        $this->timeout = config('voip.freepbx.timeout', 30);
        $this->retryAttempts = config('voip.freepbx.retry_attempts', 3);
        $this->retryDelay = config('voip.freepbx.retry_delay', 1000);
    }

    /**
     * Get OAuth2 access token
     */
    protected function getAccessToken(): string
    {
        // Check if we have a valid token
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        // If we have OAuth2 credentials, use them
        if ($this->clientId && $this->clientSecret) {
            return $this->getOAuth2Token();
        }

        // Fallback to basic auth (return empty string to indicate basic auth)
        return '';
    }

    /**
     * Get OAuth2 token using client credentials
     */
    protected function getOAuth2Token(): string
    {
        $tokenUrl = $this->apiUrl . '/admin/api/api/token';
        
        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $this->accessToken = $tokenData['access_token'];
                $this->tokenExpiry = time() + ($tokenData['expires_in'] ?? 3600) - 60; // 60 second buffer
                
                Log::info('FreePBX OAuth2 token obtained successfully');
                return $this->accessToken;
            }
        } catch (\Exception $e) {
            Log::warning('FreePBX OAuth2 token request failed, falling back to basic auth', [
                'error' => $e->getMessage()
            ]);
        }

        // If OAuth2 fails, return empty string to use basic auth
        return '';
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

        // Try OAuth2 first, then fall back to basic auth
        $token = $this->getAccessToken();
        
        $httpClient = Http::timeout($this->timeout)
            ->retry($this->retryAttempts, $this->retryDelay)
            ->withHeaders([
                'User-Agent' => 'FreePBX-VoIP-Platform/1.0',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]);

        if ($token) {
            // Use OAuth2 Bearer token
            $httpClient = $httpClient->withToken($token);
        } else {
            // Use Basic Authentication
            $httpClient = $httpClient->withBasicAuth($this->username, $this->password);
        }

        // For GraphQL requests, send as JSON body
        if (str_contains($url, '/gql') && !empty($data)) {
            $response = $httpClient->withBody(json_encode($data), 'application/json')->$method($url);
        } else {
            $response = $httpClient->$method($url, $data);
        }

        $this->handleResponse($response, $method, $endpoint);

        return $response;
    }

    /**
     * Build full API URL
     */
    protected function buildUrl(string $endpoint): string
    {
        // FreePBX uses /admin/api/api/ as the base path for REST API
        return $this->apiUrl . '/admin/api/api/' . ltrim($endpoint, '/');
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
        
        // Check for ajaxRequest declined error
        if (isset($responseData['error']) && $responseData['error'] === 'ajaxRequest declined') {
            Log::error('FreePBX AJAX Request Declined', [
                'method' => $method,
                'endpoint' => $endpoint,
                'response' => $responseData
            ]);

            throw new FreePBXApiException('FreePBX declined the AJAX request - this may be due to authentication or permission issues', 403, $responseData);
        }
        
        // Check for other FreePBX error responses
        if (isset($responseData['error'])) {
            $message = is_string($responseData['error']) ? $responseData['error'] : 'FreePBX API error';
            
            Log::error('FreePBX API Error Response', [
                'method' => $method,
                'endpoint' => $endpoint,
                'message' => $message,
                'response' => $responseData
            ]);

            throw new FreePBXApiException($message, 400, $responseData);
        }
        
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
            // Use GraphQL query that we know works
            // Make sure we send it as proper JSON
            $response = $this->makeRequest('post', 'gql', [
                'query' => '{ system { version } }'
            ]);
            
            $data = $response->json();
            return isset($data['data']['system']['version']);
        } catch (FreePBXApiException $e) {
            Log::error('FreePBX API Connection Test Failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('FreePBX API Connection Test Failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all extensions via GraphQL
     */
    public function getAllExtensions(): array
    {
        $response = $this->post('gql', [
            'query' => '{ fetchAllExtensions { extension { extensionId tech } } }'
        ]);

        if (isset($response['data']['fetchAllExtensions']['extension'])) {
            return $response['data']['fetchAllExtensions']['extension'];
        }

        return [];
    }

    /**
     * Get specific extension via GraphQL
     */
    public function getExtension(string $extensionId): ?array
    {
        try {
            $response = $this->post('gql', [
                'query' => '{ fetchExtension(extensionId: "' . $extensionId . '") { extensionId tech status } }'
            ]);

            if (isset($response['data']['fetchExtension'])) {
                return $response['data']['fetchExtension'];
            }

            return null;
        } catch (FreePBXApiException $e) {
            // Extension not found
            return null;
        }
    }

    /**
     * Create extension via GraphQL
     */
    public function createExtension(string $extensionId, string $name, string $email, array $options = []): array
    {
        $mutation = 'mutation {
            addExtension(input: {
                extensionId: "' . $extensionId . '"
                name: "' . $name . '"
                email: "' . $email . '"
                tech: "' . ($options['tech'] ?? 'pjsip') . '"
                vmEnable: ' . ($options['vmEnable'] ? 'true' : 'false') . '
                vmPassword: "' . ($options['vmPassword'] ?? '1234') . '"
            }) {
                status
                message
            }
        }';

        $response = $this->post('gql', [
            'query' => $mutation
        ]);

        if (isset($response['data']['addExtension'])) {
            return $response['data']['addExtension'];
        }

        throw new FreePBXApiException('Failed to create extension: Invalid response');
    }

    /**
     * Update extension via GraphQL
     */
    public function updateExtension(string $extensionId, array $updateData): array
    {
        // Build the mutation dynamically based on updateData
        $inputFields = [];
        
        if (isset($updateData['name'])) {
            $inputFields[] = 'name: "' . $updateData['name'] . '"';
        }
        if (isset($updateData['email'])) {
            $inputFields[] = 'email: "' . $updateData['email'] . '"';
        }
        if (isset($updateData['vmPassword'])) {
            $inputFields[] = 'vmPassword: "' . $updateData['vmPassword'] . '"';
        }

        $mutation = 'mutation {
            updateExtension(input: {
                extensionId: "' . $extensionId . '"
                ' . implode(' ', $inputFields) . '
            }) {
                status
                message
            }
        }';

        $response = $this->post('gql', [
            'query' => $mutation
        ]);

        if (isset($response['data']['updateExtension'])) {
            return $response['data']['updateExtension'];
        }

        throw new FreePBXApiException('Failed to update extension: Invalid response');
    }

    /**
     * Delete extension via GraphQL
     */
    public function deleteExtension(string $extensionId): array
    {
        $mutation = 'mutation {
            deleteExtension(input: {
                extensionId: "' . $extensionId . '"
            }) {
                status
                message
            }
        }';

        $response = $this->post('gql', [
            'query' => $mutation
        ]);

        if (isset($response['data']['deleteExtension'])) {
            return $response['data']['deleteExtension'];
        }

        throw new FreePBXApiException('Failed to delete extension: Invalid response');
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