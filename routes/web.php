<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Customer\CallController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;

// Public routes
Route::get('/', function () {
    return view('welcome');
});

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware(['auth'])->group(function () {
    // Customer routes
    Route::middleware('role:customer')->prefix('customer')->name('customer.')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Customer\CustomerController::class, 'dashboard'])->name('dashboard');
        Route::get('/call-history', [App\Http\Controllers\Customer\CustomerController::class, 'callHistory'])->name('call-history');
        Route::get('/account-settings', [App\Http\Controllers\Customer\CustomerController::class, 'accountSettings'])->name('account-settings');
        Route::put('/account-settings', [App\Http\Controllers\Customer\CustomerController::class, 'updateAccountSettings'])->name('account-settings.update');
        Route::get('/balance-history', [App\Http\Controllers\Customer\CustomerController::class, 'balanceHistory'])->name('balance-history');
        
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
        
        Route::put('/password', function () {
            // Password update logic will be implemented in task 6.2
            return redirect()->back()->with('success', 'Password updated successfully.');
        })->name('password.update');
    });

    // Legacy dashboard route (redirect to customer dashboard)
    Route::get('/dashboard', function () {
        if (auth()->user()->isCustomer()) {
            return redirect()->route('customer.dashboard');
        } elseif (auth()->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        } else {
            return redirect()->route('operator.dashboard');
        }
    })->name('dashboard');

    // Admin dashboard
    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->middleware('role:admin')->name('admin.dashboard');

    // Operator dashboard
    Route::get('/operator/dashboard', function () {
        return view('operator.dashboard');
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
    Route::get('/calls', function () { return view('admin.calls.index'); })->name('calls.index');
    Route::get('/billing', function () { return view('admin.billing.index'); })->name('billing.index');
    Route::get('/payments', function () { return view('admin.payments.index'); })->name('payments.index');
    Route::get('/reports', function () { return view('admin.reports.index'); })->name('reports.index');
    // System Monitoring and Reports
    Route::get('/system', [App\Http\Controllers\Admin\SystemController::class, 'index'])->name('system.index');
    Route::get('/system/metrics', [App\Http\Controllers\Admin\SystemController::class, 'getMetrics'])->name('system.metrics');
    Route::get('/system/call-volume', [App\Http\Controllers\Admin\SystemController::class, 'getCallVolumeData'])->name('system.call-volume');
    Route::get('/system/revenue', [App\Http\Controllers\Admin\SystemController::class, 'getRevenueData'])->name('system.revenue');
    Route::get('/system/payment-config', [App\Http\Controllers\Admin\SystemController::class, 'getPaymentGatewayConfig'])->name('system.payment-config');
    Route::post('/system/payment-config', [App\Http\Controllers\Admin\SystemController::class, 'updatePaymentGatewayConfig'])->name('system.payment-config.update');
    Route::get('/system/audit-logs', [App\Http\Controllers\Admin\SystemController::class, 'getAuditLogs'])->name('system.audit-logs');
    Route::get('/system/health', [App\Http\Controllers\Admin\SystemController::class, 'getSystemHealth'])->name('system.health');
    Route::get('/audit', function () { return view('admin.audit.index'); })->name('audit.index');
    
    // Monitoring routes
    Route::get('/monitoring', [App\Http\Controllers\Admin\MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/monitoring/health', [App\Http\Controllers\Admin\MonitoringController::class, 'health'])->name('monitoring.health');
    Route::get('/monitoring/performance', [App\Http\Controllers\Admin\MonitoringController::class, 'performance'])->name('monitoring.performance');
    Route::get('/monitoring/logs', [App\Http\Controllers\Admin\MonitoringController::class, 'logs'])->name('monitoring.logs');
    Route::post('/monitoring/logs/clear', [App\Http\Controllers\Admin\MonitoringController::class, 'clearLogs'])->name('monitoring.logs.clear');
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
