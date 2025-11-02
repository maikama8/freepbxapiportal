<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        // Convert string parameters to integers
        $maxAttempts = (int) $maxAttempts;
        $decayMinutes = (int) $decayMinutes;

        // Check if rate limit is exceeded
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            // Log rate limit exceeded
            Log::warning('API rate limit exceeded', [
                'key' => $key,
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'retry_after' => $retryAfter
            ]);

            return $this->buildRateLimitResponse($maxAttempts, $retryAfter, $decayMinutes);
        }

        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders(
            $response,
            $maxAttempts,
            RateLimiter::retriesLeft($key, $maxAttempts),
            RateLimiter::availableIn($key)
        );
    }

    /**
     * Resolve the request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        
        if ($user) {
            // For authenticated users, use user ID
            return 'api_rate_limit:user:' . $user->id;
        }
        
        // For unauthenticated requests, use IP address
        return 'api_rate_limit:ip:' . $request->ip();
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildRateLimitResponse(int $maxAttempts, int $retryAfter, int $decayMinutes): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'max_attempts' => $maxAttempts,
                'retry_after_seconds' => $retryAfter,
                'window_minutes' => $decayMinutes
            ]
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, int $maxAttempts, int $remaining, int $retryAfter): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);

        return $response;
    }
}