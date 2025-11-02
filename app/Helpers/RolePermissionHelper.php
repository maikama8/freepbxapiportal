<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionHelper
{
    /**
     * Check if user can access admin features
     */
    public static function canAccessAdmin(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('admin.dashboard');
    }

    /**
     * Check if user can manage other users
     */
    public static function canManageUsers(User $user): bool
    {
        return $user->hasAnyPermission(['users.view', 'users.create', 'users.edit', 'users.delete']);
    }

    /**
     * Check if user can manage billing
     */
    public static function canManageBilling(User $user): bool
    {
        return $user->hasAnyPermission(['billing.manage_rates', 'billing.view_all_invoices', 'billing.generate_invoices']);
    }

    /**
     * Check if user can make calls
     */
    public static function canMakeCalls(User $user): bool
    {
        return $user->hasPermission('calls.make') && $user->isActive();
    }

    /**
     * Check if user can view call history
     */
    public static function canViewCallHistory(User $user, ?User $targetUser = null): bool
    {
        // Users can always view their own call history
        if ($targetUser && $user->id === $targetUser->id) {
            return $user->hasPermission('calls.view_history');
        }

        // Check if user can view all call history
        return $user->hasPermission('calls.view_all_history');
    }

    /**
     * Check if user can process payments
     */
    public static function canProcessPayments(User $user): bool
    {
        return $user->hasPermission('payments.make') && $user->isActive();
    }

    /**
     * Check if user can view financial reports
     */
    public static function canViewFinancialReports(User $user): bool
    {
        return $user->hasAnyPermission(['reports.view_financial', 'reports.view_usage']);
    }

    /**
     * Get user's dashboard permissions
     */
    public static function getDashboardPermissions(User $user): array
    {
        return [
            'can_access_admin' => self::canAccessAdmin($user),
            'can_manage_users' => self::canManageUsers($user),
            'can_manage_billing' => self::canManageBilling($user),
            'can_make_calls' => self::canMakeCalls($user),
            'can_view_call_history' => self::canViewCallHistory($user),
            'can_process_payments' => self::canProcessPayments($user),
            'can_view_reports' => self::canViewFinancialReports($user),
            'can_view_balance' => $user->hasPermission('account.view_balance'),
            'can_edit_profile' => $user->hasPermission('account.edit_profile'),
        ];
    }

    /**
     * Get navigation menu items based on user permissions
     */
    public static function getNavigationMenu(User $user): array
    {
        $menu = [];

        // Dashboard (always available for authenticated users)
        $menu[] = [
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'dashboard',
            'permission' => null,
        ];

        // Calls section
        if (self::canMakeCalls($user) || self::canViewCallHistory($user)) {
            $callsSubmenu = [];
            
            if (self::canMakeCalls($user)) {
                $callsSubmenu[] = [
                    'name' => 'Make Call',
                    'route' => 'calls.make',
                    'icon' => 'phone',
                ];
            }
            
            if (self::canViewCallHistory($user)) {
                $callsSubmenu[] = [
                    'name' => 'Call History',
                    'route' => 'calls.history',
                    'icon' => 'history',
                ];
            }

            $menu[] = [
                'name' => 'Calls',
                'icon' => 'phone',
                'submenu' => $callsSubmenu,
            ];
        }

        // Billing section
        if ($user->hasAnyPermission(['billing.view_rates', 'billing.view_invoices', 'billing.manage_rates'])) {
            $billingSubmenu = [];
            
            if ($user->hasPermission('billing.view_rates')) {
                $billingSubmenu[] = [
                    'name' => 'View Rates',
                    'route' => 'billing.rates',
                    'icon' => 'money',
                ];
            }
            
            if ($user->hasPermission('billing.view_invoices')) {
                $billingSubmenu[] = [
                    'name' => 'Invoices',
                    'route' => 'billing.invoices',
                    'icon' => 'receipt',
                ];
            }
            
            if ($user->hasPermission('billing.manage_rates')) {
                $billingSubmenu[] = [
                    'name' => 'Manage Rates',
                    'route' => 'admin.billing.rates',
                    'icon' => 'settings',
                ];
            }

            $menu[] = [
                'name' => 'Billing',
                'icon' => 'credit-card',
                'submenu' => $billingSubmenu,
            ];
        }

        // Payments section
        if ($user->hasAnyPermission(['payments.make', 'payments.view_history'])) {
            $paymentsSubmenu = [];
            
            if (self::canProcessPayments($user)) {
                $paymentsSubmenu[] = [
                    'name' => 'Add Funds',
                    'route' => 'payments.add',
                    'icon' => 'plus',
                ];
            }
            
            if ($user->hasPermission('payments.view_history')) {
                $paymentsSubmenu[] = [
                    'name' => 'Payment History',
                    'route' => 'payments.history',
                    'icon' => 'history',
                ];
            }

            $menu[] = [
                'name' => 'Payments',
                'icon' => 'wallet',
                'submenu' => $paymentsSubmenu,
            ];
        }

        // Admin section
        if (self::canAccessAdmin($user)) {
            $adminSubmenu = [];
            
            if (self::canManageUsers($user)) {
                $adminSubmenu[] = [
                    'name' => 'Users',
                    'route' => 'admin.users',
                    'icon' => 'users',
                ];
            }
            
            if ($user->hasPermission('admin.manage_permissions')) {
                $adminSubmenu[] = [
                    'name' => 'Roles & Permissions',
                    'route' => 'admin.roles',
                    'icon' => 'shield',
                ];
            }
            
            if ($user->hasPermission('admin.system_settings')) {
                $adminSubmenu[] = [
                    'name' => 'System Settings',
                    'route' => 'admin.settings',
                    'icon' => 'cog',
                ];
            }
            
            if ($user->hasPermission('admin.view_logs')) {
                $adminSubmenu[] = [
                    'name' => 'Audit Logs',
                    'route' => 'admin.logs',
                    'icon' => 'file-text',
                ];
            }

            if (!empty($adminSubmenu)) {
                $menu[] = [
                    'name' => 'Administration',
                    'icon' => 'shield',
                    'submenu' => $adminSubmenu,
                ];
            }
        }

        // Reports section
        if (self::canViewFinancialReports($user)) {
            $reportsSubmenu = [];
            
            if ($user->hasPermission('reports.view_usage')) {
                $reportsSubmenu[] = [
                    'name' => 'Usage Reports',
                    'route' => 'reports.usage',
                    'icon' => 'bar-chart',
                ];
            }
            
            if ($user->hasPermission('reports.view_financial')) {
                $reportsSubmenu[] = [
                    'name' => 'Financial Reports',
                    'route' => 'reports.financial',
                    'icon' => 'dollar-sign',
                ];
            }

            $menu[] = [
                'name' => 'Reports',
                'icon' => 'pie-chart',
                'submenu' => $reportsSubmenu,
            ];
        }

        // Account section (always available)
        $menu[] = [
            'name' => 'Account',
            'icon' => 'user',
            'submenu' => [
                [
                    'name' => 'Profile',
                    'route' => 'account.profile',
                    'icon' => 'user',
                ],
                [
                    'name' => 'Security',
                    'route' => 'account.security',
                    'icon' => 'lock',
                ],
            ],
        ];

        return $menu;
    }

    /**
     * Get role-specific default permissions
     */
    public static function getRoleDefaults(string $role): array
    {
        $defaults = [
            'admin' => [
                'dashboard_widgets' => ['users', 'calls', 'revenue', 'system_health'],
                'default_view' => 'admin.dashboard',
                'can_impersonate' => true,
                'max_api_calls_per_minute' => 1000,
            ],
            'operator' => [
                'dashboard_widgets' => ['calls', 'customers', 'support_tickets'],
                'default_view' => 'operator.dashboard',
                'can_impersonate' => false,
                'max_api_calls_per_minute' => 500,
            ],
            'customer' => [
                'dashboard_widgets' => ['balance', 'recent_calls', 'quick_dial'],
                'default_view' => 'customer.dashboard',
                'can_impersonate' => false,
                'max_api_calls_per_minute' => 100,
            ],
        ];

        return $defaults[$role] ?? [];
    }

    /**
     * Check if permission is critical (cannot be revoked from admin)
     */
    public static function isCriticalPermission(string $permission): bool
    {
        $criticalPermissions = [
            'admin.dashboard',
            'admin.manage_permissions',
            'account.view_profile',
            'account.edit_profile',
        ];

        return in_array($permission, $criticalPermissions);
    }

    /**
     * Get permission dependencies (permissions that require other permissions)
     */
    public static function getPermissionDependencies(): array
    {
        return [
            'users.delete' => ['users.view', 'users.edit'],
            'billing.generate_invoices' => ['billing.view_invoices'],
            'payments.manage_gateways' => ['payments.view_all_history'],
            'admin.system_settings' => ['admin.dashboard'],
            'reports.export' => ['reports.view_usage', 'reports.view_financial'],
        ];
    }

    /**
     * Validate permission dependencies
     */
    public static function validatePermissionDependencies(array $permissions): array
    {
        $dependencies = self::getPermissionDependencies();
        $errors = [];

        foreach ($permissions as $permission) {
            if (isset($dependencies[$permission])) {
                $requiredPermissions = $dependencies[$permission];
                $missingPermissions = array_diff($requiredPermissions, $permissions);
                
                if (!empty($missingPermissions)) {
                    $errors[$permission] = "Requires permissions: " . implode(', ', $missingPermissions);
                }
            }
        }

        return $errors;
    }
}