<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FreePBX\CallManagementService;
use App\Services\FreePBX\FreePBXApiClient;
use App\Models\User;
use App\Models\CallRecord;
use App\Exceptions\FreePBXApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class CallManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CallManagementService $callManagementService;
    protected $apiClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->apiClient = Mockery::mock(FreePBXApiClient::class);
        $this->callManagementService = new CallManagementService($this->apiClient);
    }

    public function test_initiate_call_success_with_existing_extension()
    {
        $user = User::factory()->create([
            'extension' => '1001',
            'phone' => '+1234567890'
        ]);

        $destination = '+19876543210';
        $expectedCallData = [
            'extension' => '1001',
            'destination' => '+19876543210',
            'caller_id' => '+1234567890',
            'call_id' => Mockery::pattern('/^call_\d+_[a-zA-Z0-9]{8}$/'),
            'timeout' => 3600
        ];

        $apiResponse = [
            'status' => true,
            'message' => 'Call initiated',
            'call_id' => 'freepbx_call_123'
        ];

        $this->apiClient
            ->shouldReceive('post')
            ->once()
            ->with('calls/originate', Mockery::on(function($data) use ($expectedCallData) {
                return $data['extension'] === $expectedCallData['extension'] &&
                       $data['destination'] === $expectedCallData['destination'] &&
                       $data['caller_id'] === $expectedCallData['caller_id'] &&
                       preg_match('/^call_\d+_[a-zA-Z0-9]{8}$/', $data['call_id']) &&
                       $data['timeout'] === $expectedCallData['timeout'];
            }))
            ->andReturn($apiResponse);

        $result = $this->callManagementService->initiateCall($user, $destination);

        $this->assertTrue($result['success']);
        $this->assertEquals('initiated', $result['status']);
        $this->assertEquals('Call initiated successfully', $result['message']);
        $this->assertArrayHasKey('call_id', $result);
        $this->assertArrayHasKey('call_record_id', $result);

        // Verify call record was created
        $callRecord = CallRecord::find($result['call_record_id']);
        $this->assertNotNull($callRecord);
        $this->assertEquals($user->id, $callRecord->user_id);
        $this->assertEquals($destination, $callRecord->destination);
        $this->assertEquals('initiated', $callRecord->status);
    }

    public function test_initiate_call_creates_extension_if_not_exists()
    {
        $user = User::factory()->create([
            'extension' => null,
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $destination = '+19876543210';

        // Mock extension creation
        $extensionResponse = [
            'status' => true,
            'message' => 'Extension created'
        ];

        $callResponse = [
            'status' => true,
            'message' => 'Call initiated'
        ];

        $this->apiClient
            ->shouldReceive('post')
            ->once()
            ->with('extensions', Mockery::on(function($data) {
                return $data['extension'] === '1000' &&
                       $data['name'] === 'Test User' &&
                       $data['email'] === 'test@example.com' &&
                       isset($data['secret']);
            }))
            ->andReturn($extensionResponse);

        $this->apiClient
            ->shouldReceive('post')
            ->once()
            ->with('calls/originate', Mockery::any())
            ->andReturn($callResponse);

        $result = $this->callManagementService->initiateCall($user, $destination);

        $this->assertTrue($result['success']);
        
        // Verify user extension was updated
        $user->refresh();
        $this->assertEquals('1000', $user->extension);
    }

    public function test_initiate_call_handles_api_exception()
    {
        $user = User::factory()->create(['extension' => '1001']);
        $destination = '+19876543210';

        $this->apiClient
            ->shouldReceive('post')
            ->once()
            ->with('calls/originate', Mockery::any())
            ->andThrow(new FreePBXApiException('API Error'));

        $this->expectException(FreePBXApiException::class);
        $this->expectExceptionMessage('API Error');

        $this->callManagementService->initiateCall($user, $destination);
    }

    public function test_terminate_call_success()
    {
        $callId = 'call_123456_abcd1234';
        
        // Create existing call record
        $user = User::factory()->create();
        CallRecord::create([
            'user_id' => $user->id,
            'call_id' => $callId,
            'caller_id' => '1001',
            'destination' => '+19876543210',
            'start_time' => now()->subMinutes(5),
            'status' => 'active'
        ]);

        $apiResponse = [
            'status' => true,
            'message' => 'Call terminated'
        ];

        $this->apiClient
            ->shouldReceive('post')
            ->once()
            ->with("calls/{$callId}/hangup")
            ->andReturn($apiResponse);

        $result = $this->callManagementService->terminateCall($callId);

        $this->assertTrue($result['success']);
        $this->assertEquals($callId, $result['call_id']);
        $this->assertEquals('terminated', $result['status']);

        // Verify call record was updated
        $callRecord = CallRecord::where('call_id', $callId)->first();
        $this->assertEquals('completed', $callRecord->status);
        $this->assertNotNull($callRecord->end_time);
    }

    public function test_terminate_call_handles_api_exception()
    {
        $callId = 'call_123456_abcd1234';

        $this->apiClient
            ->shouldReceive('post')
            ->once()
            ->with("calls/{$callId}/hangup")
            ->andThrow(new FreePBXApiException('Call not found'));

        $this->expectException(FreePBXApiException::class);
        $this->expectExceptionMessage('Call not found');

        $this->callManagementService->terminateCall($callId);
    }

    public function test_get_call_status_success()
    {
        $callId = 'call_123456_abcd1234';
        $user = User::factory()->create();
        
        CallRecord::create([
            'user_id' => $user->id,
            'call_id' => $callId,
            'caller_id' => '1001',
            'destination' => '+19876543210',
            'start_time' => now()->subMinutes(2),
            'status' => 'active'
        ]);

        $statusResponse = [
            'status' => 'active',
            'duration' => 120,
            'caller_id' => '1001',
            'destination' => '+19876543210'
        ];

        $this->apiClient
            ->shouldReceive('get')
            ->once()
            ->with("calls/{$callId}/status")
            ->andReturn($statusResponse);

        $result = $this->callManagementService->getCallStatus($callId);

        $this->assertEquals($statusResponse, $result);

        // Verify call record was updated
        $callRecord = CallRecord::where('call_id', $callId)->first();
        $this->assertEquals('active', $callRecord->status);
        $this->assertEquals(120, $callRecord->duration);
    }

    public function test_get_active_calls_without_user_filter()
    {
        $activeCallsResponse = [
            'status' => true,
            'data' => [
                [
                    'call_id' => 'call_1',
                    'extension' => '1001',
                    'destination' => '+19876543210',
                    'duration' => 60
                ],
                [
                    'call_id' => 'call_2',
                    'extension' => '1002',
                    'destination' => '+19876543211',
                    'duration' => 120
                ]
            ]
        ];

        $this->apiClient
            ->shouldReceive('get')
            ->once()
            ->with('calls/active', [])
            ->andReturn($activeCallsResponse);

        $result = $this->callManagementService->getActiveCalls();

        $this->assertEquals($activeCallsResponse, $result);
    }

    public function test_get_active_calls_with_user_filter()
    {
        $user = User::factory()->create(['extension' => '1001']);
        
        $activeCallsResponse = [
            'status' => true,
            'data' => [
                [
                    'call_id' => 'call_1',
                    'extension' => '1001',
                    'destination' => '+19876543210',
                    'duration' => 60
                ]
            ]
        ];

        $this->apiClient
            ->shouldReceive('get')
            ->once()
            ->with('calls/active', ['extension' => '1001'])
            ->andReturn($activeCallsResponse);

        $result = $this->callManagementService->getActiveCalls($user);

        $this->assertEquals($activeCallsResponse, $result);
    }

    public function test_get_or_create_user_extension_existing()
    {
        $user = User::factory()->create(['extension' => '1001']);

        $result = $this->callManagementService->getOrCreateUserExtension($user);

        $this->assertEquals('1001', $result);
    }

    public function test_get_or_create_user_extension_new()
    {
        $user = User::factory()->create([
            'extension' => null,
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $extensionResponse = [
            'status' => true,
            'message' => 'Extension created'
        ];

        $this->apiClient
            ->shouldReceive('post')
            ->once()
            ->with('extensions', Mockery::on(function($data) {
                return $data['extension'] === '1000' &&
                       $data['name'] === 'Test User' &&
                       $data['email'] === 'test@example.com';
            }))
            ->andReturn($extensionResponse);

        $result = $this->callManagementService->getOrCreateUserExtension($user);

        $this->assertEquals('1000', $result);
        
        $user->refresh();
        $this->assertEquals('1000', $user->extension);
    }

    public function test_update_extension_success()
    {
        $user = User::factory()->create(['extension' => '1001']);
        $config = ['name' => 'Updated Name', 'codec' => 'g729'];

        $updateResponse = [
            'status' => true,
            'message' => 'Extension updated'
        ];

        $this->apiClient
            ->shouldReceive('put')
            ->once()
            ->with('extensions/1001', $config)
            ->andReturn($updateResponse);

        $result = $this->callManagementService->updateExtension($user, $config);

        $this->assertEquals($updateResponse, $result);
    }

    public function test_update_extension_no_extension()
    {
        $user = User::factory()->create(['extension' => null]);
        $config = ['name' => 'Updated Name'];

        $this->expectException(FreePBXApiException::class);
        $this->expectExceptionMessage('User does not have an extension');

        $this->callManagementService->updateExtension($user, $config);
    }

    public function test_delete_extension_success()
    {
        $user = User::factory()->create(['extension' => '1001']);

        $deleteResponse = [
            'status' => true,
            'message' => 'Extension deleted'
        ];

        $this->apiClient
            ->shouldReceive('delete')
            ->once()
            ->with('extensions/1001')
            ->andReturn($deleteResponse);

        $result = $this->callManagementService->deleteExtension($user);

        $this->assertTrue($result);
        
        $user->refresh();
        $this->assertNull($user->extension);
    }

    public function test_delete_extension_no_extension()
    {
        $user = User::factory()->create(['extension' => null]);

        $result = $this->callManagementService->deleteExtension($user);

        $this->assertTrue($result);
    }

    public function test_delete_extension_api_failure()
    {
        $user = User::factory()->create(['extension' => '1001']);

        $this->apiClient
            ->shouldReceive('delete')
            ->once()
            ->with('extensions/1001')
            ->andThrow(new FreePBXApiException('Delete failed'));

        $result = $this->callManagementService->deleteExtension($user);

        $this->assertFalse($result);
    }

    public function test_format_destination_removes_non_numeric()
    {
        $reflection = new \ReflectionClass($this->callManagementService);
        $formatMethod = $reflection->getMethod('formatDestination');
        $formatMethod->setAccessible(true);

        $result = $formatMethod->invoke($this->callManagementService, '+1 (234) 567-8901');
        $this->assertEquals('+12345678901', $result);
    }

    public function test_format_destination_adds_plus_for_international()
    {
        $reflection = new \ReflectionClass($this->callManagementService);
        $formatMethod = $reflection->getMethod('formatDestination');
        $formatMethod->setAccessible(true);

        $result = $formatMethod->invoke($this->callManagementService, '12345678901234');
        $this->assertEquals('+12345678901234', $result);
    }

    public function test_generate_extension_number_first_extension()
    {
        $reflection = new \ReflectionClass($this->callManagementService);
        $generateMethod = $reflection->getMethod('generateExtensionNumber');
        $generateMethod->setAccessible(true);

        $result = $generateMethod->invoke($this->callManagementService);
        $this->assertEquals('1000', $result);
    }

    public function test_generate_extension_number_incremental()
    {
        User::factory()->create(['extension' => '1005']);
        User::factory()->create(['extension' => '1003']);

        $reflection = new \ReflectionClass($this->callManagementService);
        $generateMethod = $reflection->getMethod('generateExtensionNumber');
        $generateMethod->setAccessible(true);

        $result = $generateMethod->invoke($this->callManagementService);
        $this->assertEquals('1006', $result);
    }

    public function test_generate_call_id_format()
    {
        $reflection = new \ReflectionClass($this->callManagementService);
        $generateMethod = $reflection->getMethod('generateCallId');
        $generateMethod->setAccessible(true);

        $result = $generateMethod->invoke($this->callManagementService);
        
        $this->assertMatchesRegularExpression('/^call_\d+_[a-zA-Z0-9]{8}$/', $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}