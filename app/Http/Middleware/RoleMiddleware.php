<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return $this->unauthorized($request);
        }

        $user = $request->user();

        // Check if user has any of the required roles
        if (!in_array($user->role, $roles)) {
            return $this->forbidden($request, $roles);
        }

        // Check if account is active
        if (!$user->isActive()) {
            return $this->accountInactive($request);
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
    private function forbidden(Request $request, array $requiredRoles): Response
    {
        // Log unauthorized access attempt
        \App\Models\AuditLog::log('unauthorized_role_access_attempt', $request->user(), null, null, [
            'required_roles' => $requiredRoles,
            'user_role' => $request->user()->role,
            'route' => $request->route()?->getName(),
            'url' => $request->url(),
        ], $request->ip(), $request->userAgent());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions',
                'required_roles' => $requiredRoles,
                'user_role' => $request->user()->role,
            ], 403);
        }

        abort(403, 'Insufficient permissions');
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
