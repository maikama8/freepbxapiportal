<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Show the registration form
     */
    public function showRegistrationForm(): View|RedirectResponse
    {
        // Redirect if user is already authenticated
        if (Auth::check()) {
            $user = Auth::user();
            return redirect($this->redirectPath($user));
        }
        
        return view('auth.register');
    }

    /**
     * Handle registration request
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        // Check if user is already authenticated
        if (Auth::check()) {
            $user = Auth::user();
            return redirect($this->redirectPath($user));
        }

        // Generate unique SIP username
        $sipUsername = $this->generateSipUsername();

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'customer', // Default role for registration
            'account_type' => $request->account_type ?? 'prepaid', // Default to prepaid
            'status' => 'active',
            'balance' => $request->account_type === 'prepaid' ? 0.00 : null,
            'credit_limit' => $request->account_type === 'postpaid' ? 100.00 : null,
        ]);

        // Create primary SIP account for the user
        $user->sipAccounts()->create([
            'sip_username' => $sipUsername,
            'sip_password' => $this->generateSipPassword(),
            'sip_context' => 'from-internal',
            'display_name' => $request->name,
            'status' => 'active',
            'is_primary' => true,
            'voicemail_enabled' => true,
            'voicemail_email' => $request->email,
            'call_forward_enabled' => false,
        ]);

        // Log the registration
        AuditLog::log('user_registered', $user, null, null, [
            'account_type' => $user->account_type,
            'sip_username' => $user->sip_username,
        ], $request->ip(), $request->userAgent());

        // Auto-login the user
        Auth::login($user);

        // Log successful login after registration
        AuditLog::log('web_login_success', $user, null, null, [
            'auto_login_after_registration' => true,
            'ip_address' => $request->ip(),
        ], $request->ip(), $request->userAgent());

        return redirect($this->redirectPath($user))->with('success', 'Registration successful! Welcome to the VoIP Platform.');
    }

    /**
     * Generate a unique SIP username
     */
    private function generateSipUsername(): string
    {
        return \App\Models\SipAccount::getNextSipUsername();
    }

    /**
     * Generate a secure SIP password
     */
    private function generateSipPassword(): string
    {
        return \App\Models\SipAccount::generateSipPassword();
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