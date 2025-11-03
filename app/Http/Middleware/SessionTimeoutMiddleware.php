<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeoutMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeout = 5 * 60; // 5 minutes in seconds
            $lastActivity = Session::get('last_activity', time());
            
            if (time() - $lastActivity > $timeout) {
                Auth::logout();
                Session::flush();
                Session::regenerate();
                
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired due to inactivity'], 401);
                }
                
                return redirect()->route('login')->with('status', 'Your session has expired due to inactivity. Please log in again.');
            }
            
            // Update last activity time
            Session::put('last_activity', time());
        }

        return $next($request);
    }
}