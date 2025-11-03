<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class VoipServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register VoIP configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/voip.php', 'voip'
        );

        // Register FreePBX API Client
        $this->app->singleton('freepbx.client', function ($app) {
            return new \App\Services\FreePBX\FreePBXApiClient(
                config('voip.freepbx.api_url'),
                config('voip.freepbx.username'),
                config('voip.freepbx.password'),
                config('voip.freepbx.version'),
                config('voip.freepbx.client_id'),
                config('voip.freepbx.client_secret')
            );
        });

        // Also register the class directly for dependency injection
        $this->app->singleton(\App\Services\FreePBX\FreePBXApiClient::class, function ($app) {
            return $app->make('freepbx.client');
        });

        // Register Call Management Service
        $this->app->singleton(\App\Services\FreePBX\CallManagementService::class, function ($app) {
            return new \App\Services\FreePBX\CallManagementService(
                $app->make('freepbx.client')
            );
        });

        // Register CDR Service
        $this->app->singleton(\App\Services\FreePBX\CDRService::class, function ($app) {
            return new \App\Services\FreePBX\CDRService(
                $app->make('freepbx.client')
            );
        });

        // Register Billing Service
        $this->app->singleton('billing.service', function ($app) {
            return new \App\Services\Billing\BillingService();
        });

        // Register Payment Gateways
        $this->app->singleton('payment.nowpayments', function ($app) {
            return new \App\Services\Payment\NowPaymentsGateway(
                config('voip.payments.nowpayments.api_key'),
                config('voip.payments.nowpayments.sandbox')
            );
        });

        $this->app->singleton('payment.paypal', function ($app) {
            return new \App\Services\Payment\PayPalGateway(
                config('voip.payments.paypal.client_id'),
                config('voip.payments.paypal.client_secret'),
                config('voip.payments.paypal.sandbox')
            );
        });

        // Register Role Permission Service
        $this->app->singleton(\App\Services\RolePermissionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/voip.php' => config_path('voip.php'),
        ], 'voip-config');

        // Load custom validation rules
        $this->loadCustomValidationRules();
    }

    /**
     * Load custom validation rules for VoIP platform
     */
    protected function loadCustomValidationRules(): void
    {
        // Add custom validation rules here if needed
    }
}
