<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiThrottleMiddleware
{
    /**
     * Rate limit configurations for different operation types
     */
    protected array $rateLimits = [
        'auth' => ['attempts' => 5, 'decay' => 1], // 5 attempts per minute for auth
        'calls' => ['attempts' => 10, 'decay' => 1], // 10 call operations per minute
        'payments' => ['attempts' => 5, 'decay' => 5], // 5 payment operations per 5 minutes
        'general' => ['attempts' => 60, 'decay' => 1], // 60 general API calls per minute
        'webhooks' => ['attempts' => 100, 'decay' => 1], // 100 webhook calls per minute
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'general'): Response
    {
        // Get rate limit configuration for the operation type
        $config = $this->rateLimits[$type] ?? $this->rateLimits['general'];
        $maxAttempts = $config['attempts'];
        $decayMinutes = $config['decay'];

        $key = $this->resolveRequestSignature($request, $type);

        // Check if rate limit is exceeded
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            // Log rate limit exceeded
            Log::warning('API rate limit exceeded', [
                'type' => $type,
                'key' => $key,
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'max_attempts' => $maxAttempts,
                'decay_minutes' => $decayMinutes,
                'retry_after' => $retryAfter
            ]);

            return $this->buildRateLimitResponse($type, $maxAttempts, $retryAfter, $decayMinutes);
        }

        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders(
            $response,
            $type,
            $maxAttempts,
            RateLimiter::retriesLeft($key, $maxAttempts),
            RateLimiter::availableIn($key)
        );
    }

    /**
     * Resolve the request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        $user = $request->user();
        
        if ($user) {
            // For authenticated users, use user ID and type
            return "api_throttle:{$type}:user:{$user->id}";
        }
        
        // For unauthenticated requests, use IP address and type
        return "api_throttle:{$type}:ip:" . $request->ip();
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(string $type, int $maxAttempts, int $retryAfter, int $decayMinutes): JsonResponse
    {
        $message = $this->getRateLimitMessage($type);
        
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'type' => $type,
                'max_attempts' => $maxAttempts,
                'window_minutes' => $decayMinutes,
                'retry_after_seconds' => $retryAfter,
                'retry_after_human' => $this->formatRetryAfter($retryAfter)
            ]
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            'X-RateLimit-Type' => $type,
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, string $type, int $maxAttempts, int $remaining, int $retryAfter): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            'X-RateLimit-Type' => $type,
        ]);

        return $response;
    }

    /**
     * Get appropriate rate limit message based on type
     */
    protected function getRateLimitMessage(string $type): string
    {
        return match ($type) {
            'auth' => 'Too many authentication attempts. Please try again later.',
            'calls' => 'Too many call operations. Please wait before making more calls.',
            'payments' => 'Too many payment requests. Please wait before initiating another payment.',
            'webhooks' => 'Too many webhook requests. Please reduce the frequency.',
            default => 'Too many API requests. Please try again later.',
        };
    }

    /**
     * Format retry after seconds into human readable format
     */
    protected function formatRetryAfter(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }
        
        $minutes = ceil($seconds / 60);
        return "{$minutes} minutes";
    }

    /**
     * Get rate limit configuration for a specific type
     */
    public function getRateLimitConfig(string $type): array
    {
        return $this->rateLimits[$type] ?? $this->rateLimits['general'];
    }

    /**
     * Update rate limit configuration (useful for dynamic configuration)
     */
    public function updateRateLimitConfig(string $type, int $attempts, int $decay): void
    {
        $this->rateLimits[$type] = ['attempts' => $attempts, 'decay' => $decay];
    }
}