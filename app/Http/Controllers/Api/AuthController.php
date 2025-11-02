<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PasswordHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(LoginRequest $request): JsonResponse
    {

        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            AuditLog::log('login_attempt_locked', $user, null, null, null, $request->ip(), $request->userAgent());
            
            return response()->json([
                'success' => false,
                'message' => 'Account is locked. Please try again later.',
                'locked_until' => $user->locked_until,
            ], 423);
        }

        // Check if account is active
        if (!$user->isActive()) {
            AuditLog::log('login_attempt_inactive', $user, null, null, null, $request->ip(), $request->userAgent());
            
            return response()->json([
                'success' => false,
                'message' => 'Account is not active',
            ], 403);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            $user->incrementFailedLogins();
            
            AuditLog::log('login_failed', $user, null, null, [
                'reason' => 'invalid_password',
                'attempts' => $user->failed_login_attempts,
            ], $request->ip(), $request->userAgent());

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'attempts_remaining' => max(0, config('voip.security.account_lockout.max_attempts', 3) - $user->failed_login_attempts),
            ], 401);
        }

        // Reset failed login attempts on successful login
        $user->resetFailedLogins();
        $user->updateLastLogin($request->ip());

        // Create token
        $deviceName = $request->device_name ?? $request->userAgent() ?? 'Unknown Device';
        $token = $user->createToken($deviceName, $this->getTokenAbilities($user));

        // Log successful login
        AuditLog::log('login_success', $user, null, null, [
            'device_name' => $deviceName,
            'ip_address' => $request->ip(),
        ], $request->ip(), $request->userAgent());

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->formatUserResponse($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ],
        ]);
    }

    /**
     * Register new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'customer', // Default role
            'account_type' => $request->account_type,
            'balance' => $request->account_type === 'prepaid' ? 0 : 0,
            'credit_limit' => $request->account_type === 'postpaid' ? config('voip.platform.default_credit_limit', 100) : 0,
            'status' => 'active',
            'timezone' => $request->timezone ?? config('app.timezone', 'UTC'),
            'currency' => $request->currency ?? config('voip.platform.default_currency', 'USD'),
            'sip_username' => $this->generateSipUsername($request->name),
            'sip_password' => $this->generateSipPassword(),
        ]);

        // Log user creation
        AuditLog::log('user_registered', $user, $user, null, [
            'account_type' => $user->account_type,
            'role' => $user->role,
        ], $request->ip(), $request->userAgent());

        // Create token
        $deviceName = $request->device_name ?? $request->userAgent() ?? 'Registration Device';
        $token = $user->createToken($deviceName, $this->getTokenAbilities($user));

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => $this->formatUserResponse($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ],
        ], 201);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Log logout
        AuditLog::log('logout', $user, null, null, null, $request->ip(), $request->userAgent());

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke all tokens
        $user->tokens()->delete();

        // Log logout from all devices
        AuditLog::log('logout_all_devices', $user, null, null, null, $request->ip(), $request->userAgent());

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUserResponse($request->user()),
            ],
        ]);
    }

    /**
     * Refresh token (create new token and revoke current)
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();
        
        // Create new token with same abilities
        $deviceName = $currentToken->name ?? 'Refreshed Token';
        $newToken = $user->createToken($deviceName, $currentToken->abilities);
        
        // Revoke current token
        $currentToken->delete();

        // Log token refresh
        AuditLog::log('token_refreshed', $user, null, null, [
            'old_token_id' => $currentToken->id,
            'new_token_id' => $newToken->accessToken->id,
        ], $request->ip(), $request->userAgent());

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $newToken->accessToken->expires_at,
            ],
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        // Add current password to history before updating
        PasswordHistory::addPasswordToHistory($user->id, $user->password);

        // Update password
        $newPasswordHash = Hash::make($request->password);
        $user->update([
            'password' => $newPasswordHash,
        ]);

        // Log password change
        AuditLog::log('password_changed', $user, $user, null, null, $request->ip(), $request->userAgent());

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get token abilities based on user role
     */
    private function getTokenAbilities(User $user): array
    {
        $baseAbilities = ['user:read'];

        switch ($user->role) {
            case 'admin':
                return ['*']; // All abilities
            case 'operator':
                return array_merge($baseAbilities, [
                    'users:read', 'users:write',
                    'calls:read', 'calls:write',
                    'billing:read',
                    'reports:read',
                ]);
            case 'customer':
            default:
                return array_merge($baseAbilities, [
                    'account:read', 'account:write',
                    'calls:read', 'calls:write',
                    'payments:write',
                ]);
        }
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'account_type' => $user->account_type,
            'balance' => $user->balance,
            'credit_limit' => $user->credit_limit,
            'status' => $user->status,
            'timezone' => $user->timezone,
            'currency' => $user->currency,
            'sip_username' => $user->sip_username,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Generate unique SIP username
     */
    private function generateSipUsername(string $name): string
    {
        $base = strtolower(str_replace(' ', '_', $name));
        $base = preg_replace('/[^a-z0-9_]/', '', $base);
        
        $counter = 1;
        $username = $base;
        
        while (User::where('sip_username', $username)->exists()) {
            $username = $base . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Generate secure SIP password
     */
    private function generateSipPassword(): string
    {
        return \Str::random(16);
    }
}