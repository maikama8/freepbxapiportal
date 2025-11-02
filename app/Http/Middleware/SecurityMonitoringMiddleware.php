<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SecurityMonitoringMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Monitor for suspicious activity after request is processed
        $this->monitorSuspiciousActivity($request, $response);

        return $response;
    }

    /**
     * Monitor for suspicious activity patterns
     */
    private function monitorSuspiciousActivity(Request $request, Response $response): void
    {
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Monitor failed login attempts from same IP
        if ($response->getStatusCode() === 401 && $request->is('api/auth/login')) {
            $this->trackFailedLoginsByIp($ipAddress);
        }

        // Monitor rapid API requests from same IP
        if ($request->is('api/*')) {
            $this->trackApiRequestRate($ipAddress);
        }

        // Monitor for user enumeration attempts
        if ($request->is('api/auth/*') && $response->getStatusCode() === 422) {
            $this->trackUserEnumerationAttempts($ipAddress);
        }

        // Monitor for SQL injection patterns
        $this->detectSqlInjectionAttempts($request);

        // Monitor for XSS attempts
        $this->detectXssAttempts($request);
    }

    /**
     * Track failed login attempts by IP address
     */
    private function trackFailedLoginsByIp(string $ipAddress): void
    {
        $key = "failed_logins_ip:{$ipAddress}";
        $attempts = Cache::get($key, 0) + 1;
        
        Cache::put($key, $attempts, now()->addMinutes(15));

        // Block IP after too many failed attempts
        $maxAttempts = config('voip.security.ip_lockout.max_attempts', 10);
        if ($attempts >= $maxAttempts) {
            $blockDuration = config('voip.security.ip_lockout.block_duration', 3600);
            Cache::put("blocked_ip:{$ipAddress}", true, now()->addSeconds($blockDuration));
            
            AuditLog::log('ip_blocked_suspicious_activity', null, null, null, [
                'ip_address' => $ipAddress,
                'failed_attempts' => $attempts,
                'reason' => 'excessive_failed_logins'
            ]);
        }
    }

    /**
     * Track API request rate by IP
     */
    private function trackApiRequestRate(string $ipAddress): void
    {
        $key = "api_requests_ip:{$ipAddress}";
        $requests = Cache::get($key, 0) + 1;
        
        Cache::put($key, $requests, now()->addMinute());

        // Alert on excessive API requests
        $maxRequests = config('voip.security.rate_limiting.max_requests_per_minute', 60);
        if ($requests > $maxRequests) {
            AuditLog::log('excessive_api_requests', null, null, null, [
                'ip_address' => $ipAddress,
                'requests_count' => $requests,
                'time_window' => '1_minute'
            ]);
        }
    }

    /**
     * Track user enumeration attempts
     */
    private function trackUserEnumerationAttempts(string $ipAddress): void
    {
        $key = "enumeration_attempts_ip:{$ipAddress}";
        $attempts = Cache::get($key, 0) + 1;
        
        Cache::put($key, $attempts, now()->addHour());

        // Alert on potential user enumeration
        if ($attempts > 20) {
            AuditLog::log('potential_user_enumeration', null, null, null, [
                'ip_address' => $ipAddress,
                'attempts' => $attempts,
                'time_window' => '1_hour'
            ]);
        }
    }

    /**
     * Detect SQL injection attempts
     */
    private function detectSqlInjectionAttempts(Request $request): void
    {
        $suspiciousPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
            '/script\s*>/i',
            '/\'\s*or\s*\'/i',
            '/\'\s*and\s*\'/i',
            '/--\s*$/m',
            '/\/\*.*\*\//s'
        ];

        $input = json_encode($request->all());
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                AuditLog::log('sql_injection_attempt', $request->user(), null, null, [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'pattern_matched' => $pattern,
                    'input_data' => $this->sanitizeLogData($input)
                ]);
                break;
            }
        }
    }

    /**
     * Detect XSS attempts
     */
    private function detectXssAttempts(Request $request): void
    {
        $suspiciousPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i'
        ];

        $input = json_encode($request->all());
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                AuditLog::log('xss_attempt', $request->user(), null, null, [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'pattern_matched' => $pattern,
                    'input_data' => $this->sanitizeLogData($input)
                ]);
                break;
            }
        }
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     */
    private function sanitizeLogData(string $data): string
    {
        // Remove potential passwords and sensitive data
        $data = preg_replace('/"password":\s*"[^"]*"/', '"password":"[REDACTED]"', $data);
        $data = preg_replace('/"current_password":\s*"[^"]*"/', '"current_password":"[REDACTED]"', $data);
        $data = preg_replace('/"password_confirmation":\s*"[^"]*"/', '"password_confirmation":"[REDACTED]"', $data);
        $data = preg_replace('/"sip_password":\s*"[^"]*"/', '"sip_password":"[REDACTED]"', $data);
        
        // Truncate if too long
        return strlen($data) > 1000 ? substr($data, 0, 1000) . '...[TRUNCATED]' : $data;
    }
}