<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DidNumber;
use App\Models\CountryRate;
use App\Models\BalanceTransaction;
use App\Services\BalanceService;
use App\Services\FreePBX\ExtensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Mockery;

class DidPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $mockExtensionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockExtensionService = Mockery::mock(ExtensionService::class);
        $this->app->instance(ExtensionService::class, $this->mockExtensionService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function customer_can_browse_available_dids()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 100.00
        ]);

        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'did_setup_cost' => 5.00,
            'did_monthly_cost' => 2.50,
            'is_active' => true
        ]);

        // Create available DIDs
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        DidNumber::create([
            'did_number' => '13105551234',
            'country_code' => 'US',
            'area_code' => '310',
            'status' => 'available',
            'monthly_cost' => 3.00,
            'setup_cost' => 6.00
        ]);

        $response = $this->actingAs($user)
            ->get('/customer/dids/browse');

        $response->assertStatus(200);
        $response->assertSee('12125551234');
        $response->assertSee('13105551234');
        $response->assertSee('United States');
        $response->assertSee('$2.50');
        $response->assertSee('$5.00');
    }

    /** @test */
    public function customer_can_filter_dids_by_country_and_area_code()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 100.00
        ]);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        CountryRate::create([
            'country_code' => 'GB',
            'country_name' => 'United Kingdom',
            'country_prefix' => '44',
            'is_active' => true
        ]);

        // Create DIDs in different countries and area codes
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        DidNumber::create([
            'did_number' => '442012345678',
            'country_code' => 'GB',
            'area_code' => '20',
            'status' => 'available',
            'monthly_cost' => 4.00,
            'setup_cost' => 8.00
        ]);

        // Filter by country
        $response = $this->actingAs($user)
            ->get('/customer/dids/browse?country=US');

        $response->assertStatus(200);
        $response->assertSee('12125551234');
        $response->assertDontSee('442012345678');

        // Filter by area code
        $response = $this->actingAs($user)
            ->get('/customer/dids/browse?area_code=212');

        $response->assertStatus(200);
        $response->assertSee('12125551234');
        $response->assertDontSee('442012345678');
    }

    /** @test */
    public function customer_can_purchase_did_with_sufficient_balance()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 100.00
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->mockExtensionService
            ->shouldReceive('assignDidToExtension')
            ->once()
            ->with('12125551234', $user->sip_username ?? null)
            ->andReturn(true);

        $response = $this->actingAs($user)
            ->post("/customer/dids/{$didNumber->id}/purchase", [
                'extension' => '1001'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify DID assignment
        $didNumber->refresh();
        $this->assertEquals($user->id, $didNumber->user_id);
        $this->assertEquals('assigned', $didNumber->status);
        $this->assertEquals('1001', $didNumber->assigned_extension);
        $this->assertNotNull($didNumber->assigned_at);

        // Verify balance deduction
        $user->refresh();
        $this->assertEquals(95.00, $user->balance); // 100 - 5 setup cost

        // Verify transaction record
        $transaction = BalanceTransaction::where('user_id', $user->id)
            ->where('type', 'did_setup_charge')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals(-5.00, $transaction->amount);
    }

    /** @test */
    public function customer_cannot_purchase_did_with_insufficient_balance()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 3.00 // Less than setup cost
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $response = $this->actingAs($user)
            ->post("/customer/dids/{$didNumber->id}/purchase", [
                'extension' => '1001'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify DID was not assigned
        $didNumber->refresh();
        $this->assertNull($didNumber->user_id);
        $this->assertEquals('available', $didNumber->status);

        // Verify balance unchanged
        $user->refresh();
        $this->assertEquals(3.00, $user->balance);
    }

    /** @test */
    public function customer_cannot_purchase_already_assigned_did()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 100.00
        ]);

        $otherUser = User::factory()->create();

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'assigned',
            'user_id' => $otherUser->id,
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $response = $this->actingAs($user)
            ->post("/customer/dids/{$didNumber->id}/purchase", [
                'extension' => '1001'
            ]);

        $response->assertStatus(404); // Should not be able to access assigned DID

        // Verify DID ownership unchanged
        $didNumber->refresh();
        $this->assertEquals($otherUser->id, $didNumber->user_id);
    }

    /** @test */
    public function customer_can_view_assigned_dids()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid'
        ]);

        $assignedDid = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'assigned',
            'user_id' => $user->id,
            'assigned_extension' => '1001',
            'assigned_at' => now(),
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $otherUserDid = DidNumber::create([
            'did_number' => '13105551234',
            'country_code' => 'US',
            'status' => 'assigned',
            'user_id' => User::factory()->create()->id,
            'monthly_cost' => 3.00,
            'setup_cost' => 6.00
        ]);

        $response = $this->actingAs($user)
            ->get('/customer/dids');

        $response->assertStatus(200);
        $response->assertSee('12125551234');
        $response->assertSee('1001'); // Extension
        $response->assertDontSee('13105551234'); // Other user's DID
    }

    /** @test */
    public function customer_can_configure_did_settings()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid'
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'assigned',
            'user_id' => $user->id,
            'assigned_extension' => '1001',
            'monthly_cost' => 2.50
        ]);

        $this->mockExtensionService
            ->shouldReceive('updateDidConfiguration')
            ->once()
            ->with('12125551234', [
                'forward_to' => '1002',
                'voicemail_enabled' => true,
                'call_recording' => false
            ])
            ->andReturn(true);

        $response = $this->actingAs($user)
            ->put("/customer/dids/{$didNumber->id}/configure", [
                'forward_to' => '1002',
                'voicemail_enabled' => true,
                'call_recording' => false
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify configuration was saved
        $didNumber->refresh();
        $metadata = $didNumber->metadata;
        $this->assertEquals('1002', $metadata['forward_to']);
        $this->assertTrue($metadata['voicemail_enabled']);
        $this->assertFalse($metadata['call_recording']);
    }

    /** @test */
    public function customer_can_release_did()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid'
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'assigned',
            'user_id' => $user->id,
            'assigned_extension' => '1001',
            'assigned_at' => now(),
            'monthly_cost' => 2.50
        ]);

        $this->mockExtensionService
            ->shouldReceive('removeDidFromExtension')
            ->once()
            ->with('12125551234', '1001')
            ->andReturn(true);

        $response = $this->actingAs($user)
            ->delete("/customer/dids/{$didNumber->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify DID was released
        $didNumber->refresh();
        $this->assertNull($didNumber->user_id);
        $this->assertEquals('available', $didNumber->status);
        $this->assertNull($didNumber->assigned_extension);
        $this->assertNull($didNumber->assigned_at);
    }

    /** @test */
    public function customer_can_view_did_billing_history()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid'
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'assigned',
            'user_id' => $user->id,
            'monthly_cost' => 2.50,
            'billing_history' => json_encode([
                [
                    'month' => '2024-01',
                    'amount' => 2.50,
                    'status' => 'charged',
                    'processed_at' => now()->subMonth()->toISOString()
                ],
                [
                    'month' => '2024-02',
                    'amount' => 2.50,
                    'status' => 'charged',
                    'processed_at' => now()->toISOString()
                ]
            ])
        ]);

        $response = $this->actingAs($user)
            ->get("/customer/dids/{$didNumber->id}");

        $response->assertStatus(200);
        $response->assertSee('2024-01');
        $response->assertSee('2024-02');
        $response->assertSee('$2.50');
        $response->assertSee('charged');
    }

    /** @test */
    public function complete_did_purchase_and_monthly_billing_workflow()
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'account_type' => 'prepaid',
            'balance' => 100.00
        ]);

        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->mockExtensionService
            ->shouldReceive('assignDidToExtension')
            ->once()
            ->andReturn(true);

        // Step 1: Purchase DID
        $response = $this->actingAs($user)
            ->post("/customer/dids/{$didNumber->id}/purchase", [
                'extension' => '1001'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify initial setup
        $didNumber->refresh();
        $user->refresh();
        
        $this->assertEquals($user->id, $didNumber->user_id);
        $this->assertEquals('assigned', $didNumber->status);
        $this->assertEquals(95.00, $user->balance); // Setup cost deducted

        // Step 2: Simulate monthly billing
        $balanceService = app(BalanceService::class);
        
        $success = $balanceService->deductBalance(
            $user,
            $didNumber->monthly_cost,
            'did_monthly_charge',
            "Monthly DID charge for {$didNumber->did_number}",
            [
                'did_number_id' => $didNumber->id,
                'billing_month' => now()->format('Y-m')
            ]
        );

        $this->assertTrue($success);

        // Update billing history
        $billingHistory = [
            [
                'month' => now()->format('Y-m'),
                'amount' => $didNumber->monthly_cost,
                'status' => 'charged',
                'processed_at' => now()->toISOString()
            ]
        ];

        $didNumber->update([
            'billing_history' => json_encode($billingHistory),
            'last_billed_at' => now()
        ]);

        // Verify monthly billing
        $user->refresh();
        $didNumber->refresh();
        
        $this->assertEquals(92.50, $user->balance); // Monthly cost deducted
        $this->assertNotNull($didNumber->billing_history);
        $this->assertNotNull($didNumber->last_billed_at);

        // Verify transaction records
        $transactions = BalanceTransaction::where('user_id', $user->id)->get();
        $this->assertCount(2, $transactions); // Setup + monthly charge

        $setupTransaction = $transactions->where('type', 'did_setup_charge')->first();
        $monthlyTransaction = $transactions->where('type', 'did_monthly_charge')->first();

        $this->assertNotNull($setupTransaction);
        $this->assertNotNull($monthlyTransaction);
        $this->assertEquals(-5.00, $setupTransaction->amount);
        $this->assertEquals(-2.50, $monthlyTransaction->amount);
    }

    /** @test */
    public function customer_cannot_access_other_users_dids()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $otherUser = User::factory()->create(['role' => 'customer']);

        $otherUserDid = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'assigned',
            'user_id' => $otherUser->id,
            'monthly_cost' => 2.50
        ]);

        // Try to view other user's DID
        $response = $this->actingAs($user)
            ->get("/customer/dids/{$otherUserDid->id}");

        $response->assertStatus(404);

        // Try to configure other user's DID
        $response = $this->actingAs($user)
            ->put("/customer/dids/{$otherUserDid->id}/configure", [
                'forward_to' => '1002'
            ]);

        $response->assertStatus(404);

        // Try to release other user's DID
        $response = $this->actingAs($user)
            ->delete("/customer/dids/{$otherUserDid->id}");

        $response->assertStatus(404);
    }
}