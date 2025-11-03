<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\FreePBX\ExtensionService;
use App\Services\FreePBX\FreePBXApiClient;
use App\Exceptions\FreePBXApiException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RegisterController extends Controller
{
    protected ExtensionService $extensionService;

    public function __construct()
    {
        // Initialize FreePBX Extension Service
        $apiClient = new FreePBXApiClient(
            config('voip.freepbx.api_url'),
            config('voip.freepbx.username'),
            config('voip.freepbx.password'),
            config('voip.freepbx.version', 'v17'),
            config('voip.freepbx.client_id'),
            config('voip.freepbx.client_secret')
        );
        
        $this->extensionService = new ExtensionService($apiClient);
    }

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

        DB::beginTransaction();
        
        try {
            // Create the user first
            $accountType = $request->account_type ?? 'prepaid';
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'customer', // Default role for registration
                'account_type' => $accountType,
                'status' => 'active',
                'balance' => $accountType === 'prepaid' ? 0.00 : 0.00, // Both start with 0 balance
                'credit_limit' => $accountType === 'postpaid' ? 100.00 : 0.00, // Prepaid gets 0 credit limit
            ]);

            Log::info('User created, now creating FreePBX extension', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            // Create FreePBX extension and get the details
            $extensionDetails = $this->createFreePBXExtension($user);

            // Create primary SIP account with FreePBX details
            $sipAccount = $user->sipAccounts()->create([
                'sip_username' => $extensionDetails['extension'],
                'sip_password' => $extensionDetails['password'],
                'sip_context' => $extensionDetails['context'],
                'display_name' => $request->name,
                'status' => 'active',
                'is_primary' => true,
                'voicemail_enabled' => true,
                'voicemail_email' => $request->email,
                'call_forward_enabled' => false,
                'freepbx_extension_id' => $extensionDetails['extension'],
                'freepbx_settings' => [
                    'voicemail_password' => $extensionDetails['voicemail_password'],
                    'sip_server' => $extensionDetails['sip_server'],
                    'sip_port' => $extensionDetails['sip_port'],
                    'freepbx_response' => $extensionDetails['freepbx_response']
                ]
            ]);

            // Log the registration
            AuditLog::log('user_registered', $user, null, null, [
                'account_type' => $user->account_type,
                'sip_username' => $sipAccount->sip_username,
                'freepbx_extension' => $extensionDetails['extension'],
                'freepbx_created' => true
            ], $request->ip(), $request->userAgent());

            DB::commit();

            // Auto-login the user
            Auth::login($user);

            // Log successful login after registration
            AuditLog::log('web_login_success', $user, null, null, [
                'auto_login_after_registration' => true,
                'ip_address' => $request->ip(),
            ], $request->ip(), $request->userAgent());

            return redirect($this->redirectPath($user))->with('success', 
                'Registration successful! Your SIP extension ' . $extensionDetails['extension'] . ' has been created. Check your account settings for SIP details.');

        } catch (FreePBXApiException $e) {
            DB::rollBack();
            
            Log::error('FreePBX extension creation failed during registration', [
                'user_email' => $request->email,
                'error' => $e->getMessage(),
                'error_data' => $e->getErrorData()
            ]);

            return back()->withInput()->withErrors([
                'freepbx' => 'Failed to create your VoIP extension. Please try again or contact support if the problem persists.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Unexpected error during registration', [
                'user_email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput()->withErrors([
                'registration' => 'Registration failed: ' . $e->getMessage() . ' Please try again or contact support.'
            ]);
        }
    }

    /**
     * Create FreePBX extension for the user
     */
    private function createFreePBXExtension(User $user): array
    {
        try {
            // Check if we're in development mode and FreePBX is not available
            if (config('app.env') === 'local' && !$this->extensionService->testConnection()) {
                Log::info('FreePBX not available in development, creating mock extension', [
                    'user_id' => $user->id
                ]);
                
                return $this->createMockExtension($user);
            }

            // Create extension in FreePBX for production
            return $this->extensionService->createExtension($user);

        } catch (FreePBXApiException $e) {
            Log::error('FreePBX extension creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            // In development, fall back to mock extension
            if (config('app.env') === 'local') {
                Log::info('Falling back to mock extension in development', [
                    'user_id' => $user->id
                ]);
                
                return $this->createMockExtension($user);
            }
            
            throw $e;
        }
    }

    /**
     * Create a mock extension for development purposes
     */
    private function createMockExtension(User $user): array
    {
        $extension = \App\Models\SipAccount::getNextAvailableExtension();
        $password = 'dev_' . bin2hex(random_bytes(8));
        $vmPassword = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        return [
            'extension' => $extension,
            'password' => $password,
            'voicemail_password' => $vmPassword,
            'freepbx_response' => ['status' => 'mock_created_for_development'],
            'sip_server' => config('voip.freepbx.sip.domain', 'localhost'),
            'sip_port' => config('voip.freepbx.sip.port', 5060),
            'context' => config('voip.freepbx.default_context', 'from-internal')
        ];
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