<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CallRate;

class CallRatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rates = [
            // US and Canada
            ['prefix' => '1', 'name' => 'USA/Canada', 'rate' => 0.015, 'min_duration' => 60, 'increment' => 60],
            
            // UK
            ['prefix' => '44', 'name' => 'United Kingdom', 'rate' => 0.025, 'min_duration' => 60, 'increment' => 60],
            
            // Germany
            ['prefix' => '49', 'name' => 'Germany', 'rate' => 0.030, 'min_duration' => 60, 'increment' => 60],
            
            // France
            ['prefix' => '33', 'name' => 'France', 'rate' => 0.028, 'min_duration' => 60, 'increment' => 60],
            
            // Australia
            ['prefix' => '61', 'name' => 'Australia', 'rate' => 0.035, 'min_duration' => 60, 'increment' => 60],
            
            // Japan
            ['prefix' => '81', 'name' => 'Japan', 'rate' => 0.040, 'min_duration' => 60, 'increment' => 60],
            
            // China
            ['prefix' => '86', 'name' => 'China', 'rate' => 0.045, 'min_duration' => 60, 'increment' => 60],
            
            // India
            ['prefix' => '91', 'name' => 'India', 'rate' => 0.050, 'min_duration' => 60, 'increment' => 60],
            
            // Brazil
            ['prefix' => '55', 'name' => 'Brazil', 'rate' => 0.055, 'min_duration' => 60, 'increment' => 60],
            
            // Mexico
            ['prefix' => '52', 'name' => 'Mexico', 'rate' => 0.020, 'min_duration' => 60, 'increment' => 60],
            
            // More specific US rates
            ['prefix' => '1212', 'name' => 'New York City', 'rate' => 0.012, 'min_duration' => 60, 'increment' => 60],
            ['prefix' => '1213', 'name' => 'Los Angeles', 'rate' => 0.012, 'min_duration' => 60, 'increment' => 60],
            ['prefix' => '1312', 'name' => 'Chicago', 'rate' => 0.012, 'min_duration' => 60, 'increment' => 60],
            
            // Premium rates
            ['prefix' => '900', 'name' => 'Premium Services', 'rate' => 0.500, 'min_duration' => 30, 'increment' => 30],
            
            // Emergency/Special (free)
            ['prefix' => '911', 'name' => 'Emergency Services', 'rate' => 0.000, 'min_duration' => 0, 'increment' => 1],
            ['prefix' => '411', 'name' => 'Directory Assistance', 'rate' => 0.250, 'min_duration' => 30, 'increment' => 30],
        ];

        foreach ($rates as $rate) {
            CallRate::create([
                'destination_prefix' => $rate['prefix'],
                'destination_name' => $rate['name'],
                'rate_per_minute' => $rate['rate'],
                'minimum_duration' => $rate['min_duration'],
                'billing_increment' => $rate['increment'],
                'effective_date' => now(),
                'is_active' => true
            ]);
        }
    }
}