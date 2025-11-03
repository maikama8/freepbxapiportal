<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\CountryRate;
use App\Models\DidNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DidBulkUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com'
        ]);
        
        // Create country rate
        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'did_setup_cost' => 5.00,
            'did_monthly_cost' => 2.99,
            'call_rate_per_minute' => 0.02,
            'billing_increment' => 60,
            'minimum_duration' => 60,
            'is_active' => true,
            'features' => ['voice', 'sms']
        ]);
    }

    public function test_admin_can_download_csv_template()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dids.template', ['country' => 'US']));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename="did_template_US_' . date('Y-m-d') . '.csv"');
    }

    public function test_admin_can_bulk_upload_dids()
    {
        Storage::fake('local');
        
        // Create CSV content
        $csvContent = "did_number,country_code,area_code,provider,monthly_cost,setup_cost,features,expires_at\n";
        $csvContent .= "15551234567,US,555,Test Provider,2.99,5.00,voice,\n";
        $csvContent .= "15551234568,US,555,Test Provider,2.99,5.00,\"voice,sms\",\n";
        
        $file = UploadedFile::fake()->createWithContent('test_dids.csv', $csvContent);
        
        $response = $this->actingAs($this->admin)
            ->post(route('admin.dids.bulk-upload'), [
                'csv_file' => $file,
                'country_code' => 'US'
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Verify DIDs were created
        $this->assertDatabaseHas('did_numbers', [
            'did_number' => '15551234567',
            'country_code' => 'US',
            'status' => 'available'
        ]);
        
        $this->assertDatabaseHas('did_numbers', [
            'did_number' => '15551234568',
            'country_code' => 'US',
            'status' => 'available'
        ]);
    }

    public function test_bulk_upload_validates_csv_format()
    {
        Storage::fake('local');
        
        // Create invalid CSV content (missing required columns)
        $csvContent = "invalid_column\n123456789\n";
        $file = UploadedFile::fake()->createWithContent('invalid.csv', $csvContent);
        
        $response = $this->actingAs($this->admin)
            ->post(route('admin.dids.bulk-upload'), [
                'csv_file' => $file,
                'country_code' => 'US'
            ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_admin_can_bulk_update_prices()
    {
        // Create test DIDs
        DidNumber::create([
            'did_number' => '15551234567',
            'country_code' => 'US',
            'monthly_cost' => 2.99,
            'setup_cost' => 5.00,
            'status' => 'available'
        ]);
        
        DidNumber::create([
            'did_number' => '15551234568',
            'country_code' => 'US',
            'monthly_cost' => 2.99,
            'setup_cost' => 5.00,
            'status' => 'available'
        ]);
        
        $response = $this->actingAs($this->admin)
            ->post(route('admin.dids.bulk-update-prices'), [
                'filter_country' => 'US',
                'filter_status' => 'available',
                'update_type' => 'increase',
                'update_amount' => 1.00,
                'update_monthly_cost' => '1'
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        
        // Verify prices were updated
        $this->assertDatabaseHas('did_numbers', [
            'did_number' => '15551234567',
            'monthly_cost' => 3.99 // 2.99 + 1.00
        ]);
        
        $this->assertDatabaseHas('did_numbers', [
            'did_number' => '15551234568',
            'monthly_cost' => 3.99 // 2.99 + 1.00
        ]);
    }

    public function test_non_admin_cannot_access_bulk_upload()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        
        $response = $this->actingAs($customer)
            ->get(route('admin.dids.template', ['country' => 'US']));

        $response->assertStatus(403);
    }
}