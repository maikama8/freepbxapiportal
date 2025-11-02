<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;

class RoleOrPermissionMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * This middleware allows access if the user has ANY of the specified roles OR permissions.
     * Usage: 'role_or_permission:admin,operator|users.view,users.edit'
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $rolesOrPermissions): Response
    {
        if (!$request->user()) {
            return $this->unauthorized($request);
        }

        $user = $request->user();

        // Check if account is active
        if (!$user->isActive()) {
            return $this->accountInactive($request);
        }

        // Parse roles and permissions from parameter
        $parts = explode('|', $rolesOrPermissions);
        $roles = isset($parts[0]) ? explode(',', $parts[0]) : [];
        $permissions = isset($parts[1]) ? explode(',', $parts[1]) : [];

        $hasAccess = false;
        $accessReason = null;

        // Check roles first
        if (!empty($roles) && in_array($user->role, $roles)) {
            $hasAccess = true;
            $accessReason = "role:{$user->role}";
        }

        // Check permissions if no role match
        if (!$hasAccess && !empty($permissions)) {
            foreach ($permissions as $permission) {
                if ($user->hasPermission(trim($permission))) {
                    $hasAccess = true;
                    $accessReason = "permission:" . trim($permission);
                    break;
                }
            }
        }

        if (!$hasAccess) {
            // Log unauthorized access attempt
            AuditLog::log('unauthorized_access_attempt', $user, null, null, [
                'required_roles' => $roles,
                'required_permissions' => $permissions,
                'user_role' => $user->role,
                'route' => $request->route()?->getName(),
                'url' => $request->url(),
            ], $request->ip(), $request->userAgent());

            return $this->forbidden($request, $roles, $permissions);
        }

        // Log successful access for audit purposes (optional, can be disabled for performance)
        if (config('voip.security.log_successful_access', false)) {
            AuditLog::log('authorized_access', $user, null, null, [
                'access_reason' => $accessReason,
                'route' => $request->route()?->getName(),
                'url' => $request->url(),
            ], $request->ip(), $request->userAgent());
        }

        return $next($request);
    }

    /**
     * Handle unauthorized access
     */
    private function unauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Handle forbidden access
     */
    private function forbidden(Request $request, array $requiredRoles, array $requiredPermissions): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions',
                'required_roles' => $requiredRoles,
                'required_permissions' => $requiredPermissions,
                'user_role' => $request->user()->role,
            ], 403);
        }

        abort(403, 'You do not have permission to access this resource.');
    }

    /**
     * Handle inactive account
     */
    private function accountInactive(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active',
            ], 403);
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Your account is not active.',
        ]);
    }
}