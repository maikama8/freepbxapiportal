<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DidNumber;

class DidNumbersSeeder extends Seeder
{
    public function run()
    {
        $didNumbers = [
            // US Numbers
            ['did_number' => '15551234567', 'country_code' => 'US', 'area_code' => '555', 'monthly_cost' => 2.99, 'setup_cost' => 5.00],
            ['did_number' => '15551234568', 'country_code' => 'US', 'area_code' => '555', 'monthly_cost' => 2.99, 'setup_cost' => 5.00],
            ['did_number' => '12125551234', 'country_code' => 'US', 'area_code' => '212', 'monthly_cost' => 4.99, 'setup_cost' => 10.00],
            ['did_number' => '13105551234', 'country_code' => 'US', 'area_code' => '310', 'monthly_cost' => 3.99, 'setup_cost' => 7.50],
            
            // UK Numbers
            ['did_number' => '442071234567', 'country_code' => 'GB', 'area_code' => '207', 'monthly_cost' => 3.50, 'setup_cost' => 8.00],
            ['did_number' => '441612345678', 'country_code' => 'GB', 'area_code' => '161', 'monthly_cost' => 2.75, 'setup_cost' => 6.00],
            
            // German Numbers
            ['did_number' => '493012345678', 'country_code' => 'DE', 'area_code' => '30', 'monthly_cost' => 4.25, 'setup_cost' => 9.00],
            ['did_number' => '498912345678', 'country_code' => 'DE', 'area_code' => '89', 'monthly_cost' => 3.75, 'setup_cost' => 7.00],
            
            // Swedish Numbers
            ['did_number' => '46812345678', 'country_code' => 'SE', 'area_code' => '8', 'monthly_cost' => 5.99, 'setup_cost' => 12.00],
            ['did_number' => '46312345678', 'country_code' => 'SE', 'area_code' => '31', 'monthly_cost' => 4.99, 'setup_cost' => 10.00],
        ];

        foreach ($didNumbers as $didData) {
            DidNumber::create(array_merge($didData, [
                'provider' => 'VoIP Provider Inc.',
                'status' => 'available',
                'features' => ['voice', 'sms'],
                'expires_at' => now()->addYear()
            ]));
        }
    }
}