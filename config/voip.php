<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FreePBX API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for FreePBX API v17 integration
    |
    */

    'freepbx' => [
        // API Configuration
        'api_url' => env('FREEPBX_API_URL', 'http://localhost'),
        'username' => env('FREEPBX_API_USERNAME'),
        'password' => env('FREEPBX_API_PASSWORD'),
        'version' => env('FREEPBX_API_VERSION', 'v17'),
        'timeout' => env('FREEPBX_API_TIMEOUT', 30),
        'retry_attempts' => env('FREEPBX_API_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('FREEPBX_API_RETRY_DELAY', 1000), // milliseconds
        
        // Database Configuration for CDR access
        'database' => [
            'host' => env('FREEPBX_DB_HOST', 'localhost'),
            'port' => env('FREEPBX_DB_PORT', 3306),
            'database' => env('FREEPBX_DB_DATABASE', 'asteriskcdrdb'),
            'username' => env('FREEPBX_DB_USERNAME'),
            'password' => env('FREEPBX_DB_PASSWORD'),
        ],
        
        // SIP Configuration
        'sip' => [
            'domain' => env('FREEPBX_SIP_DOMAIN', 'localhost'),
            'port' => env('FREEPBX_SIP_PORT', 5060),
            'transport' => env('FREEPBX_SIP_TRANSPORT', 'udp'),
            'context' => env('FREEPBX_SIP_CONTEXT', 'from-internal'),
        ],
        
        // Extension Configuration
        'extensions' => [
            'start_range' => env('FREEPBX_EXT_START_RANGE', 2000),
            'end_range' => env('FREEPBX_EXT_END_RANGE', 9999),
            'admin_range_start' => env('FREEPBX_ADMIN_EXT_START', 1000),
            'admin_range_end' => env('FREEPBX_ADMIN_EXT_END', 1999),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for payment processing gateways
    |
    */

    'payments' => [
        'nowpayments' => [
            'api_key' => env('NOWPAYMENTS_API_KEY'),
            'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
            'sandbox' => env('NOWPAYMENTS_SANDBOX', true),
            'api_url' => env('NOWPAYMENTS_SANDBOX', true) 
                ? 'https://api-sandbox.nowpayments.io' 
                : 'https://api.nowpayments.io',
            'supported_currencies' => ['BTC', 'ETH', 'USDT', 'LTC', 'BCH'],
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
            'api_url' => env('PAYPAL_SANDBOX', true) 
                ? 'https://api-m.sandbox.paypal.com' 
                : 'https://api-m.paypal.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | VoIP Platform Settings
    |--------------------------------------------------------------------------
    |
    | Core platform configuration settings
    |
    */

    'platform' => [
        'default_currency' => env('VOIP_DEFAULT_CURRENCY', 'USD'),
        'min_balance' => env('VOIP_MIN_BALANCE', 0.00),
        'default_credit_limit' => env('VOIP_DEFAULT_CREDIT_LIMIT', 100.00),
        'call_timeout' => env('VOIP_CALL_TIMEOUT', 3600), // seconds
        'billing_increment' => env('VOIP_BILLING_INCREMENT', 60), // seconds
        'supported_currencies' => ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'USDT'],
        'account_types' => ['prepaid', 'postpaid'],
        'user_roles' => ['admin', 'customer', 'operator'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related configuration
    |
    */

    'security' => [
        'account_lockout' => [
            'max_attempts' => env('ACCOUNT_LOCKOUT_ATTEMPTS', 3),
            'lockout_duration' => env('ACCOUNT_LOCKOUT_DURATION', 900), // seconds
        ],
        'password' => [
            'min_length' => env('PASSWORD_MIN_LENGTH', 8),
            'history_count' => env('PASSWORD_HISTORY_COUNT', 5),
            'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
            'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
            'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
            'require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
            'max_age_days' => env('PASSWORD_MAX_AGE_DAYS', 90),
        ],
        'session' => [
            'timeout' => env('SESSION_TIMEOUT', 7200), // seconds
            'concurrent_sessions' => env('MAX_CONCURRENT_SESSIONS', 3),
            'secure_cookies' => env('SESSION_SECURE_COOKIES', true),
            'same_site' => env('SESSION_SAME_SITE', 'strict'),
        ],
        'ip_lockout' => [
            'max_attempts' => env('IP_LOCKOUT_ATTEMPTS', 10),
            'block_duration' => env('IP_LOCKOUT_DURATION', 3600), // seconds
        ],
        'rate_limiting' => [
            'max_requests_per_minute' => env('RATE_LIMIT_REQUESTS_PER_MINUTE', 60),
            'max_login_attempts_per_minute' => env('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
        ],
        'ip_whitelist' => array_filter(explode(',', env('IP_WHITELIST', ''))),
        'ip_blacklist' => array_filter(explode(',', env('IP_BLACKLIST', ''))),
        'encryption' => [
            'algorithm' => env('ENCRYPTION_ALGORITHM', 'AES-256-CBC'),
            'key_rotation_days' => env('ENCRYPTION_KEY_ROTATION_DAYS', 30),
        ],
        'two_factor' => [
            'enabled' => env('TWO_FACTOR_ENABLED', false),
            'required_for_admin' => env('TWO_FACTOR_REQUIRED_ADMIN', true),
            'backup_codes_count' => env('TWO_FACTOR_BACKUP_CODES', 8),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for audit logging
    |
    */

    'audit' => [
        'enabled' => env('AUDIT_LOG_ENABLED', true),
        'channel' => env('AUDIT_LOG_CHANNEL', 'audit'),
        'events' => [
            'user_login',
            'user_logout',
            'password_change',
            'account_created',
            'account_updated',
            'payment_processed',
            'call_initiated',
            'call_completed',
            'rate_updated',
            'admin_action',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Call Rating Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for call rating and billing
    |
    */

    'rating' => [
        'default_rate' => 0.05, // per minute
        'minimum_duration' => 60, // seconds
        'billing_increment' => 60, // seconds
        'grace_period' => 6, // seconds
        'rate_precision' => 6, // decimal places
        'currency_precision' => 4, // decimal places
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing Configuration
    |--------------------------------------------------------------------------
    |
    | Invoice and billing system configuration
    |
    */

    'billing' => [
        'currency' => env('VOIP_CURRENCY', 'USD'),
        'decimal_places' => env('VOIP_DECIMAL_PLACES', 4),
        'tax_rate' => env('VOIP_TAX_RATE', 0.0),
        'payment_terms_days' => env('VOIP_PAYMENT_TERMS_DAYS', 30),
        'monthly_service_fee' => env('VOIP_MONTHLY_SERVICE_FEE', 0.00),
        'low_balance_threshold' => env('VOIP_LOW_BALANCE_THRESHOLD', 5.00),
    ],

    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Company details for invoices and communications
    |
    */

    'company' => [
        'name' => env('COMPANY_NAME', 'VoIP Platform'),
        'address' => env('COMPANY_ADDRESS', '123 Business St'),
        'city' => env('COMPANY_CITY', 'Business City'),
        'state' => env('COMPANY_STATE', 'ST'),
        'zip' => env('COMPANY_ZIP', '12345'),
        'phone' => env('COMPANY_PHONE', '(555) 123-4567'),
        'email' => env('COMPANY_EMAIL', 'billing@voipplatform.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Email templates and settings
    |
    */

    'email' => [
        'templates' => [
            'payment_confirmation' => 'emails.payment.confirmation',
            'low_balance_warning' => 'emails.payment.low-balance',
            'payment_failed' => 'emails.payment.failed',
            'account_created' => 'emails.account.created',
            'password_reset' => 'emails.auth.password_reset',
            'invoice_sent' => 'emails.billing.invoice_sent',
            'test_email' => 'emails.test',
        ],
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'noreply@voipplatform.com'),
            'name' => env('MAIL_FROM_NAME', 'VoIP Platform'),
        ],
        'notifications' => [
            'payment_confirmation' => env('EMAIL_PAYMENT_CONFIRMATION', true),
            'payment_failed' => env('EMAIL_PAYMENT_FAILED', true),
            'low_balance_warning' => env('EMAIL_LOW_BALANCE_WARNING', true),
        ],
        'queue_emails' => env('EMAIL_QUEUE_ENABLED', true),
        'low_balance_check_frequency' => env('EMAIL_LOW_BALANCE_CHECK_FREQUENCY', 'daily'),
    ],

];