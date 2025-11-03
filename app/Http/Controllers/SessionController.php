<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    /**
     * Refresh the user session to prevent timeout
     */
    public function refreshSession(Request $request): JsonResponse
    {
        try {
            if (!auth()->check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Update session activity timestamp
            session(['last_activity' => time()]);
            
            // Regenerate session ID for security
            $request->session()->regenerate();

            return response()->json([
                'status' => 'success',
                'message' => 'Session refreshed successfully',
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            \Log::error('Session refresh failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to refresh session'
            ], 500);
        }
    }

    /**
     * Check session status
     */
    public function checkSession(Request $request): JsonResponse
    {
        try {
            if (!auth()->check()) {
                return response()->json([
                    'status' => 'expired',
                    'authenticated' => false
                ], 401);
            }

            $lastActivity = session('last_activity', time());
            $timeoutMinutes = config('session.lifetime', 120);
            $timeoutSeconds = $timeoutMinutes * 60;
            $timeRemaining = $timeoutSeconds - (time() - $lastActivity);

            return response()->json([
                'status' => 'active',
                'authenticated' => true,
                'time_remaining' => max(0, $timeRemaining),
                'expires_at' => $lastActivity + $timeoutSeconds
            ]);

        } catch (\Exception $e) {
            \Log::error('Session check failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check session status'
            ], 500);
        }
    }
}