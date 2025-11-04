<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Customer\CallController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;

// Public routes - redirect to login since we have no public frontend
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// Session management routes for auto-logout functionality
Route::middleware('auth')->group(function () {
    Route::post('/api/refresh-session', [App\Http\Controllers\SessionController::class, 'refreshSession'])->name('session.refresh');
    Route::get('/api/session-status', [App\Http\Controllers\SessionController::class, 'checkSession'])->name('session.status');
});

// Protected routes
Route::middleware(['auth'])->group(function () {
    // Customer routes
    Route::middleware('role:customer')->prefix('customer')->name('customer.')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Customer\CustomerController::class, 'dashboard'])->name('dashboard');
        Route::get('/call-history', [App\Http\Controllers\Customer\CustomerController::class, 'callHistory'])->name('call-history');
        Route::get('/account-settings', [App\Http\Controllers\Customer\CustomerController::class, 'accountSettings'])->name('account-settings');
        Route::put('/account-settings', [App\Http\Controllers\Customer\CustomerController::class, 'updateAccountSettings'])->name('account-settings.update');
        Route::get('/balance-history', [App\Http\Controllers\Customer\CustomerController::class, 'balanceHistory'])->name('balance-history');
        
        // SIP Account management routes
        Route::prefix('sip-accounts')->name('sip-accounts.')->group(function () {
            Route::get('/', [App\Http\Controllers\Customer\SipAccountController::class, 'index'])->name('index');
            Route::get('/{sipAccount}', [App\Http\Controllers\Customer\SipAccountController::class, 'show'])->name('show');
            Route::get('/{sipAccount}/change-password', [App\Http\Controllers\Customer\SipAccountController::class, 'editPassword'])->name('edit-password');
            Route::put('/{sipAccount}/change-password', [App\Http\Controllers\Customer\SipAccountController::class, 'updatePassword'])->name('update-password');
            Route::put('/{sipAccount}/settings', [App\Http\Controllers\Customer\SipAccountController::class, 'updateSettings'])->name('update-settings');
        });
        
        // AJAX routes
        Route::get('/active-calls-count', function () {
            $activeCallsCount = auth()->user()->callRecords()
                ->whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])
                ->count();
            return response()->json(['active_calls' => $activeCallsCount]);
        })->name('active-calls-count');
        
        Route::get('/balance/refresh', function () {
            return response()->json([
                'success' => true,
                'balance' => number_format(auth()->user()->balance, 2)
            ]);
        })->name('balance.refresh');
        
        // Call management routes
        Route::get('/calls/make', [App\Http\Controllers\Customer\CallController::class, 'makeCall'])->name('calls.make');
        Route::post('/calls/initiate', [App\Http\Controllers\Customer\CallController::class, 'initiateCall'])->name('calls.initiate');
        Route::post('/calls/{callId}/hangup', [App\Http\Controllers\Customer\CallController::class, 'hangupCall'])->name('calls.hangup');
        Route::get('/calls/{callId}/status', [App\Http\Controllers\Customer\CallController::class, 'getCallStatus'])->name('calls.status');
        Route::get('/calls/active', [App\Http\Controllers\Customer\CallController::class, 'getActiveCalls'])->name('calls.active');
        Route::get('/calls/rate', [App\Http\Controllers\Customer\CallController::class, 'getCallRate'])->name('calls.rate');
        Route::get('/calls/enhanced-rate', [App\Http\Controllers\Customer\CallController::class, 'getEnhancedCallRate'])->name('calls.enhanced-rate');
        Route::get('/calls/{callRecord}/billing', [App\Http\Controllers\Customer\CallController::class, 'getCallBilling'])->name('calls.billing');
        Route::get('/calls/monitor', [App\Http\Controllers\Customer\CallController::class, 'monitorCalls'])->name('calls.monitor');
        
        // Payment routes
        Route::get('/payments/add-funds', [CustomerPaymentController::class, 'addFunds'])->name('payments.add-funds');
        Route::post('/payments/initiate', [CustomerPaymentController::class, 'initiatePayment'])->name('payments.initiate');
        Route::get('/payments/history', [CustomerPaymentController::class, 'paymentHistory'])->name('payments.history');
        Route::get('/payments/{transaction}/status', [CustomerPaymentController::class, 'getPaymentStatus'])->name('payments.status');
        Route::get('/payments/crypto-currencies', [CustomerPaymentController::class, 'getCryptoCurrencies'])->name('payments.crypto-currencies');
        Route::get('/payments/minimum-amount', [CustomerPaymentController::class, 'getMinimumAmount'])->name('payments.minimum-amount');
        
        // Invoice routes
        Route::get('/invoices', [CustomerPaymentController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{invoice}/download', [CustomerPaymentController::class, 'downloadInvoice'])->name('invoices.download');
        
        // Billing routes
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/realtime', [App\Http\Controllers\Customer\BillingController::class, 'realtime'])->name('realtime');
            Route::get('/realtime-data', [App\Http\Controllers\Customer\BillingController::class, 'realtimeData'])->name('realtime-data');
            Route::post('/predict-cost', [App\Http\Controllers\Customer\BillingController::class, 'predictCost'])->name('predict-cost');
            Route::post('/update-alerts', [App\Http\Controllers\Customer\BillingController::class, 'updateAlerts'])->name('update-alerts');
            Route::get('/call-breakdown/{callRecord}', [App\Http\Controllers\Customer\BillingController::class, 'callBreakdown'])->name('call-breakdown');
            Route::get('/export', [App\Http\Controllers\Customer\BillingController::class, 'export'])->name('export');
            Route::get('/statistics', [App\Http\Controllers\Customer\BillingController::class, 'statistics'])->name('statistics');
        });
        
        Route::put('/password', function () {
            // Password update logic will be implemented in task 6.2
            return redirect()->back()->with('success', 'Password updated successfully.');
        })->name('password.update');
        
        // Caller ID Management
        Route::post('/caller-id/update', [App\Http\Controllers\Customer\CustomerController::class, 'updateCallerId'])->name('caller-id.update');
        Route::get('/caller-id/available', [App\Http\Controllers\Customer\CustomerController::class, 'getAvailableCallerIds'])->name('caller-id.available');
        
        // DID Management
        Route::prefix('dids')->name('dids.')->group(function () {
            Route::get('/', [App\Http\Controllers\Customer\DidController::class, 'index'])->name('index');
            Route::get('/browse', [App\Http\Controllers\Customer\DidController::class, 'browse'])->name('browse');
            Route::post('/{didNumber}/purchase', [App\Http\Controllers\Customer\DidController::class, 'purchase'])->name('purchase');
            Route::post('/{didNumber}/release', [App\Http\Controllers\Customer\DidController::class, 'release'])->name('release');
            Route::post('/{didNumber}/renew', [App\Http\Controllers\Customer\DidController::class, 'renew'])->name('renew');
            Route::get('/{didNumber}/billing-history', [App\Http\Controllers\Customer\DidController::class, 'billingHistory'])->name('billing-history');
            Route::get('/statistics', [App\Http\Controllers\Customer\DidController::class, 'statistics'])->name('statistics');
            
            // Enhanced DID Management
            Route::get('/{didNumber}/configure', [App\Http\Controllers\Customer\DidController::class, 'configure'])->name('configure');
            Route::post('/{didNumber}/configure', [App\Http\Controllers\Customer\DidController::class, 'updateConfiguration'])->name('configure');
            Route::get('/{didNumber}/transfer', [App\Http\Controllers\Customer\DidController::class, 'showTransfer'])->name('transfer');
            Route::post('/{didNumber}/transfer', [App\Http\Controllers\Customer\DidController::class, 'transfer'])->name('transfer');
            Route::post('/verify-recipient', [App\Http\Controllers\Customer\DidController::class, 'verifyRecipient'])->name('verify-recipient');
        });
    });

    // Legacy dashboard route (redirect based on user role)
    Route::get('/dashboard', function () {
        if (auth()->user()->isCustomer()) {
            return redirect()->route('customer.dashboard');
        } elseif (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        } elseif (auth()->user()->isOperator()) {
            return redirect()->route('operator.dashboard');
        } else {
            // Fallback to customer dashboard for unknown roles
            return redirect()->route('customer.dashboard');
        }
    })->name('dashboard');

    // Admin dashboard
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->middleware('role:admin')->name('admin.dashboard');

    // Operator dashboard
    Route::get('/operator/dashboard', function () {
        // For now, redirect operators to admin dashboard with limited access
        return redirect()->route('admin.dashboard');
    })->middleware('role:operator')->name('operator.dashboard');
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Customer Management
    Route::get('/customers', [App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/data', [App\Http\Controllers\Admin\CustomerController::class, 'getData'])->name('customers.data');
    Route::get('/customers/create', [App\Http\Controllers\Admin\CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [App\Http\Controllers\Admin\CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [App\Http\Controllers\Admin\CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [App\Http\Controllers\Admin\CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [App\Http\Controllers\Admin\CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::post('/customers/{customer}/adjust-balance', [App\Http\Controllers\Admin\CustomerController::class, 'adjustBalance'])->name('customers.adjust-balance');
    Route::post('/customers/{customer}/reset-password', [App\Http\Controllers\Admin\CustomerController::class, 'resetPassword'])->name('customers.reset-password');
    Route::post('/customers/{customer}/create-extension', [App\Http\Controllers\Admin\CustomerController::class, 'createExtension'])->name('customers.create-extension');
    Route::get('/customers/{customer}/call-history', [App\Http\Controllers\Admin\CustomerController::class, 'callHistory'])->name('customers.call-history');
    Route::get('/customers/{customer}/extensions', [App\Http\Controllers\Admin\CustomerController::class, 'getExtensions'])->name('customers.extensions');
    Route::delete('/customers/{customer}/extensions/{sipAccount}', [App\Http\Controllers\Admin\CustomerController::class, 'deleteExtension'])->name('customers.delete-extension');
    
    // SIP Account Management for Customers
    Route::prefix('customers/{user}/sip-accounts')->name('customers.sip-accounts.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\SipAccountController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\SipAccountController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\SipAccountController::class, 'store'])->name('store');
        Route::get('/{sipAccount}/edit', [App\Http\Controllers\Admin\SipAccountController::class, 'edit'])->name('edit');
        Route::put('/{sipAccount}', [App\Http\Controllers\Admin\SipAccountController::class, 'update'])->name('update');
        Route::delete('/{sipAccount}', [App\Http\Controllers\Admin\SipAccountController::class, 'destroy'])->name('destroy');
        Route::post('/{sipAccount}/reset-password', [App\Http\Controllers\Admin\SipAccountController::class, 'resetPassword'])->name('reset-password');
    });
    
    // Rate Management
    Route::get('/rates', [App\Http\Controllers\Admin\RateController::class, 'index'])->name('rates.index');
    Route::get('/rates/data', [App\Http\Controllers\Admin\RateController::class, 'getData'])->name('rates.data');
    Route::post('/rates', [App\Http\Controllers\Admin\RateController::class, 'store'])->name('rates.store');
    Route::get('/rates/{rate}', [App\Http\Controllers\Admin\RateController::class, 'show'])->name('rates.show');
    Route::put('/rates/{rate}', [App\Http\Controllers\Admin\RateController::class, 'update'])->name('rates.update');
    Route::delete('/rates/{rate}', [App\Http\Controllers\Admin\RateController::class, 'destroy'])->name('rates.destroy');
    Route::post('/rates/bulk-import', [App\Http\Controllers\Admin\RateController::class, 'bulkImport'])->name('rates.bulk-import');
    Route::get('/rates/export', [App\Http\Controllers\Admin\RateController::class, 'export'])->name('rates.export');
    Route::get('/rates/history/{prefix}', [App\Http\Controllers\Admin\RateController::class, 'history'])->name('rates.history');
    Route::post('/rates/test', [App\Http\Controllers\Admin\RateController::class, 'testRate'])->name('rates.test');
    
    // Country Rate Management
    Route::prefix('country-rates')->name('country-rates.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CountryRateController::class, 'index'])->name('index');
        Route::get('/data', [App\Http\Controllers\Admin\CountryRateController::class, 'getData'])->name('data');
        Route::post('/', [App\Http\Controllers\Admin\CountryRateController::class, 'store'])->name('store');
        Route::get('/{countryRate}', [App\Http\Controllers\Admin\CountryRateController::class, 'show'])->name('show');
        Route::put('/{countryRate}', [App\Http\Controllers\Admin\CountryRateController::class, 'update'])->name('update');
        Route::delete('/{countryRate}', [App\Http\Controllers\Admin\CountryRateController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-update', [App\Http\Controllers\Admin\CountryRateController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('/comparison', [App\Http\Controllers\Admin\CountryRateController::class, 'comparison'])->name('comparison');
        Route::get('/analytics', [App\Http\Controllers\Admin\CountryRateController::class, 'analytics'])->name('analytics');
        Route::get('/{countryRate}/history', [App\Http\Controllers\Admin\CountryRateController::class, 'changeHistory'])->name('history');
        Route::get('/export', [App\Http\Controllers\Admin\CountryRateController::class, 'export'])->name('export');
    });
    
    // DID Management
    Route::get('/dids', [App\Http\Controllers\Admin\DidController::class, 'index'])->name('dids.index');
    Route::get('/dids/data', [App\Http\Controllers\Admin\DidController::class, 'getData'])->name('dids.data');
    Route::post('/dids', [App\Http\Controllers\Admin\DidController::class, 'store'])->name('dids.store');
    Route::put('/dids/{did}', [App\Http\Controllers\Admin\DidController::class, 'update'])->name('dids.update');
    Route::delete('/dids/{did}', [App\Http\Controllers\Admin\DidController::class, 'destroy'])->name('dids.destroy');
    Route::post('/dids/{did}/assign', [App\Http\Controllers\Admin\DidController::class, 'assign'])->name('dids.assign');
    Route::post('/dids/{did}/release', [App\Http\Controllers\Admin\DidController::class, 'release'])->name('dids.release');
    Route::get('/dids/available', [App\Http\Controllers\Admin\DidController::class, 'getAvailable'])->name('dids.available');
    Route::get('/dids/statistics', [App\Http\Controllers\Admin\DidController::class, 'getStatistics'])->name('dids.statistics');
    Route::post('/dids/bulk-status-update', [App\Http\Controllers\Admin\DidController::class, 'bulkStatusUpdate'])->name('dids.bulk-status-update');
    
    // Bulk upload and management routes
    Route::get('/dids/template', [App\Http\Controllers\Admin\DidController::class, 'downloadTemplate'])->name('dids.template');
    Route::post('/dids/bulk-upload', [App\Http\Controllers\Admin\DidController::class, 'bulkUpload'])->name('dids.bulk-upload');
    Route::post('/dids/bulk-update-prices', [App\Http\Controllers\Admin\DidController::class, 'bulkUpdatePrices'])->name('dids.bulk-update-prices');
    // Placeholder routes for missing sections
    Route::get('/calls', function () { return view('admin.calls.index'); })->name('calls.index');
    // Advanced Billing Management
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\BillingController::class, 'index'])->name('index');
        Route::get('/increments', [App\Http\Controllers\Admin\BillingController::class, 'increments'])->name('increments');
        Route::put('/increments', [App\Http\Controllers\Admin\BillingController::class, 'updateIncrements'])->name('increments.update');
        Route::put('/call-rates/{callRate}/increment', [App\Http\Controllers\Admin\BillingController::class, 'updateCallRateIncrement'])->name('call-rates.increment.update');
        Route::put('/country-rates/{countryRate}/increment', [App\Http\Controllers\Admin\BillingController::class, 'updateCountryRateIncrement'])->name('country-rates.increment.update');
        Route::post('/bulk-update-increments', [App\Http\Controllers\Admin\BillingController::class, 'bulkUpdateIncrements'])->name('bulk-update-increments');
        Route::get('/reports', [App\Http\Controllers\Admin\BillingController::class, 'reports'])->name('reports');
        Route::post('/process-pending', [App\Http\Controllers\Admin\BillingController::class, 'processPendingBilling'])->name('process-pending');
        Route::post('/test', [App\Http\Controllers\Admin\BillingController::class, 'testBilling'])->name('test');
        
        // Advanced Configuration Routes
        Route::get('/configuration', [App\Http\Controllers\Admin\BillingController::class, 'configuration'])->name('configuration');
        Route::get('/get-configuration', [App\Http\Controllers\Admin\BillingController::class, 'getConfiguration'])->name('get-configuration');
        Route::post('/update-configuration', [App\Http\Controllers\Admin\BillingController::class, 'updateConfiguration'])->name('update-configuration');
        Route::get('/monitoring-dashboard', [App\Http\Controllers\Admin\BillingController::class, 'monitoringDashboard'])->name('monitoring-dashboard');
        Route::get('/monitoring-data', [App\Http\Controllers\Admin\BillingController::class, 'getMonitoringData'])->name('monitoring-data');
        Route::get('/rules-management', [App\Http\Controllers\Admin\BillingController::class, 'rulesManagement'])->name('rules-management');
        Route::post('/create-rule', [App\Http\Controllers\Admin\BillingController::class, 'createRule'])->name('create-rule');
        Route::post('/test-rule', [App\Http\Controllers\Admin\BillingController::class, 'testRule'])->name('test-rule');
        Route::get('/performance-analytics', [App\Http\Controllers\Admin\BillingController::class, 'getPerformanceAnalytics'])->name('performance-analytics');
        Route::get('/export-configuration', [App\Http\Controllers\Admin\BillingController::class, 'exportConfiguration'])->name('export-configuration');
        Route::post('/import-configuration', [App\Http\Controllers\Admin\BillingController::class, 'importConfiguration'])->name('import-configuration');
        
        // Real-time billing routes
        Route::prefix('realtime')->name('realtime.')->group(function () {
            Route::get('/dashboard', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'dashboard'])->name('dashboard');
            Route::get('/active-calls', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'getActiveCalls'])->name('active-calls');
            Route::post('/process-periodic', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'processPeriodicBilling'])->name('process-periodic');
            Route::post('/calls/{callRecord}/terminate', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'terminateCall'])->name('terminate-call');
            Route::post('/calls/{callRecord}/emergency-terminate', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'emergencyTerminate'])->name('emergency-terminate');
            Route::post('/terminate-insufficient-balance', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'terminateInsufficientBalanceCalls'])->name('terminate-insufficient-balance');
            Route::post('/terminate-user-calls', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'terminateUserCalls'])->name('terminate-user-calls');
            Route::get('/statistics', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'getStatistics'])->name('statistics');
            Route::post('/calls/{callRecord}/start-billing', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'startBilling'])->name('start-billing');
            Route::post('/calls/{callRecord}/finalize-billing', [App\Http\Controllers\Admin\RealTimeBillingController::class, 'finalizeBilling'])->name('finalize-billing');
        });
    });
    
    // Enhanced CDR Management
    Route::prefix('cdr')->name('cdr.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CDRController::class, 'index'])->name('index');
        Route::get('/data', [App\Http\Controllers\Admin\CDRController::class, 'getData'])->name('data');
        Route::get('/{callRecord}', [App\Http\Controllers\Admin\CDRController::class, 'show'])->name('show');
        Route::post('/process-unprocessed', [App\Http\Controllers\Admin\CDRController::class, 'processUnprocessed'])->name('process-unprocessed');
        Route::post('/{callRecord}/reprocess-billing', [App\Http\Controllers\Admin\CDRController::class, 'reprocessBilling'])->name('reprocess-billing');
        Route::get('/statistics/data', [App\Http\Controllers\Admin\CDRController::class, 'getStatistics'])->name('statistics');
        Route::post('/export', [App\Http\Controllers\Admin\CDRController::class, 'export'])->name('export');
    });
    Route::get('/payments', function () { return view('admin.payments.index'); })->name('payments.index');
    Route::get('/reports', function () { return view('admin.reports.index'); })->name('reports.index');
    
    // Role Management Routes
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\RoleManagementController::class, 'index'])->name('index');
        Route::get('/data', [App\Http\Controllers\Admin\RoleManagementController::class, 'getData'])->name('data');
        Route::post('/', [App\Http\Controllers\Admin\RoleManagementController::class, 'store'])->name('store');
        Route::get('/{role}', [App\Http\Controllers\Admin\RoleManagementController::class, 'show'])->name('show');
        Route::put('/{role}', [App\Http\Controllers\Admin\RoleManagementController::class, 'update'])->name('update');
        Route::delete('/{role}', [App\Http\Controllers\Admin\RoleManagementController::class, 'destroy'])->name('destroy');
        Route::get('/permissions/list', [App\Http\Controllers\Admin\RoleManagementController::class, 'getPermissions'])->name('permissions');
        Route::post('/assign', [App\Http\Controllers\Admin\RoleManagementController::class, 'assignRole'])->name('assign');
        Route::get('/statistics/data', [App\Http\Controllers\Admin\RoleManagementController::class, 'getStatistics'])->name('statistics');
    });
    
    // Audit Log Management
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\AuditController::class, 'index'])->name('index');
        Route::get('/data', [App\Http\Controllers\Admin\AuditController::class, 'getData'])->name('data');
        Route::get('/statistics', [App\Http\Controllers\Admin\AuditController::class, 'getStatistics'])->name('statistics');
        Route::get('/users', [App\Http\Controllers\Admin\AuditController::class, 'getUsers'])->name('users');
        Route::get('/actions', [App\Http\Controllers\Admin\AuditController::class, 'getActions'])->name('actions');
        Route::get('/export', [App\Http\Controllers\Admin\AuditController::class, 'export'])->name('export');
    });
    
    // System Monitoring and Reports
    Route::get('/system', [App\Http\Controllers\Admin\SystemController::class, 'index'])->name('system.index');
    Route::get('/system/metrics', [App\Http\Controllers\Admin\SystemController::class, 'getMetrics'])->name('system.metrics');
    Route::get('/system/call-volume', [App\Http\Controllers\Admin\SystemController::class, 'getCallVolumeData'])->name('system.call-volume');
    Route::get('/system/revenue', [App\Http\Controllers\Admin\SystemController::class, 'getRevenueData'])->name('system.revenue');
    Route::get('/system/payment-config', [App\Http\Controllers\Admin\SystemController::class, 'getPaymentGatewayConfig'])->name('system.payment-config');
    Route::post('/system/payment-config', [App\Http\Controllers\Admin\SystemController::class, 'updatePaymentGatewayConfig'])->name('system.payment-config.update');
    Route::get('/system/audit-logs', [App\Http\Controllers\Admin\SystemController::class, 'getAuditLogs'])->name('system.audit-logs');
    Route::get('/system/health', [App\Http\Controllers\Admin\SystemController::class, 'getSystemHealth'])->name('system.health');
    
    // Monitoring routes
    Route::get('/monitoring', [App\Http\Controllers\Admin\MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/monitoring/health', [App\Http\Controllers\Admin\MonitoringController::class, 'health'])->name('monitoring.health');
    Route::get('/monitoring/performance', [App\Http\Controllers\Admin\MonitoringController::class, 'performance'])->name('monitoring.performance');
    Route::get('/monitoring/logs', [App\Http\Controllers\Admin\MonitoringController::class, 'logs'])->name('monitoring.logs');
    Route::post('/monitoring/logs/clear', [App\Http\Controllers\Admin\MonitoringController::class, 'clearLogs'])->name('monitoring.logs.clear');
    
    // System settings
    Route::get('/settings', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-freepbx', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'testFreepbxConnection'])->name('settings.test-freepbx');
    Route::post('/settings/sip-status', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'getSipServerStatus'])->name('settings.sip-status');
    Route::post('/settings/reset', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'resetToDefaults'])->name('settings.reset');
    
    // Cron Job Management
    Route::prefix('cron-jobs')->name('cron-jobs.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CronJobController::class, 'index'])->name('index');
        Route::get('/status', [App\Http\Controllers\Admin\CronJobController::class, 'status'])->name('status');
        Route::get('/history', [App\Http\Controllers\Admin\CronJobController::class, 'history'])->name('history');
        Route::get('/statistics', [App\Http\Controllers\Admin\CronJobController::class, 'statistics'])->name('statistics');
        Route::post('/kill-job', [App\Http\Controllers\Admin\CronJobController::class, 'killJob'])->name('kill-job');
        Route::post('/cleanup', [App\Http\Controllers\Admin\CronJobController::class, 'cleanup'])->name('cleanup');
        Route::get('/job-details/{executionId}', [App\Http\Controllers\Admin\CronJobController::class, 'jobDetails'])->name('job-details');
        Route::get('/job-names', [App\Http\Controllers\Admin\CronJobController::class, 'jobNames'])->name('job-names');
        
        // Advanced Automation Monitoring Routes
        Route::get('/monitoring-dashboard', [App\Http\Controllers\Admin\CronJobController::class, 'monitoringDashboard'])->name('monitoring-dashboard');
        Route::get('/monitoring-data', [App\Http\Controllers\Admin\CronJobController::class, 'getMonitoringData'])->name('monitoring-data');
        Route::get('/performance-analytics', [App\Http\Controllers\Admin\CronJobController::class, 'getPerformanceAnalytics'])->name('performance-analytics');
        Route::post('/configure-alerts', [App\Http\Controllers\Admin\CronJobController::class, 'configureAlerts'])->name('configure-alerts');
        Route::post('/test-alert', [App\Http\Controllers\Admin\CronJobController::class, 'testAlert'])->name('test-alert');
        Route::get('/health-report', [App\Http\Controllers\Admin\CronJobController::class, 'getHealthReport'])->name('health-report');
        Route::get('/export-monitoring-data', [App\Http\Controllers\Admin\CronJobController::class, 'exportMonitoringData'])->name('export-monitoring-data');
        Route::post('/trigger-health-check', [App\Http\Controllers\Admin\CronJobController::class, 'triggerHealthCheck'])->name('trigger-health-check');
    });
    
    // Debug route for testing
    Route::get('/debug/test-auth', function() {
        return response()->json([
            'authenticated' => auth()->check(),
            'user' => auth()->user() ? [
                'id' => auth()->user()->id,
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'role' => auth()->user()->role
            ] : null,
            'session_id' => session()->getId(),
            'csrf_token' => csrf_token()
        ]);
    })->name('debug.test-auth');
    
    // Simple customers data test
    Route::get('/debug/customers-simple', function() {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Not authenticated as admin'], 401);
        }
        
        $users = \App\Models\User::whereIn('role', ['customer', 'operator'])->get();
        return response()->json([
            'success' => true,
            'count' => $users->count(),
            'users' => $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ];
            })
        ]);
    })->name('debug.customers-simple');
});

Route::middleware(['auth', 'role:customer,operator'])->prefix('calls')->group(function () {
    Route::get('/history', function () {
        return 'Call history page';
    })->name('calls.history');
});

// Test routes for permission-based access
Route::middleware(['auth', 'permission:admin.dashboard'])->get('/admin-test', function () {
    return 'Admin permission test passed';
});

Route::middleware(['auth', 'permission:calls.make'])->get('/call-test', function () {
    return 'Call permission test passed';
});
