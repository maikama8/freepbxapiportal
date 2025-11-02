<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'description' => 'View user list and details', 'category' => 'user_management'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'description' => 'Create new user accounts', 'category' => 'user_management'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'description' => 'Edit user account details', 'category' => 'user_management'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'description' => 'Delete user accounts', 'category' => 'user_management'],
            ['name' => 'users.manage_roles', 'display_name' => 'Manage User Roles', 'description' => 'Assign and modify user roles', 'category' => 'user_management'],
            
            // Account Management
            ['name' => 'account.view_profile', 'display_name' => 'View Profile', 'description' => 'View own profile information', 'category' => 'account'],
            ['name' => 'account.edit_profile', 'display_name' => 'Edit Profile', 'description' => 'Edit own profile information', 'category' => 'account'],
            ['name' => 'account.view_balance', 'display_name' => 'View Balance', 'description' => 'View account balance', 'category' => 'account'],
            ['name' => 'account.view_transactions', 'display_name' => 'View Transactions', 'description' => 'View transaction history', 'category' => 'account'],
            
            // Call Management
            ['name' => 'calls.make', 'display_name' => 'Make Calls', 'description' => 'Initiate VoIP calls', 'category' => 'calls'],
            ['name' => 'calls.view_history', 'display_name' => 'View Call History', 'description' => 'View own call history', 'category' => 'calls'],
            ['name' => 'calls.view_all_history', 'display_name' => 'View All Call History', 'description' => 'View all users call history', 'category' => 'calls'],
            ['name' => 'calls.manage', 'display_name' => 'Manage Calls', 'description' => 'Manage active calls and call routing', 'category' => 'calls'],
            
            // Billing Management
            ['name' => 'billing.view_rates', 'display_name' => 'View Rates', 'description' => 'View call rates and pricing', 'category' => 'billing'],
            ['name' => 'billing.manage_rates', 'display_name' => 'Manage Rates', 'description' => 'Create and modify call rates', 'category' => 'billing'],
            ['name' => 'billing.view_invoices', 'display_name' => 'View Invoices', 'description' => 'View own invoices', 'category' => 'billing'],
            ['name' => 'billing.view_all_invoices', 'display_name' => 'View All Invoices', 'description' => 'View all user invoices', 'category' => 'billing'],
            ['name' => 'billing.generate_invoices', 'display_name' => 'Generate Invoices', 'description' => 'Generate and send invoices', 'category' => 'billing'],
            
            // Payment Management
            ['name' => 'payments.make', 'display_name' => 'Make Payments', 'description' => 'Process payments to add balance', 'category' => 'payments'],
            ['name' => 'payments.view_history', 'display_name' => 'View Payment History', 'description' => 'View own payment history', 'category' => 'payments'],
            ['name' => 'payments.view_all_history', 'display_name' => 'View All Payment History', 'description' => 'View all payment transactions', 'category' => 'payments'],
            ['name' => 'payments.manage_gateways', 'display_name' => 'Manage Payment Gateways', 'description' => 'Configure payment gateway settings', 'category' => 'payments'],
            
            // System Administration
            ['name' => 'admin.dashboard', 'display_name' => 'Admin Dashboard', 'description' => 'Access administrative dashboard', 'category' => 'administration'],
            ['name' => 'admin.system_settings', 'display_name' => 'System Settings', 'description' => 'Manage system configuration', 'category' => 'administration'],
            ['name' => 'admin.view_logs', 'display_name' => 'View Logs', 'description' => 'View system and audit logs', 'category' => 'administration'],
            ['name' => 'admin.manage_permissions', 'display_name' => 'Manage Permissions', 'description' => 'Manage user permissions and roles', 'category' => 'administration'],
            
            // Reports
            ['name' => 'reports.view_usage', 'display_name' => 'View Usage Reports', 'description' => 'View usage and analytics reports', 'category' => 'reports'],
            ['name' => 'reports.view_financial', 'display_name' => 'View Financial Reports', 'description' => 'View financial and revenue reports', 'category' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'description' => 'Export reports in various formats', 'category' => 'reports'],
        ];

        foreach ($permissions as $permission) {
            \DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                array_merge($permission, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Define role permissions
        $rolePermissions = [
            'admin' => [
                // All permissions for admin
                'users.view', 'users.create', 'users.edit', 'users.delete', 'users.manage_roles',
                'account.view_profile', 'account.edit_profile', 'account.view_balance', 'account.view_transactions',
                'calls.make', 'calls.view_history', 'calls.view_all_history', 'calls.manage',
                'billing.view_rates', 'billing.manage_rates', 'billing.view_invoices', 'billing.view_all_invoices', 'billing.generate_invoices',
                'payments.make', 'payments.view_history', 'payments.view_all_history', 'payments.manage_gateways',
                'admin.dashboard', 'admin.system_settings', 'admin.view_logs', 'admin.manage_permissions',
                'reports.view_usage', 'reports.view_financial', 'reports.export',
            ],
            'operator' => [
                // Operator permissions
                'users.view', 'users.edit',
                'account.view_profile', 'account.edit_profile',
                'calls.view_all_history', 'calls.manage',
                'billing.view_rates', 'billing.view_all_invoices',
                'payments.view_all_history',
                'reports.view_usage',
            ],
            'customer' => [
                // Customer permissions
                'account.view_profile', 'account.edit_profile', 'account.view_balance', 'account.view_transactions',
                'calls.make', 'calls.view_history',
                'billing.view_rates', 'billing.view_invoices',
                'payments.make', 'payments.view_history',
            ],
        ];

        // Insert role permissions
        foreach ($rolePermissions as $role => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                $permission = \DB::table('permissions')->where('name', $permissionName)->first();
                if ($permission) {
                    \DB::table('role_permissions')->updateOrInsert(
                        ['role' => $role, 'permission_id' => $permission->id],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
