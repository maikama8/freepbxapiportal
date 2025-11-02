<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AccountStatusMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return $next($request);
        }

        $user = $request->user();

        // Check if account is locked
        if ($user->isLocked()) {
            return $this->accountLocked($request, $user);
        }

        // Check if account is suspended
        if ($user->status === 'suspended') {
            return $this->accountSuspended($request);
        }

        // Check if account is inactive
        if ($user->status === 'inactive') {
            return $this->accountInactive($request);
        }

        return $next($request);
    }

    /**
     * Handle locked account
     */
    private function accountLocked(Request $request, $user): Response
    {
        // Log out the user
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is locked due to multiple failed login attempts',
                'locked_until' => $user->locked_until,
            ], 423);
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Your account is locked due to multiple failed login attempts. Please try again later.',
        ]);
    }

    /**
     * Handle suspended account
     */
    private function accountSuspended(Request $request): Response
    {
        // Log out the user
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is suspended',
            ], 403);
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Your account has been suspended. Please contact support.',
        ]);
    }

    /**
     * Handle inactive account
     */
    private function accountInactive(Request $request): Response
    {
        // Log out the user
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active',
            ], 403);
        }

        return redirect()->route('login')->withErrors([
            'email' => 'Your account is not active. Please contact support.',
        ]);
    }
}
