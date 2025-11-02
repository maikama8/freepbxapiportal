<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SessionSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check session timeout
        if (Auth::check()) {
            $this->checkSessionTimeout($request);
            $this->checkConcurrentSessions($request);
            $this->validateSessionIntegrity($request);
        }

        $response = $next($request);

        // Update session activity
        if (Auth::check()) {
            $this->updateSessionActivity($request);
        }

        return $response;
    }

    /**
     * Check if session has timed out
     */
    private function checkSessionTimeout(Request $request): void
    {
        $timeout = config('voip.security.session.timeout', 7200);
        $lastActivity = $request->session()->get('last_activity', time());

        if (time() - $lastActivity > $timeout) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            if ($request->expectsJson()) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'success' => false,
                        'message' => 'Session has expired due to inactivity.',
                        'error_code' => 'SESSION_EXPIRED'
                    ], 401)
                );
            }
        }
    }

    /**
     * Check for concurrent sessions
     */
    private function checkConcurrentSessions(Request $request): void
    {
        $user = Auth::user();
        $maxSessions = config('voip.security.session.concurrent_sessions', 3);
        $currentSessionId = $request->session()->getId();
        
        $sessionKey = "user_sessions:{$user->id}";
        $sessions = Cache::get($sessionKey, []);
        
        // Clean up expired sessions
        $activeSessions = [];
        foreach ($sessions as $sessionId => $data) {
            if ($data['expires_at'] > time()) {
                $activeSessions[$sessionId] = $data;
            }
        }
        
        // Add current session
        $activeSessions[$currentSessionId] = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => time(),
            'expires_at' => time() + config('voip.security.session.timeout', 7200),
        ];
        
        // Check if we exceed max sessions
        if (count($activeSessions) > $maxSessions) {
            // Remove oldest session
            uasort($activeSessions, function ($a, $b) {
                return $a['created_at'] <=> $b['created_at'];
            });
            
            $activeSessions = array_slice($activeSessions, -$maxSessions, null, true);
        }
        
        Cache::put($sessionKey, $activeSessions, now()->addHours(24));
    }

    /**
     * Validate session integrity
     */
    private function validateSessionIntegrity(Request $request): void
    {
        $user = Auth::user();
        
        // Check if user agent changed (potential session hijacking)
        $storedUserAgent = $request->session()->get('user_agent');
        $currentUserAgent = $request->userAgent();
        
        if ($storedUserAgent && $storedUserAgent !== $currentUserAgent) {
            \App\Models\AuditLog::log('session_user_agent_changed', $user, null, null, [
                'stored_user_agent' => $storedUserAgent,
                'current_user_agent' => $currentUserAgent,
                'ip_address' => $request->ip(),
            ]);
            
            // Optionally logout user for security
            if (config('voip.security.session.strict_user_agent', false)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                if ($request->expectsJson()) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        response()->json([
                            'success' => false,
                            'message' => 'Session invalidated due to security concerns.',
                            'error_code' => 'SESSION_SECURITY_VIOLATION'
                        ], 401)
                    );
                }
            }
        }
        
        // Check if IP address changed significantly
        $storedIp = $request->session()->get('ip_address');
        $currentIp = $request->ip();
        
        if ($storedIp && $storedIp !== $currentIp) {
            \App\Models\AuditLog::log('session_ip_changed', $user, null, null, [
                'stored_ip' => $storedIp,
                'current_ip' => $currentIp,
                'user_agent' => $currentUserAgent,
            ]);
            
            // Update stored IP
            $request->session()->put('ip_address', $currentIp);
        }
    }

    /**
     * Update session activity
     */
    private function updateSessionActivity(Request $request): void
    {
        $request->session()->put('last_activity', time());
        
        // Store user agent and IP on first request
        if (!$request->session()->has('user_agent')) {
            $request->session()->put('user_agent', $request->userAgent());
        }
        
        if (!$request->session()->has('ip_address')) {
            $request->session()->put('ip_address', $request->ip());
        }
    }
}