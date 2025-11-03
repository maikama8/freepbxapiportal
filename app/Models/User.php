<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Casts\EncryptedCast;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'account_type',
        'balance',
        'credit_limit',
        'status',
        'timezone',
        'currency',
        'sip_username',
        'sip_password',
        'sip_context',
        'extension_status',
        'freepbx_extension_id',
        'codec_preferences',
        'call_forward_number',
        'call_forward_enabled',
        'voicemail_enabled',
        'voicemail_email',
        'extension',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'sip_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:4',
            'credit_limit' => 'decimal:4',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'sip_password' => EncryptedCast::class,
            'phone' => EncryptedCast::class,
            'codec_preferences' => 'json',
            'call_forward_enabled' => 'boolean',
            'voicemail_enabled' => 'boolean',
        ];
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Check role permissions
        $rolePermission = \DB::table('role_permissions')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('role_permissions.role', $this->role)
            ->where('permissions.name', $permission)
            ->exists();

        if ($rolePermission) {
            // Check if user has explicitly denied this permission
            $userPermission = $this->userPermissions()
                ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
                ->where('permissions.name', $permission)
                ->first();

            return $userPermission ? $userPermission->granted : true;
        }

        // Check if user has explicitly granted this permission
        $userPermission = $this->userPermissions()
            ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->where('permissions.name', $permission)
            ->where('user_permissions.granted', true)
            ->exists();

        return $userPermission;
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is customer
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Check if user is operator
     */
    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            return $this->role === $roles;
        }

        return in_array($this->role, $roles);
    }

    /**
     * Get user's SIP accounts
     */
    public function sipAccounts()
    {
        return $this->hasMany(SipAccount::class);
    }

    /**
     * Get user's primary SIP account
     */
    public function primarySipAccount()
    {
        return $this->hasOne(SipAccount::class)->where('is_primary', true);
    }

    /**
     * Get user's active SIP accounts
     */
    public function activeSipAccounts()
    {
        return $this->hasMany(SipAccount::class)->where('status', 'active');
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is locked
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked' || 
               ($this->locked_until && $this->locked_until->isFuture());
    }

    /**
     * Check if account is prepaid
     */
    public function isPrepaid(): bool
    {
        return $this->account_type === 'prepaid';
    }

    /**
     * Check if account is postpaid
     */
    public function isPostpaid(): bool
    {
        return $this->account_type === 'postpaid';
    }

    /**
     * Check if user has sufficient balance for amount
     */
    public function hasSufficientBalance(float $amount): bool
    {
        if ($this->isPrepaid()) {
            return $this->balance >= $amount;
        }
        
        // For postpaid, check against credit limit
        return ($this->balance + $this->credit_limit) >= $amount;
    }

    /**
     * Deduct amount from balance
     */
    public function deductBalance(float $amount): bool
    {
        if (!$this->hasSufficientBalance($amount)) {
            return false;
        }

        $this->balance -= $amount;
        $this->save();
        
        return true;
    }

    /**
     * Add amount to balance
     */
    public function addBalance(float $amount): void
    {
        $this->balance += $amount;
        $this->save();
    }

    /**
     * Increment failed login attempts
     */
    public function incrementFailedLogins(): void
    {
        $this->failed_login_attempts++;
        
        $maxAttempts = config('voip.security.account_lockout.max_attempts', 3);
        if ($this->failed_login_attempts >= $maxAttempts) {
            $lockoutDuration = config('voip.security.account_lockout.lockout_duration', 900);
            $this->locked_until = now()->addSeconds($lockoutDuration);
            $this->status = 'locked';
        }
        
        $this->save();
    }

    /**
     * Reset failed login attempts
     */
    public function resetFailedLogins(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        if ($this->status === 'locked') {
            $this->status = 'active';
        }
        $this->save();
    }

    /**
     * Update last login information
     */
    public function updateLastLogin(string $ipAddress): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ipAddress;
        $this->save();
    }

    /**
     * Get user permissions relationship
     */
    public function userPermissions()
    {
        return $this->hasMany(\App\Models\UserPermission::class);
    }

    /**
     * Get audit logs relationship
     */
    public function auditLogs()
    {
        return $this->hasMany(\App\Models\AuditLog::class);
    }

    /**
     * Get call records relationship
     */
    public function callRecords()
    {
        return $this->hasMany(\App\Models\CallRecord::class);
    }

    /**
     * Get balance transactions relationship
     */
    public function balanceTransactions()
    {
        return $this->hasMany(\App\Models\BalanceTransaction::class);
    }

    /**
     * Get invoices relationship
     */
    public function invoices()
    {
        return $this->hasMany(\App\Models\Invoice::class);
    }

    /**
     * Get payment transactions relationship
     */
    public function paymentTransactions()
    {
        return $this->hasMany(\App\Models\PaymentTransaction::class);
    }

    /**
     * Get DID numbers relationship
     */
    public function didNumbers()
    {
        return $this->hasMany(\App\Models\DidNumber::class);
    }

    /**
     * Generate SIP credentials
     */
    public function generateSipCredentials(): array
    {
        if (!$this->sip_username) {
            $this->sip_username = $this->generateSipUsername();
        }
        
        if (!$this->sip_password) {
            $this->sip_password = $this->generateSipPassword();
        }
        
        $this->save();
        
        return [
            'username' => $this->sip_username,
            'password' => $this->sip_password,
            'context' => $this->sip_context ?: 'from-internal'
        ];
    }

    /**
     * Generate unique SIP username (extension number)
     */
    protected function generateSipUsername(): string
    {
        $startRange = \App\Models\SystemSetting::get('extension_range_start', 1000);
        $endRange = \App\Models\SystemSetting::get('extension_range_end', 9999);
        
        do {
            $extension = rand($startRange, $endRange);
        } while (static::where('sip_username', $extension)->exists());
        
        return (string) $extension;
    }

    /**
     * Generate secure SIP password
     */
    protected function generateSipPassword(): string
    {
        return \Str::random(12);
    }

    /**
     * Get SIP server configuration
     */
    public function getSipServerConfig(): array
    {
        return [
            'host' => \App\Models\SystemSetting::get('sip_server_host', '192.168.1.100'),
            'port' => \App\Models\SystemSetting::get('sip_server_port', 5060),
            'transport' => \App\Models\SystemSetting::get('sip_server_transport', 'UDP'),
            'username' => $this->sip_username,
            'password' => $this->sip_password,
            'context' => $this->sip_context ?: 'from-internal'
        ];
    }

    /**
     * Check if extension is active
     */
    public function isExtensionActive(): bool
    {
        return $this->extension_status === 'active';
    }

    /**
     * Activate extension
     */
    public function activateExtension(): void
    {
        $this->extension_status = 'active';
        $this->save();
    }

    /**
     * Deactivate extension
     */
    public function deactivateExtension(): void
    {
        $this->extension_status = 'inactive';
        $this->save();
    }

    /**
     * Suspend extension
     */
    public function suspendExtension(): void
    {
        $this->extension_status = 'suspended';
        $this->save();
    }
}
