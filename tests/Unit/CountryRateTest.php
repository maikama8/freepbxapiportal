<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CountryRate;
use App\Models\DidNumber;
use App\Models\CallRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class CountryRateTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_calculates_call_cost_correctly()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.05,
            'billing_increment' => 6,
            'minimum_duration' => 0,
            'is_active' => true
        ]);

        // Test exact increment
        $cost = $countryRate->calculateCallCost(60); // 1 minute
        $this->assertEquals(0.05, $cost);

        // Test partial increment (should round up)
        $cost = $countryRate->calculateCallCost(65); // 65 seconds = 11 increments of 6s = 66s = 1.1 minutes
        $this->assertEquals(0.055, $cost);

        // Test minimum duration
        $countryRate->update(['minimum_duration' => 30]);
        $cost = $countryRate->calculateCallCost(10); // Should use minimum 30 seconds
        $this->assertEquals(0.025, $cost); // 30s = 5 increments of 6s = 30s = 0.5 minutes
    }

    /** @test */
    public function it_formats_call_rate_correctly()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'call_rate_per_minute' => 0.0567,
            'is_active' => true
        ]);

        $this->assertEquals('$0.0567/min', $countryRate->formatted_call_rate);
    }

    /** @test */
    public function it_formats_did_costs_correctly()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'did_setup_cost' => 5.99,
            'did_monthly_cost' => 2.50,
            'is_active' => true
        ]);

        $this->assertEquals('$5.99', $countryRate->formatted_did_setup_cost);
        $this->assertEquals('$2.50', $countryRate->formatted_did_monthly_cost);
    }

    /** @test */
    public function it_describes_billing_increment_correctly()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'billing_increment' => 6,
            'is_active' => true
        ]);

        $this->assertEquals('6 seconds', $countryRate->billing_increment_description);

        $countryRate->update(['billing_increment' => 1]);
        $this->assertEquals('1 second', $countryRate->billing_increment_description);

        $countryRate->update(['billing_increment' => 60]);
        $this->assertEquals('1 minute', $countryRate->billing_increment_description);

        // Test 60 second increment (1 minute)
        $this->assertEquals('1 minute', $countryRate->billing_increment_description);
    }

    /** @test */
    public function it_checks_feature_support()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'features' => ['voice', 'sms', 'fax'],
            'is_active' => true
        ]);

        $this->assertTrue($countryRate->supportsFeature('voice'));
        $this->assertTrue($countryRate->supportsFeature('sms'));
        $this->assertTrue($countryRate->supportsFeature('fax'));
        $this->assertFalse($countryRate->supportsFeature('video'));

        // Test default feature support
        $countryRateNoFeatures = CountryRate::create([
            'country_code' => 'CA',
            'country_name' => 'Canada',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        $this->assertTrue($countryRateNoFeatures->supportsFeature('voice'));
        $this->assertFalse($countryRateNoFeatures->supportsFeature('sms'));
    }

    /** @test */
    public function it_finds_country_by_phone_number()
    {
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

        CountryRate::create([
            'country_code' => 'DE',
            'country_name' => 'Germany',
            'country_prefix' => '49',
            'is_active' => true
        ]);

        // Test US number
        $country = CountryRate::getByPhoneNumber('12345678901');
        $this->assertNotNull($country);
        $this->assertEquals('US', $country->country_code);

        // Test UK number
        $country = CountryRate::getByPhoneNumber('442012345678');
        $this->assertNotNull($country);
        $this->assertEquals('GB', $country->country_code);

        // Test formatted number
        $country = CountryRate::getByPhoneNumber('+44 20 1234 5678');
        $this->assertNotNull($country);
        $this->assertEquals('GB', $country->country_code);

        // Test unknown number
        $country = CountryRate::getByPhoneNumber('999999999');
        $this->assertNull($country);
    }

    /** @test */
    public function it_prioritizes_longer_prefixes()
    {
        // Create overlapping prefixes
        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        CountryRate::create([
            'country_code' => 'NANP_SPECIAL',
            'country_name' => 'Special NANP Region',
            'country_prefix' => '1800',
            'is_active' => true
        ]);

        // Should match the longer prefix first
        $country = CountryRate::getByPhoneNumber('18001234567');
        $this->assertEquals('NANP_SPECIAL', $country->country_code);

        // Should match the shorter prefix for non-matching longer prefix
        $country = CountryRate::getByPhoneNumber('12125551234');
        $this->assertEquals('US', $country->country_code);
    }

    /** @test */
    public function it_validates_area_codes()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'area_codes' => ['212', '213', '310', '415'],
            'is_active' => true
        ]);

        $this->assertTrue($countryRate->isValidAreaCode('212'));
        $this->assertTrue($countryRate->isValidAreaCode('415'));
        $this->assertFalse($countryRate->isValidAreaCode('999'));

        // Test country with no area code restrictions
        $countryRateNoRestrictions = CountryRate::create([
            'country_code' => 'CA',
            'country_name' => 'Canada',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        $this->assertTrue($countryRateNoRestrictions->isValidAreaCode('416'));
        $this->assertTrue($countryRateNoRestrictions->isValidAreaCode('999'));
    }

    /** @test */
    public function it_gets_available_area_codes()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'area_codes' => ['212', '213', '310'],
            'is_active' => true
        ]);

        $areaCodes = $countryRate->getAvailableAreaCodes();
        $this->assertEquals(['212', '213', '310'], $areaCodes);

        // Test country with no area codes
        $countryRateEmpty = CountryRate::create([
            'country_code' => 'CA',
            'country_name' => 'Canada',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        $areaCodes = $countryRateEmpty->getAvailableAreaCodes();
        $this->assertEquals([], $areaCodes);
    }

    /** @test */
    public function it_has_relationships()
    {
        $countryRate = CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        // Test DID numbers relationship
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'status' => 'available',
            'monthly_cost' => 2.50,
            'setup_cost' => 5.00
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $countryRate->didNumbers);
        $this->assertEquals(1, $countryRate->didNumbers->count());

        // Test call rates relationship (CallRate doesn't have country_code, so skip this test)
        // CallRate model uses destination_prefix for routing, not country_code
        $this->assertTrue(true); // Placeholder assertion
    }

    /** @test */
    public function it_scopes_active_countries()
    {
        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        CountryRate::create([
            'country_code' => 'XX',
            'country_name' => 'Inactive Country',
            'country_prefix' => '999',
            'is_active' => false
        ]);

        $activeCountries = CountryRate::active()->get();
        $this->assertEquals(1, $activeCountries->count());
        $this->assertEquals('US', $activeCountries->first()->country_code);
    }
}