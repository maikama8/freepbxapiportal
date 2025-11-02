<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Documentation routes (public)
Route::prefix('docs')->group(function () {
    Route::get('/', [DocumentationController::class, 'index'])->name('api.docs');
    Route::get('/rate-limits', [DocumentationController::class, 'rateLimits'])->name('api.docs.rate-limits');
    Route::get('/auth-guide', [DocumentationController::class, 'authGuide'])->name('api.docs.auth-guide');
});

// Public authentication routes with rate limiting
Route::middleware(['api.throttle:auth'])->prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected authentication routes with rate limiting
Route::middleware(['auth:sanctum', 'api.throttle:auth'])->prefix('auth')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

// Test route for authenticated users
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Role-based test routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/test', function () {
        return response()->json(['message' => 'Admin access granted']);
    });
});

Route::middleware(['auth:sanctum', 'role:customer'])->prefix('customer')->group(function () {
    Route::get('/test', function () {
        return response()->json(['message' => 'Customer access granted']);
    });
});

Route::middleware(['auth:sanctum', 'role:operator'])->prefix('operator')->group(function () {
    Route::get('/test', function () {
        return response()->json(['message' => 'Operator access granted']);
    });
});

// Permission-based test routes
Route::middleware(['auth:sanctum', 'permission:admin.dashboard'])->get('/admin-dashboard-test', function () {
    return response()->json(['message' => 'Admin dashboard access granted']);
});

Route::middleware(['auth:sanctum', 'permission:calls.make'])->get('/calls-test', function () {
    return response()->json(['message' => 'Call permission granted']);
});

// Customer API routes with rate limiting
Route::middleware(['auth:sanctum', 'role:customer', 'api.throttle:general'])->prefix('customer')->group(function () {
    // Account and balance
    Route::get('/account', [\App\Http\Controllers\Api\CustomerController::class, 'getAccountInfo']);
    Route::get('/balance', [\App\Http\Controllers\Api\CustomerController::class, 'getBalance']);
    
    // Call management with specific rate limiting
    Route::middleware(['api.throttle:calls'])->group(function () {
        Route::get('/calls/history', [\App\Http\Controllers\Api\CustomerController::class, 'getCallHistory']);
        Route::get('/calls/active', [\App\Http\Controllers\Api\CustomerController::class, 'getActiveCalls']);
        Route::post('/calls/initiate', [\App\Http\Controllers\Api\CustomerController::class, 'initiateCall']);
        Route::post('/calls/{callId}/terminate', [\App\Http\Controllers\Api\CustomerController::class, 'terminateCall']);
        Route::get('/calls/{callId}/status', [\App\Http\Controllers\Api\CustomerController::class, 'getCallStatus']);
    });
    
    // Payment operations with specific rate limiting
    Route::middleware(['api.throttle:payments'])->group(function () {
        Route::get('/payments/methods', [\App\Http\Controllers\Api\CustomerController::class, 'getPaymentMethods']);
        Route::post('/payments/initiate', [\App\Http\Controllers\Api\CustomerController::class, 'initiatePayment']);
        Route::get('/payments/history', [\App\Http\Controllers\Api\CustomerController::class, 'getPaymentHistory']);
        Route::get('/payments/{transactionId}/status', [\App\Http\Controllers\Api\CustomerController::class, 'getPaymentStatus']);
    });
});

// Payment API routes (for all authenticated users) with rate limiting
Route::middleware(['auth:sanctum', 'api.throttle:payments'])->prefix('payments')->group(function () {
    Route::get('/methods', [\App\Http\Controllers\Api\PaymentController::class, 'getPaymentMethods']);
    Route::post('/initiate', [\App\Http\Controllers\Api\PaymentController::class, 'initiatePayment']);
    Route::get('/history', [\App\Http\Controllers\Api\PaymentController::class, 'getPaymentHistory']);
    Route::get('/{transactionId}/status', [\App\Http\Controllers\Api\PaymentController::class, 'getPaymentStatus']);
    Route::post('/{transactionId}/cancel', [\App\Http\Controllers\Api\PaymentController::class, 'cancelPayment']);
    Route::post('/{transactionId}/retry', [\App\Http\Controllers\Api\PaymentController::class, 'retryPayment']);
    Route::get('/stats', [\App\Http\Controllers\Api\PaymentController::class, 'getPaymentStats']);
    Route::get('/minimum-amount', [\App\Http\Controllers\Api\PaymentController::class, 'getMinimumAmount']);
});

// Legacy payment routes (for backward compatibility)
Route::middleware(['auth:sanctum'])->prefix('payments')->group(function () {
    Route::get('/methods-legacy', [\App\Http\Controllers\PaymentController::class, 'getPaymentMethods']);
    Route::post('/initiate-legacy', [\App\Http\Controllers\PaymentController::class, 'initiatePayment']);
    Route::get('/history-legacy', [\App\Http\Controllers\PaymentController::class, 'getPaymentHistory']);
    Route::get('/{transaction}/status-legacy', [\App\Http\Controllers\PaymentController::class, 'getPaymentStatus']);
});

// Payment callback routes (public)
Route::prefix('payments')->group(function () {
    Route::get('/success', [\App\Http\Controllers\PaymentController::class, 'handlePaymentSuccess'])->name('payment.success');
    Route::get('/cancel', [\App\Http\Controllers\PaymentController::class, 'handlePaymentCancel'])->name('payment.cancel');
    Route::get('/paypal/success', [\App\Http\Controllers\PaymentController::class, 'handlePayPalSuccess'])->name('payment.paypal.success');
    Route::get('/paypal/cancel', [\App\Http\Controllers\PaymentController::class, 'handlePayPalCancel'])->name('payment.paypal.cancel');
});

// Webhook routes (public) with rate limiting
Route::middleware(['api.throttle:webhooks'])->prefix('webhooks')->group(function () {
    Route::post('/nowpayments', [\App\Http\Controllers\Api\WebhookController::class, 'handleNowPayments'])->name('webhooks.nowpayments');
    Route::post('/paypal', [\App\Http\Controllers\Api\WebhookController::class, 'handlePayPal'])->name('webhooks.paypal');
    Route::post('/payment-status', [\App\Http\Controllers\Api\WebhookController::class, 'handlePaymentStatusUpdate'])->name('webhooks.payment-status');
    
    // Legacy webhook routes
    Route::post('/nowpayments-legacy', [\App\Http\Controllers\WebhookController::class, 'handleNowPayments'])->name('webhooks.nowpayments.legacy');
    Route::post('/paypal-legacy', [\App\Http\Controllers\WebhookController::class, 'handlePayPal'])->name('webhooks.paypal.legacy');
});

// Role and Permission Management Routes
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // Role management routes
    Route::prefix('roles')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\RoleController::class, 'index'])
            ->middleware('permission:admin.manage_permissions');
        Route::get('/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'show'])
            ->middleware('permission:admin.manage_permissions');
        Route::post('/{role}/permissions/assign', [\App\Http\Controllers\Admin\RoleController::class, 'assignPermissions'])
            ->middleware('permission:admin.manage_permissions');
        Route::delete('/{role}/permissions/remove', [\App\Http\Controllers\Admin\RoleController::class, 'removePermissions'])
            ->middleware('permission:admin.manage_permissions');
        Route::put('/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'updatePermissions'])
            ->middleware('permission:admin.manage_permissions');
    });

    // User permission management routes
    Route::prefix('users/{user}/permissions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\UserPermissionController::class, 'show'])
            ->middleware('role_or_permission:admin,operator|users.view');
        Route::post('/role', [\App\Http\Controllers\Admin\UserPermissionController::class, 'assignRole'])
            ->middleware('role_or_permission:admin,operator|users.manage_roles');
        Route::post('/grant', [\App\Http\Controllers\Admin\UserPermissionController::class, 'grantPermission'])
            ->middleware('permission:admin.manage_permissions');
        Route::post('/revoke', [\App\Http\Controllers\Admin\UserPermissionController::class, 'revokePermission'])
            ->middleware('permission:admin.manage_permissions');
        Route::delete('/remove', [\App\Http\Controllers\Admin\UserPermissionController::class, 'removePermissionAssignment'])
            ->middleware('permission:admin.manage_permissions');
        Route::put('/bulk', [\App\Http\Controllers\Admin\UserPermissionController::class, 'bulkUpdatePermissions'])
            ->middleware('permission:admin.manage_permissions');
    });
});