<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default admin user
        \App\Models\User::factory()->admin()->create([
            'name' => 'System Administrator',
            'email' => 'admin@voipplatform.com',
            'phone' => '+1234567890',
            'sip_username' => 'admin',
            'password' => \Hash::make('admin123'),
        ]);

        // Create default operator user
        \App\Models\User::factory()->operator()->create([
            'name' => 'System Operator',
            'email' => 'operator@voipplatform.com',
            'phone' => '+1234567891',
            'sip_username' => 'operator',
            'password' => \Hash::make('operator123'),
        ]);

        // Create sample customer users
        \App\Models\User::factory()->customer()->prepaid()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567892',
            'sip_username' => 'john_doe',
            'password' => \Hash::make('customer123'),
            'balance' => 50.00,
        ]);

        \App\Models\User::factory()->customer()->postpaid()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1234567893',
            'sip_username' => 'jane_smith',
            'password' => \Hash::make('customer123'),
            'credit_limit' => 200.00,
        ]);

        // Create additional sample users for testing
        \App\Models\User::factory(10)->customer()->create();
    }
}
