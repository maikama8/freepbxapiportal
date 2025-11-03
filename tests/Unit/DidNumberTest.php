<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\DidNumber;
use App\Models\User;
use App\Models\CountryRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class DidNumberTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_formats_us_numbers_correctly()
    {
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertEquals('+1 (212) 555-1234', $didNumber->formatted_number);
    }

    /** @test */
    public function it_formats_uk_numbers_correctly()
    {
        $didNumber = DidNumber::create([
            'did_number' => '442012345678',
            'country_code' => 'GB',
            'status' => 'available',
            'monthly_cost' => 3.50,
            'setup_cost' => 7.00
        ]);

        $this->assertEquals('+44 2012 345678', $didNumber->formatted_number);
    }

    /** @test */
    public function it_formats_international_numbers_as_default()
    {
        $didNumber = DidNumber::create([
            'did_number' => '4912345678',
            'country_code' => 'DE',
            'status' => 'available',
            'monthly_cost' => 4.00,
            'setup_cost' => 8.00
        ]);

        $this->assertEquals('+4912345678', $didNumber->formatted_number);
    }

    /** @test */
    public function it_checks_expiration_status()
    {
        // Expired DID
        $expiredDid = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'active',
            'expires_at' => now()->subDays(1),
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertTrue($expiredDid->isExpired());
        $this->assertFalse($expiredDid->isExpiringSoon());

        // Expiring soon DID
        $expiringSoonDid = DidNumber::create([
            'did_number' => '12125551235',
            'country_code' => 'US',
            'status' => 'active',
            'expires_at' => now()->addDays(15),
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertFalse($expiringSoonDid->isExpired());
        $this->assertTrue($expiringSoonDid->isExpiringSoon());

        // Valid DID
        $validDid = DidNumber::create([
            'did_number' => '12125551236',
            'country_code' => 'US',
            'status' => 'active',
            'expires_at' => now()->addDays(60),
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertFalse($validDid->isExpired());
        $this->assertFalse($validDid->isExpiringSoon());

        // No expiration date
        $noExpirationDid = DidNumber::create([
            'did_number' => '12125551237',
            'country_code' => 'US',
            'status' => 'active',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertFalse($noExpirationDid->isExpired());
        $this->assertFalse($noExpirationDid->isExpiringSoon());
    }

    /** @test */
    public function it_assigns_did_to_user()
    {
        $user = User::factory()->create();
        
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $result = $didNumber->assignToUser($user, '1001');

        $this->assertTrue($result);
        $didNumber->refresh();
        $this->assertEquals($user->id, $didNumber->user_id);
        $this->assertEquals('1001', $didNumber->assigned_extension);
        $this->assertEquals('active', $didNumber->status);
        $this->assertNotNull($didNumber->assigned_at);
    }

    /** @test */
    public function it_cannot_assign_non_available_did()
    {
        $user = User::factory()->create();
        
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'active', // Already assigned
            'user_id' => User::factory()->create()->id,
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $result = $didNumber->assignToUser($user, '1001');

        $this->assertFalse($result);
        $didNumber->refresh();
        $this->assertNotEquals($user->id, $didNumber->user_id);
    }

    /** @test */
    public function it_releases_did_from_user()
    {
        $user = User::factory()->create();
        
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'active',
            'user_id' => $user->id,
            'assigned_extension' => '1001',
            'assigned_at' => now(),
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $result = $didNumber->release();

        $this->assertTrue($result);
        $didNumber->refresh();
        $this->assertNull($didNumber->user_id);
        $this->assertNull($didNumber->assigned_extension);
        $this->assertNull($didNumber->assigned_at);
        $this->assertEquals('available', $didNumber->status);
    }

    /** @test */
    public function it_checks_features()
    {
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'features' => ['voice', 'sms', 'fax'],
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertTrue($didNumber->hasFeature('voice'));
        $this->assertTrue($didNumber->hasFeature('sms'));
        $this->assertTrue($didNumber->hasFeature('fax'));
        $this->assertFalse($didNumber->hasFeature('video'));

        // Test DID with no features
        $noFeaturesDid = DidNumber::create([
            'did_number' => '12125551235',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertFalse($noFeaturesDid->hasFeature('voice'));
    }

    /** @test */
    public function it_formats_costs_correctly()
    {
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 15.99
        ]);

        $this->assertEquals('$2.50', $didNumber->formatted_monthly_cost);
        $this->assertEquals('$15.99', $didNumber->formatted_setup_cost);
    }

    /** @test */
    public function it_has_user_relationship()
    {
        $user = User::factory()->create();
        
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'active',
            'user_id' => $user->id,
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertInstanceOf(User::class, $didNumber->user);
        $this->assertEquals($user->id, $didNumber->user->id);
    }

    /** @test */
    public function it_has_country_rate_relationship()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);
        
        $didNumber = DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertInstanceOf(CountryRate::class, $didNumber->countryRate);
        $this->assertEquals('US', $didNumber->countryRate->country_code);
    }

    /** @test */
    public function it_scopes_by_status()
    {
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        DidNumber::create([
            'did_number' => '12125551235',
            'country_code' => 'US',
            'status' => 'active',
            'user_id' => User::factory()->create()->id,
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $availableDids = DidNumber::available()->get();
        $this->assertEquals(1, $availableDids->count());
        $this->assertEquals('12125551234', $availableDids->first()->did_number);

        $activeDids = DidNumber::active()->get();
        $this->assertEquals(1, $activeDids->count());
        $this->assertEquals('12125551235', $activeDids->first()->did_number);
    }

    /** @test */
    public function it_scopes_by_country_and_area_code()
    {
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
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        DidNumber::create([
            'did_number' => '442012345678',
            'country_code' => 'GB',
            'area_code' => '20',
            'status' => 'available',
            'monthly_cost' => 3.50,
            'setup_cost' => 7.00
        ]);

        $usDids = DidNumber::byCountry('US')->get();
        $this->assertEquals(2, $usDids->count());

        $nyDids = DidNumber::byAreaCode('212')->get();
        $this->assertEquals(1, $nyDids->count());
        $this->assertEquals('12125551234', $nyDids->first()->did_number);

        $gbDids = DidNumber::byCountry('GB')->get();
        $this->assertEquals(1, $gbDids->count());
    }
}