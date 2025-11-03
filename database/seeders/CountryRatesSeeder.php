<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CountryRate;

class CountryRatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countryRates = [
            [
                'country_code' => 'US',
                'country_name' => 'United States',
                'country_prefix' => '1',
                'did_setup_cost' => 5.00,
                'did_monthly_cost' => 2.50,
                'call_rate_per_minute' => 0.02,
                'sms_rate_per_message' => 0.01,
                'billing_increment' => '60',
                'minimum_duration' => 60,
                'is_active' => true,
                'area_codes' => ['212', '213', '214', '215', '216', '217', '218', '219', '224', '225'],
                'features' => ['voice', 'sms', 'fax']
            ],
            [
                'country_code' => 'CA',
                'country_name' => 'Canada',
                'country_prefix' => '1',
                'did_setup_cost' => 5.00,
                'did_monthly_cost' => 3.00,
                'call_rate_per_minute' => 0.025,
                'sms_rate_per_message' => 0.012,
                'billing_increment' => '60',
                'minimum_duration' => 60,
                'is_active' => true,
                'area_codes' => ['403', '416', '418', '438', '450', '506', '514', '519', '579', '581'],
                'features' => ['voice', 'sms', 'fax']
            ],
            [
                'country_code' => 'GB',
                'country_name' => 'United Kingdom',
                'country_prefix' => '44',
                'did_setup_cost' => 8.00,
                'did_monthly_cost' => 4.50,
                'call_rate_per_minute' => 0.035,
                'sms_rate_per_message' => 0.015,
                'billing_increment' => '60',
                'minimum_duration' => 60,
                'is_active' => true,
                'area_codes' => ['20', '121', '131', '141', '151', '161', '171', '181', '191', '1202'],
                'features' => ['voice', 'sms']
            ],
            [
                'country_code' => 'DE',
                'country_name' => 'Germany',
                'country_prefix' => '49',
                'did_setup_cost' => 10.00,
                'did_monthly_cost' => 5.00,
                'call_rate_per_minute' => 0.04,
                'sms_rate_per_message' => 0.018,
                'billing_increment' => '60',
                'minimum_duration' => 60,
                'is_active' => true,
                'area_codes' => ['30', '40', '69', '89', '201', '211', '221', '231', '241', '251'],
                'features' => ['voice', 'sms']
            ],
            [
                'country_code' => 'FR',
                'country_name' => 'France',
                'country_prefix' => '33',
                'did_setup_cost' => 12.00,
                'did_monthly_cost' => 6.00,
                'call_rate_per_minute' => 0.045,
                'sms_rate_per_message' => 0.02,
                'billing_increment' => '60',
                'minimum_duration' => 60,
                'is_active' => true,
                'area_codes' => ['1', '2', '3', '4', '5', '6', '7', '8', '9'],
                'features' => ['voice', 'sms']
            ],
            [
                'country_code' => 'AU',
                'country_name' => 'Australia',
                'country_prefix' => '61',
                'did_setup_cost' => 15.00,
                'did_monthly_cost' => 8.00,
                'call_rate_per_minute' => 0.06,
                'sms_rate_per_message' => 0.025,
                'billing_increment' => '30',
                'minimum_duration' => 30,
                'is_active' => true,
                'area_codes' => ['2', '3', '7', '8'],
                'features' => ['voice', 'sms']
            ],
            [
                'country_code' => 'JP',
                'country_name' => 'Japan',
                'country_prefix' => '81',
                'did_setup_cost' => 20.00,
                'did_monthly_cost' => 12.00,
                'call_rate_per_minute' => 0.08,
                'sms_rate_per_message' => 0.03,
                'billing_increment' => '30',
                'minimum_duration' => 30,
                'is_active' => true,
                'area_codes' => ['3', '6', '45', '52', '75', '78', '82', '86', '92', '95'],
                'features' => ['voice', 'sms']
            ],
            [
                'country_code' => 'IN',
                'country_name' => 'India',
                'country_prefix' => '91',
                'did_setup_cost' => 25.00,
                'did_monthly_cost' => 15.00,
                'call_rate_per_minute' => 0.12,
                'sms_rate_per_message' => 0.05,
                'billing_increment' => '6',
                'minimum_duration' => 6,
                'is_active' => true,
                'area_codes' => ['11', '22', '33', '44', '80', '124', '129', '135', '141', '172'],
                'features' => ['voice', 'sms']
            ],
            [
                'country_code' => 'BR',
                'country_name' => 'Brazil',
                'country_prefix' => '55',
                'did_setup_cost' => 18.00,
                'did_monthly_cost' => 10.00,
                'call_rate_per_minute' => 0.10,
                'sms_rate_per_message' => 0.04,
                'billing_increment' => '6',
                'minimum_duration' => 6,
                'is_active' => true,
                'area_codes' => ['11', '21', '31', '41', '47', '51', '61', '71', '81', '85'],
                'features' => ['voice', 'sms']
            ],
            [
                'country_code' => 'MX',
                'country_name' => 'Mexico',
                'country_prefix' => '52',
                'did_setup_cost' => 8.00,
                'did_monthly_cost' => 4.00,
                'call_rate_per_minute' => 0.05,
                'sms_rate_per_message' => 0.02,
                'billing_increment' => '30',
                'minimum_duration' => 30,
                'is_active' => true,
                'area_codes' => ['55', '33', '81', '222', '228', '229', '238', '246', '248', '271'],
                'features' => ['voice', 'sms']
            ]
        ];

        foreach ($countryRates as $rate) {
            CountryRate::updateOrCreate(
                ['country_code' => $rate['country_code']],
                $rate
            );
        }

        $this->command->info('Country rates seeded successfully!');
    }
}