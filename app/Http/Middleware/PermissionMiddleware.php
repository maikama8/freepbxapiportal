<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!$request->user()) {
            return $this->unauthorized($request);
        }

        $user = $request->user();

        // Check if account is active
        if (!$user->isActive()) {
            return $this->accountInactive($request);
        }

        // Check if user has any of the required permissions
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            // Log unauthorized access attempt
            AuditLog::log('unauthorized_access_attempt', $user, null, null, [
                'required_permissions' => $permissions,
                'user_role' => $user->role,
                'route' => $request->route()?->getName(),
                'url' => $request->url(),
            ], $request->ip(), $request->userAgent());

            return $this->forbidden($request, $permissions);
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
    private function forbidden(Request $request, array $requiredPermissions): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions',
                'required_permissions' => $requiredPermissions,
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
