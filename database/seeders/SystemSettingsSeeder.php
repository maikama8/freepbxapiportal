<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // SIP Server Configuration
        SystemSetting::set(
            'sip_server_host',
            env('SIP_SERVER_HOST', '192.168.1.100'),
            'string',
            'sip',
            'SIP Server Host',
            'The IP address or hostname of the FreePBX/SIP server',
            false
        );

        SystemSetting::set(
            'sip_server_port',
            env('SIP_SERVER_PORT', '5060'),
            'integer',
            'sip',
            'SIP Server Port',
            'The port number for SIP communication (default: 5060)',
            false
        );

        SystemSetting::set(
            'sip_server_transport',
            env('SIP_SERVER_TRANSPORT', 'UDP'),
            'string',
            'sip',
            'SIP Transport Protocol',
            'Transport protocol for SIP communication (UDP, TCP, TLS)',
            false
        );

        SystemSetting::set(
            'freepbx_api_url',
            env('FREEPBX_API_URL', 'http://192.168.1.100/admin/api/api'),
            'string',
            'freepbx',
            'FreePBX API URL',
            'The base URL for FreePBX API endpoints',
            false
        );

        SystemSetting::set(
            'freepbx_api_username',
            env('FREEPBX_API_USERNAME', 'admin'),
            'string',
            'freepbx',
            'FreePBX API Username',
            'Username for FreePBX API authentication',
            false
        );

        SystemSetting::set(
            'freepbx_api_password',
            env('FREEPBX_API_PASSWORD', ''),
            'string',
            'freepbx',
            'FreePBX API Password',
            'Password for FreePBX API authentication',
            false
        );

        // Extension Settings
        SystemSetting::set(
            'extension_range_start',
            '1000',
            'integer',
            'extensions',
            'Extension Range Start',
            'Starting number for auto-generated extensions',
            false
        );

        SystemSetting::set(
            'extension_range_end',
            '9999',
            'integer',
            'extensions',
            'Extension Range End',
            'Ending number for auto-generated extensions',
            false
        );

        SystemSetting::set(
            'default_extension_context',
            'from-internal',
            'string',
            'extensions',
            'Default Extension Context',
            'Default context for new extensions',
            false
        );

        // Company Information
        SystemSetting::set(
            'company_name',
            'FreePBX VoIP Platform',
            'string',
            'company',
            'Company Name',
            'Name of the company or organization',
            true
        );

        SystemSetting::set(
            'company_email',
            'admin@voipplatform.com',
            'string',
            'company',
            'Company Email',
            'Primary contact email address',
            true
        );

        SystemSetting::set(
            'company_phone',
            '+1-555-0123',
            'string',
            'company',
            'Company Phone',
            'Primary contact phone number',
            true
        );

        // System Configuration
        SystemSetting::set(
            'timezone',
            'UTC',
            'string',
            'system',
            'System Timezone',
            'Default timezone for the system',
            false
        );

        SystemSetting::set(
            'currency',
            'USD',
            'string',
            'billing',
            'Default Currency',
            'Default currency for billing and payments',
            true
        );

        SystemSetting::set(
            'low_balance_threshold',
            '10.00',
            'float',
            'billing',
            'Low Balance Threshold',
            'Balance threshold for low balance warnings',
            false
        );
    }
}