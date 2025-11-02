<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DocumentationController extends Controller
{
    /**
     * Get API documentation
     */
    public function index(Request $request): JsonResponse
    {
        $documentation = [
            'api_version' => '1.0',
            'base_url' => url('/api'),
            'authentication' => [
                'type' => 'Bearer Token (Laravel Sanctum)',
                'header' => 'Authorization: Bearer {token}',
                'description' => 'Obtain token via /api/auth/login endpoint'
            ],
            'rate_limits' => [
                'authentication' => '5 requests per minute',
                'calls' => '10 requests per minute',
                'payments' => '5 requests per 5 minutes',
                'general' => '60 requests per minute',
                'webhooks' => '100 requests per minute'
            ],
            'endpoints' => $this->getEndpointsDocumentation()
        ];

        return response()->json([
            'success' => true,
            'data' => $documentation
        ]);
    }

    /**
     * Get endpoints documentation
     */
    protected function getEndpointsDocumentation(): array
    {
        return [
            'authentication' => [
                'POST /api/auth/login' => [
                    'description' => 'Authenticate user and get access token',
                    'parameters' => [
                        'email' => 'string|required',
                        'password' => 'string|required',
                        'device_name' => 'string|optional'
                    ],
                    'response' => 'User object with access token'
                ],
                'POST /api/auth/register' => [
                    'description' => 'Register new customer account',
                    'parameters' => [
                        'name' => 'string|required',
                        'email' => 'string|required|unique',
                        'password' => 'string|required|confirmed',
                        'phone' => 'string|optional',
                        'account_type' => 'string|required|in:prepaid,postpaid'
                    ],
                    'response' => 'User object with access token'
                ],
                'POST /api/auth/logout' => [
                    'description' => 'Logout and revoke current token',
                    'authentication' => 'required',
                    'response' => 'Success message'
                ],
                'GET /api/auth/user' => [
                    'description' => 'Get authenticated user information',
                    'authentication' => 'required',
                    'response' => 'User object'
                ]
            ],
            'customer_operations' => [
                'GET /api/customer/account' => [
                    'description' => 'Get customer account information',
                    'authentication' => 'required',
                    'role' => 'customer',
                    'response' => 'Account details with balance and payment stats'
                ],
                'GET /api/customer/balance' => [
                    'description' => 'Get account balance information',
                    'authentication' => 'required',
                    'role' => 'customer',
                    'response' => 'Balance status and limits'
                ],
                'GET /api/customer/calls/history' => [
                    'description' => 'Get call history with pagination',
                    'authentication' => 'required',
                    'role' => 'customer',
                    'parameters' => [
                        'page' => 'integer|optional',
                        'per_page' => 'integer|optional|max:100',
                        'date_from' => 'date|optional',
                        'date_to' => 'date|optional',
                        'status' => 'string|optional',
                        'destination' => 'string|optional'
                    ],
                    'response' => 'Paginated call records'
                ],
                'POST /api/customer/calls/initiate' => [
                    'description' => 'Initiate a new call',
                    'authentication' => 'required',
                    'role' => 'customer',
                    'parameters' => [
                        'destination' => 'string|required',
                        'caller_id' => 'string|optional'
                    ],
                    'response' => 'Call initiation result with call ID'
                ],
                'GET /api/customer/calls/active' => [
                    'description' => 'Get active calls for the user',
                    'authentication' => 'required',
                    'role' => 'customer',
                    'response' => 'List of active calls'
                ]
            ],
            'payment_operations' => [
                'GET /api/payments/methods' => [
                    'description' => 'Get available payment methods',
                    'authentication' => 'required',
                    'response' => 'List of payment methods with details'
                ],
                'POST /api/payments/initiate' => [
                    'description' => 'Initiate a payment transaction',
                    'authentication' => 'required',
                    'parameters' => [
                        'amount' => 'numeric|required|min:0.01|max:10000',
                        'currency' => 'string|required|in:USD,EUR,GBP',
                        'gateway' => 'string|required|in:nowpayments,paypal',
                        'payment_method' => 'string|required'
                    ],
                    'response' => 'Payment transaction with payment URL'
                ],
                'GET /api/payments/history' => [
                    'description' => 'Get payment history with pagination',
                    'authentication' => 'required',
                    'parameters' => [
                        'page' => 'integer|optional',
                        'per_page' => 'integer|optional|max:100',
                        'status' => 'string|optional',
                        'gateway' => 'string|optional',
                        'date_from' => 'date|optional',
                        'date_to' => 'date|optional'
                    ],
                    'response' => 'Paginated payment transactions'
                ],
                'GET /api/payments/{id}/status' => [
                    'description' => 'Get payment transaction status',
                    'authentication' => 'required',
                    'response' => 'Payment transaction details'
                ]
            ],
            'webhooks' => [
                'POST /api/webhooks/nowpayments' => [
                    'description' => 'NowPayments webhook endpoint',
                    'authentication' => 'none',
                    'note' => 'Webhook signature verification required'
                ],
                'POST /api/webhooks/paypal' => [
                    'description' => 'PayPal webhook endpoint',
                    'authentication' => 'none',
                    'note' => 'Webhook signature verification required'
                ]
            ]
        ];
    }

    /**
     * Get rate limiting information
     */
    public function rateLimits(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'rate_limits' => [
                    'auth' => [
                        'description' => 'Authentication operations',
                        'limit' => '5 requests per minute',
                        'applies_to' => ['login', 'register', 'logout', 'password changes']
                    ],
                    'calls' => [
                        'description' => 'Call management operations',
                        'limit' => '10 requests per minute',
                        'applies_to' => ['call initiation', 'call termination', 'call status']
                    ],
                    'payments' => [
                        'description' => 'Payment operations',
                        'limit' => '5 requests per 5 minutes',
                        'applies_to' => ['payment initiation', 'payment cancellation', 'payment retry']
                    ],
                    'general' => [
                        'description' => 'General API operations',
                        'limit' => '60 requests per minute',
                        'applies_to' => ['account info', 'balance check', 'history queries']
                    ],
                    'webhooks' => [
                        'description' => 'Webhook endpoints',
                        'limit' => '100 requests per minute',
                        'applies_to' => ['payment webhooks', 'status updates']
                    ]
                ],
                'headers' => [
                    'X-RateLimit-Limit' => 'Maximum requests allowed in the time window',
                    'X-RateLimit-Remaining' => 'Remaining requests in current window',
                    'X-RateLimit-Reset' => 'Unix timestamp when the rate limit resets',
                    'X-RateLimit-Type' => 'Type of rate limit applied',
                    'Retry-After' => 'Seconds to wait before retrying (when limit exceeded)'
                ],
                'error_response' => [
                    'status_code' => 429,
                    'response' => [
                        'success' => false,
                        'message' => 'Too many requests. Please try again later.',
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'type' => 'auth|calls|payments|general|webhooks',
                            'max_attempts' => 'number',
                            'window_minutes' => 'number',
                            'retry_after_seconds' => 'number'
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get authentication guide
     */
    public function authGuide(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'authentication_flow' => [
                    'step_1' => [
                        'action' => 'Register or Login',
                        'endpoint' => 'POST /api/auth/login',
                        'payload' => [
                            'email' => 'user@example.com',
                            'password' => 'password123',
                            'device_name' => 'Mobile App'
                        ]
                    ],
                    'step_2' => [
                        'action' => 'Extract Token',
                        'description' => 'Get the token from response.data.token'
                    ],
                    'step_3' => [
                        'action' => 'Use Token',
                        'description' => 'Include in Authorization header',
                        'header' => 'Authorization: Bearer {token}'
                    ]
                ],
                'token_management' => [
                    'refresh' => 'POST /api/auth/refresh - Get new token',
                    'logout' => 'POST /api/auth/logout - Revoke current token',
                    'logout_all' => 'POST /api/auth/logout-all - Revoke all tokens'
                ],
                'security_features' => [
                    'account_lockout' => 'Account locked after 3 failed login attempts',
                    'token_abilities' => 'Tokens have role-based abilities',
                    'rate_limiting' => 'Authentication endpoints are rate limited'
                ]
            ]
        ]);
    }
}