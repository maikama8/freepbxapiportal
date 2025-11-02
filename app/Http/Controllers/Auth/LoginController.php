<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return back()->withErrors([
                'email' => 'These credentials do not match our records.',
            ])->withInput($request->only('email'));
        }

        // Check if account is locked
        if ($user->isLocked()) {
            AuditLog::log('login_attempt_locked', $user, null, null, null, $request->ip(), $request->userAgent());
            
            return back()->withErrors([
                'email' => 'Account is locked. Please try again later.',
            ])->withInput($request->only('email'));
        }

        // Check if account is active
        if (!$user->isActive()) {
            AuditLog::log('login_attempt_inactive', $user, null, null, null, $request->ip(), $request->userAgent());
            
            return back()->withErrors([
                'email' => 'Account is not active.',
            ])->withInput($request->only('email'));
        }

        // Attempt login
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            $user->resetFailedLogins();
            $user->updateLastLogin($request->ip());

            // Log successful login
            AuditLog::log('web_login_success', $user, null, null, [
                'remember' => $remember,
                'ip_address' => $request->ip(),
            ], $request->ip(), $request->userAgent());

            return redirect()->intended($this->redirectPath($user));
        }

        // Login failed - increment failed attempts
        $user->incrementFailedLogins();
        
        AuditLog::log('web_login_failed', $user, null, null, [
            'reason' => 'invalid_password',
            'attempts' => $user->failed_login_attempts,
        ], $request->ip(), $request->userAgent());

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        
        // Log logout
        if ($user) {
            AuditLog::log('web_logout', $user, null, null, null, $request->ip(), $request->userAgent());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'You have been logged out successfully.');
    }

    /**
     * Get the redirect path based on user role
     */
    private function redirectPath(User $user): string
    {
        switch ($user->role) {
            case 'admin':
                return '/admin/dashboard';
            case 'operator':
                return '/operator/dashboard';
            case 'customer':
            default:
                return '/dashboard';
        }
    }
}