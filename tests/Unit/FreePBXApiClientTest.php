<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FreePBX\FreePBXApiClient;
use App\Exceptions\FreePBXApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Mockery;

class FreePBXApiClientTest extends TestCase
{
    protected FreePBXApiClient $apiClient;
    protected string $apiUrl = 'https://test-freepbx.example.com';
    protected string $username = 'testuser';
    protected string $password = 'testpass';

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiClient = new FreePBXApiClient(
            $this->apiUrl,
            $this->username,
            $this->password
        );
    }

    public function test_constructor_sets_properties_correctly()
    {
        $client = new FreePBXApiClient(
            'https://example.com/',
            'user',
            'pass',
            'v18'
        );

        // Use reflection to access protected properties
        $reflection = new \ReflectionClass($client);
        
        $apiUrlProperty = $reflection->getProperty('apiUrl');
        $apiUrlProperty->setAccessible(true);
        $this->assertEquals('https://example.com', $apiUrlProperty->getValue($client));

        $usernameProperty = $reflection->getProperty('username');
        $usernameProperty->setAccessible(true);
        $this->assertEquals('user', $usernameProperty->getValue($client));

        $versionProperty = $reflection->getProperty('version');
        $versionProperty->setAccessible(true);
        $this->assertEquals('v18', $versionProperty->getValue($client));
    }

    public function test_build_url_method()
    {
        $reflection = new \ReflectionClass($this->apiClient);
        $buildUrlMethod = $reflection->getMethod('buildUrl');
        $buildUrlMethod->setAccessible(true);

        $result = $buildUrlMethod->invoke($this->apiClient, 'system/status');
        $expected = $this->apiUrl . '/admin/api/v17/system/status';
        
        $this->assertEquals($expected, $result);
    }

    public function test_build_url_with_leading_slash()
    {
        $reflection = new \ReflectionClass($this->apiClient);
        $buildUrlMethod = $reflection->getMethod('buildUrl');
        $buildUrlMethod->setAccessible(true);

        $result = $buildUrlMethod->invoke($this->apiClient, '/system/status');
        $expected = $this->apiUrl . '/admin/api/v17/system/status';
        
        $this->assertEquals($expected, $result);
    }

    public function test_successful_get_request()
    {
        $responseData = [
            'status' => true,
            'data' => ['version' => '17.0.0']
        ];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/version' => Http::response($responseData, 200)
        ]);

        $result = $this->apiClient->get('system/version');

        $this->assertEquals($responseData, $result);
        
        Http::assertSent(function ($request) {
            return $request->url() === $this->apiUrl . '/admin/api/v17/system/version' &&
                   $request->method() === 'GET' &&
                   $request->hasHeader('Authorization');
        });
    }

    public function test_get_request_with_parameters()
    {
        $responseData = ['status' => true, 'data' => []];
        $params = ['limit' => 10, 'offset' => 0];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/extensions*' => Http::response($responseData, 200)
        ]);

        $result = $this->apiClient->get('extensions', $params);

        $this->assertEquals($responseData, $result);
        
        Http::assertSent(function ($request) use ($params) {
            $query = parse_url($request->url(), PHP_URL_QUERY);
            parse_str($query, $queryParams);
            
            return str_contains($request->url(), '/admin/api/v17/extensions') &&
                   $queryParams['limit'] == $params['limit'] &&
                   $queryParams['offset'] == $params['offset'];
        });
    }

    public function test_successful_post_request()
    {
        $requestData = ['extension' => '1001', 'name' => 'Test User'];
        $responseData = ['status' => true, 'message' => 'Extension created'];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/extensions' => Http::response($responseData, 200)
        ]);

        $result = $this->apiClient->post('extensions', $requestData);

        $this->assertEquals($responseData, $result);
        
        Http::assertSent(function ($request) use ($requestData) {
            return $request->url() === $this->apiUrl . '/admin/api/v17/extensions' &&
                   $request->method() === 'POST' &&
                   $request->data() === $requestData;
        });
    }

    public function test_successful_put_request()
    {
        $requestData = ['name' => 'Updated User'];
        $responseData = ['status' => true, 'message' => 'Extension updated'];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/extensions/1001' => Http::response($responseData, 200)
        ]);

        $result = $this->apiClient->put('extensions/1001', $requestData);

        $this->assertEquals($responseData, $result);
        
        Http::assertSent(function ($request) use ($requestData) {
            return $request->url() === $this->apiUrl . '/admin/api/v17/extensions/1001' &&
                   $request->method() === 'PUT' &&
                   $request->data() === $requestData;
        });
    }

    public function test_successful_delete_request()
    {
        $responseData = ['status' => true, 'message' => 'Extension deleted'];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/extensions/1001' => Http::response($responseData, 200)
        ]);

        $result = $this->apiClient->delete('extensions/1001');

        $this->assertEquals($responseData, $result);
        
        Http::assertSent(function ($request) {
            return $request->url() === $this->apiUrl . '/admin/api/v17/extensions/1001' &&
                   $request->method() === 'DELETE';
        });
    }

    public function test_http_error_throws_exception()
    {
        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/status' => Http::response('Not Found', 404)
        ]);

        $this->expectException(FreePBXApiException::class);
        $this->expectExceptionMessage('FreePBX API request failed: 404');

        $this->apiClient->get('system/status');
    }

    public function test_freepbx_business_logic_error_throws_exception()
    {
        $errorResponse = [
            'status' => false,
            'message' => 'Extension already exists'
        ];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/extensions' => Http::response($errorResponse, 200)
        ]);

        $this->expectException(FreePBXApiException::class);
        $this->expectExceptionMessage('Extension already exists');

        $this->apiClient->post('extensions', ['extension' => '1001']);
    }

    public function test_test_connection_success()
    {
        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/status' => Http::response([
                'status' => true,
                'data' => ['uptime' => '1 day']
            ], 200)
        ]);

        $result = $this->apiClient->testConnection();

        $this->assertTrue($result);
    }

    public function test_test_connection_failure()
    {
        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/status' => Http::response('Server Error', 500)
        ]);

        $result = $this->apiClient->testConnection();

        $this->assertFalse($result);
    }

    public function test_test_connection_invalid_response()
    {
        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/status' => Http::response([
                'status' => false,
                'message' => 'System unavailable'
            ], 200)
        ]);

        $result = $this->apiClient->testConnection();

        $this->assertFalse($result);
    }

    public function test_get_version()
    {
        $versionData = [
            'status' => true,
            'data' => [
                'version' => '17.0.10',
                'build' => '2023.05.15'
            ]
        ];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/version' => Http::response($versionData, 200)
        ]);

        $result = $this->apiClient->getVersion();

        $this->assertEquals($versionData, $result);
    }

    public function test_get_system_status()
    {
        $statusData = [
            'status' => true,
            'data' => [
                'uptime' => '5 days, 3 hours',
                'load_average' => '0.25',
                'memory_usage' => '45%'
            ]
        ];

        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/status' => Http::response($statusData, 200)
        ]);

        $result = $this->apiClient->getSystemStatus();

        $this->assertEquals($statusData, $result);
    }

    public function test_handle_response_with_network_timeout()
    {
        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/status' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            }
        ]);

        $this->expectException(\Illuminate\Http\Client\ConnectionException::class);

        $this->apiClient->get('system/status');
    }

    public function test_authentication_headers_are_set()
    {
        Http::fake([
            $this->apiUrl . '/admin/api/v17/system/status' => Http::response(['status' => true], 200)
        ]);

        $this->apiClient->get('system/status');

        Http::assertSent(function ($request) {
            $authHeader = $request->header('Authorization')[0] ?? '';
            $expectedAuth = 'Basic ' . base64_encode($this->username . ':' . $this->password);
            
            return $authHeader === $expectedAuth;
        });
    }

    public function test_content_type_headers_are_set()
    {
        Http::fake([
            $this->apiUrl . '/admin/api/v17/extensions' => Http::response(['status' => true], 200)
        ]);

        $this->apiClient->post('extensions', ['extension' => '1001']);

        Http::assertSent(function ($request) {
            $acceptHeader = $request->header('Accept')[0] ?? '';
            $contentTypeHeader = $request->header('Content-Type')[0] ?? '';
            
            return str_contains($acceptHeader, 'application/json') &&
                   str_contains($contentTypeHeader, 'application/json');
        });
    }
}