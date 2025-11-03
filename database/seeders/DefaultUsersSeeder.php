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
        $admin = \App\Models\User::factory()->admin()->create([
            'name' => 'System Administrator',
            'email' => 'admin@voipplatform.com',
            'phone' => '+1234567890',
            'sip_username' => '1001',
            'sip_password' => 'admin_sip_123',
            'sip_context' => 'from-internal',
            'extension_status' => 'active',
            'password' => \Hash::make('admin123'),
        ]);

        // Create default operator user
        $operator = \App\Models\User::factory()->operator()->create([
            'name' => 'System Operator',
            'email' => 'operator@voipplatform.com',
            'phone' => '+1234567891',
            'sip_username' => '1002',
            'sip_password' => 'operator_sip_123',
            'sip_context' => 'from-internal',
            'extension_status' => 'active',
            'password' => \Hash::make('operator123'),
        ]);

        // Create sample customer users
        $john = \App\Models\User::factory()->customer()->prepaid()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567892',
            'sip_username' => '1003',
            'sip_password' => 'john_sip_123',
            'sip_context' => 'from-internal',
            'extension_status' => 'active',
            'voicemail_enabled' => true,
            'voicemail_email' => 'john@example.com',
            'password' => \Hash::make('customer123'),
            'balance' => 50.00,
        ]);

        $jane = \App\Models\User::factory()->customer()->postpaid()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'phone' => '+1234567893',
            'sip_username' => '1004',
            'sip_password' => 'jane_sip_123',
            'sip_context' => 'from-internal',
            'extension_status' => 'active',
            'voicemail_enabled' => true,
            'voicemail_email' => 'jane@example.com',
            'call_forward_enabled' => false,
            'password' => \Hash::make('customer123'),
            'credit_limit' => 200.00,
        ]);

        // Create additional sample users for testing
        \App\Models\User::factory(10)->customer()->create();
    }
}
