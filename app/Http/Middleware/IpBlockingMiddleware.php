<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IpBlockingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ipAddress = $request->ip();

        // Check if IP is blocked
        if (Cache::has("blocked_ip:{$ipAddress}")) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Your IP address has been temporarily blocked due to suspicious activity.',
                'error_code' => 'IP_BLOCKED'
            ], 429);
        }

        // Check if IP is in whitelist (if configured)
        $whitelist = config('voip.security.ip_whitelist', []);
        if (!empty($whitelist) && !in_array($ipAddress, $whitelist)) {
            // Check if this is a private/local IP that should be allowed
            if (!$this->isPrivateIp($ipAddress)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Your IP address is not in the allowed list.',
                    'error_code' => 'IP_NOT_WHITELISTED'
                ], 403);
            }
        }

        // Check if IP is in blacklist
        $blacklist = config('voip.security.ip_blacklist', []);
        if (in_array($ipAddress, $blacklist)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Your IP address has been blocked.',
                'error_code' => 'IP_BLACKLISTED'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if IP address is private/local
     */
    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}