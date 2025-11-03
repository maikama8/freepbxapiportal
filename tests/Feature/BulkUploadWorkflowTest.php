<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DidNumber;
use App\Models\CountryRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class BulkUploadWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function admin_can_download_csv_template_for_country()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'did_setup_cost' => 5.00,
            'did_monthly_cost' => 2.50,
            'is_active' => true
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/dids/template/US');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="did_template_US.csv"');

        $content = $response->getContent();
        $this->assertStringContainsString('did_number,area_code,provider,monthly_cost,setup_cost,features');
        $this->assertStringContainsString('12125551234,212,Provider Name,2.50,5.00,"voice,sms"');
    }

    /** @test */
    public function admin_can_bulk_upload_did_numbers()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'did_setup_cost' => 5.00,
            'did_monthly_cost' => 2.50,
            'is_active' => true
        ]);

        // Create CSV content
        $csvContent = "did_number,area_code,provider,monthly_cost,setup_cost,features\n";
        $csvContent .= "12125551234,212,Provider A,2.50,5.00,\"voice,sms\"\n";
        $csvContent .= "12125551235,212,Provider A,2.50,5.00,voice\n";
        $csvContent .= "13105551234,310,Provider B,3.00,6.00,\"voice,sms,fax\"\n";

        $file = UploadedFile::fake()->createWithContent('dids.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file,
                'overwrite_existing' => false
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify DIDs were created
        $this->assertEquals(3, DidNumber::count());

        $did1 = DidNumber::where('did_number', '12125551234')->first();
        $this->assertNotNull($did1);
        $this->assertEquals('US', $did1->country_code);
        $this->assertEquals('212', $did1->area_code);
        $this->assertEquals('Provider A', $did1->provider);
        $this->assertEquals(2.50, $did1->monthly_cost);
        $this->assertEquals(5.00, $did1->setup_cost);
        $this->assertEquals(['voice', 'sms'], $did1->features);
        $this->assertEquals('available', $did1->status);

        $did3 = DidNumber::where('did_number', '13105551234')->first();
        $this->assertEquals(['voice', 'sms', 'fax'], $did3->features);
    }

    /** @test */
    public function bulk_upload_validates_csv_format()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        // Create invalid CSV content (missing required columns)
        $csvContent = "did_number,area_code\n";
        $csvContent .= "12125551234,212\n";

        $file = UploadedFile::fake()->createWithContent('invalid.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Verify no DIDs were created
        $this->assertEquals(0, DidNumber::count());
    }

    /** @test */
    public function bulk_upload_handles_duplicate_numbers()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        // Create existing DID
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'available',
            'monthly_cost' => 2.00,
            'setup_cost' => 4.00
        ]);

        // Create CSV with duplicate number
        $csvContent = "did_number,area_code,provider,monthly_cost,setup_cost,features\n";
        $csvContent .= "12125551234,212,Provider A,2.50,5.00,voice\n";
        $csvContent .= "12125551235,212,Provider A,2.50,5.00,voice\n";

        $file = UploadedFile::fake()->createWithContent('dids.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file,
                'overwrite_existing' => false
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Should have 2 DIDs total (1 existing + 1 new)
        $this->assertEquals(2, DidNumber::count());

        // Existing DID should not be overwritten
        $existingDid = DidNumber::where('did_number', '12125551234')->first();
        $this->assertEquals(2.00, $existingDid->monthly_cost); // Original cost
        $this->assertEquals(4.00, $existingDid->setup_cost); // Original cost

        // New DID should be created
        $newDid = DidNumber::where('did_number', '12125551235')->first();
        $this->assertNotNull($newDid);
    }

    /** @test */
    public function bulk_upload_can_overwrite_existing_numbers()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        // Create existing DID
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'available',
            'monthly_cost' => 2.00,
            'setup_cost' => 4.00
        ]);

        // Create CSV with same number but different pricing
        $csvContent = "did_number,area_code,provider,monthly_cost,setup_cost,features\n";
        $csvContent .= "12125551234,212,Provider A,2.50,5.00,voice\n";

        $file = UploadedFile::fake()->createWithContent('dids.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file,
                'overwrite_existing' => true
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Should still have 1 DID
        $this->assertEquals(1, DidNumber::count());

        // DID should be updated with new values
        $updatedDid = DidNumber::where('did_number', '12125551234')->first();
        $this->assertEquals(2.50, $updatedDid->monthly_cost); // Updated cost
        $this->assertEquals(5.00, $updatedDid->setup_cost); // Updated cost
        $this->assertEquals('Provider A', $updatedDid->provider);
        $this->assertEquals(['voice'], $updatedDid->features);
    }

    /** @test */
    public function bulk_upload_validates_country_exists()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        $csvContent = "did_number,area_code,provider,monthly_cost,setup_cost,features\n";
        $csvContent .= "12125551234,212,Provider A,2.50,5.00,voice\n";

        $file = UploadedFile::fake()->createWithContent('dids.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'XX', // Non-existent country
                'csv_file' => $file
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors(['country_code']);

        // Verify no DIDs were created
        $this->assertEquals(0, DidNumber::count());
    }

    /** @test */
    public function admin_can_bulk_update_did_prices()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        // Create existing DIDs
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'available',
            'monthly_cost' => 2.00,
            'setup_cost' => 4.00
        ]);

        DidNumber::create([
            'did_number' => '12125551235',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'assigned',
            'user_id' => User::factory()->create()->id,
            'monthly_cost' => 2.00,
            'setup_cost' => 4.00
        ]);

        DidNumber::create([
            'did_number' => '13105551234',
            'country_code' => 'US',
            'area_code' => '310',
            'status' => 'available',
            'monthly_cost' => 3.00,
            'setup_cost' => 6.00
        ]);

        $response = $this->actingAs($admin)
            ->put('/admin/dids/bulk-update-prices', [
                'country_code' => 'US',
                'area_code' => '212',
                'status' => 'available',
                'monthly_cost' => 2.75,
                'setup_cost' => 5.50,
                'update_monthly_cost' => true,
                'update_setup_cost' => true
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check that only matching DIDs were updated
        $updated1 = DidNumber::where('did_number', '12125551234')->first();
        $this->assertEquals(2.75, $updated1->monthly_cost);
        $this->assertEquals(5.50, $updated1->setup_cost);

        // Assigned DID should not be updated (filtered out by status)
        $notUpdated = DidNumber::where('did_number', '12125551235')->first();
        $this->assertEquals(2.00, $notUpdated->monthly_cost);
        $this->assertEquals(4.00, $notUpdated->setup_cost);

        // Different area code should not be updated
        $differentArea = DidNumber::where('did_number', '13105551234')->first();
        $this->assertEquals(3.00, $differentArea->monthly_cost);
        $this->assertEquals(6.00, $differentArea->setup_cost);
    }

    /** @test */
    public function bulk_price_update_can_filter_by_multiple_criteria()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        // Create DIDs with different statuses and area codes
        DidNumber::create([
            'did_number' => '12125551234',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'available',
            'monthly_cost' => 2.00
        ]);

        DidNumber::create([
            'did_number' => '12125551235',
            'country_code' => 'US',
            'area_code' => '212',
            'status' => 'assigned',
            'user_id' => User::factory()->create()->id,
            'monthly_cost' => 2.00
        ]);

        DidNumber::create([
            'did_number' => '13105551234',
            'country_code' => 'US',
            'area_code' => '310',
            'status' => 'available',
            'monthly_cost' => 2.00
        ]);

        // Update only available DIDs in 212 area code
        $response = $this->actingAs($admin)
            ->put('/admin/dids/bulk-update-prices', [
                'country_code' => 'US',
                'area_code' => '212',
                'status' => 'available',
                'monthly_cost' => 3.00,
                'update_monthly_cost' => true
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Only the available 212 DID should be updated
        $this->assertEquals(3.00, DidNumber::where('did_number', '12125551234')->first()->monthly_cost);
        $this->assertEquals(2.00, DidNumber::where('did_number', '12125551235')->first()->monthly_cost);
        $this->assertEquals(2.00, DidNumber::where('did_number', '13105551234')->first()->monthly_cost);
    }

    /** @test */
    public function bulk_upload_shows_preview_in_dry_run_mode()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        $csvContent = "did_number,area_code,provider,monthly_cost,setup_cost,features\n";
        $csvContent .= "12125551234,212,Provider A,2.50,5.00,voice\n";
        $csvContent .= "12125551235,212,Provider A,2.50,5.00,voice\n";

        $file = UploadedFile::fake()->createWithContent('dids.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file,
                'preview_only' => true
            ]);

        $response->assertStatus(200);
        $response->assertSee('Upload Preview');
        $response->assertSee('12125551234');
        $response->assertSee('12125551235');
        $response->assertSee('Provider A');
        $response->assertSee('2 DIDs will be created');

        // Verify no DIDs were actually created
        $this->assertEquals(0, DidNumber::count());
    }

    /** @test */
    public function bulk_upload_handles_large_files_in_batches()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'is_active' => true
        ]);

        // Create large CSV content (100 DIDs)
        $csvContent = "did_number,area_code,provider,monthly_cost,setup_cost,features\n";
        for ($i = 1; $i <= 100; $i++) {
            $number = '1212555' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $csvContent .= "{$number},212,Provider A,2.50,5.00,voice\n";
        }

        $file = UploadedFile::fake()->createWithContent('large_dids.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file,
                'batch_size' => 25
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify all DIDs were created
        $this->assertEquals(100, DidNumber::count());

        // Verify some random DIDs
        $this->assertNotNull(DidNumber::where('did_number', '12125550001')->first());
        $this->assertNotNull(DidNumber::where('did_number', '12125550050')->first());
        $this->assertNotNull(DidNumber::where('did_number', '12125550100')->first());
    }

    /** @test */
    public function complete_bulk_upload_workflow()
    {
        Storage::fake('local');
        
        $admin = User::factory()->create(['role' => 'admin']);

        // Step 1: Create country rate
        CountryRate::create([
            'country_code' => 'US',
            'country_name' => 'United States',
            'country_prefix' => '1',
            'did_setup_cost' => 5.00,
            'did_monthly_cost' => 2.50,
            'is_active' => true
        ]);

        // Step 2: Download template
        $templateResponse = $this->actingAs($admin)
            ->get('/admin/dids/template/US');

        $templateResponse->assertStatus(200);
        $this->assertStringContainsString('did_number,area_code', $templateResponse->getContent());

        // Step 3: Upload DIDs
        $csvContent = "did_number,area_code,provider,monthly_cost,setup_cost,features\n";
        $csvContent .= "12125551234,212,Provider A,2.50,5.00,\"voice,sms\"\n";
        $csvContent .= "12125551235,212,Provider A,2.50,5.00,voice\n";
        $csvContent .= "13105551234,310,Provider B,3.00,6.00,\"voice,sms,fax\"\n";

        $file = UploadedFile::fake()->createWithContent('dids.csv', $csvContent);

        $uploadResponse = $this->actingAs($admin)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file
            ]);

        $uploadResponse->assertRedirect();
        $uploadResponse->assertSessionHas('success');

        // Step 4: Verify upload results
        $this->assertEquals(3, DidNumber::count());

        // Step 5: Bulk update prices for specific area code
        $updateResponse = $this->actingAs($admin)
            ->put('/admin/dids/bulk-update-prices', [
                'country_code' => 'US',
                'area_code' => '212',
                'status' => 'available',
                'monthly_cost' => 2.75,
                'update_monthly_cost' => true
            ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');

        // Step 6: Verify price updates
        $updatedDids = DidNumber::where('area_code', '212')->get();
        foreach ($updatedDids as $did) {
            $this->assertEquals(2.75, $did->monthly_cost);
        }

        // 310 area code should remain unchanged
        $unchangedDid = DidNumber::where('area_code', '310')->first();
        $this->assertEquals(3.00, $unchangedDid->monthly_cost);

        // Step 7: View DID inventory
        $inventoryResponse = $this->actingAs($admin)
            ->get('/admin/dids');

        $inventoryResponse->assertStatus(200);
        $inventoryResponse->assertSee('12125551234');
        $inventoryResponse->assertSee('13105551234');
        $inventoryResponse->assertSee('Provider A');
        $inventoryResponse->assertSee('Provider B');
        $inventoryResponse->assertSee('$2.75');
        $inventoryResponse->assertSee('$3.00');
    }

    /** @test */
    public function non_admin_cannot_access_bulk_upload_features()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        // Try to download template
        $response = $this->actingAs($customer)
            ->get('/admin/dids/template/US');

        $response->assertStatus(403);

        // Try to bulk upload
        $file = UploadedFile::fake()->create('dids.csv');
        
        $response = $this->actingAs($customer)
            ->post('/admin/dids/bulk-upload', [
                'country_code' => 'US',
                'csv_file' => $file
            ]);

        $response->assertStatus(403);

        // Try to bulk update prices
        $response = $this->actingAs($customer)
            ->put('/admin/dids/bulk-update-prices', [
                'country_code' => 'US',
                'monthly_cost' => 3.00
            ]);

        $response->assertStatus(403);
    }
}