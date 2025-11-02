<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Invoice;
use App\Services\Payment\NowPaymentsGateway;
use App\Services\Payment\PayPalGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    protected NowPaymentsGateway $nowPaymentsGateway;
    protected PayPalGateway $paypalGateway;

    public function __construct(
        NowPaymentsGateway $nowPaymentsGateway,
        PayPalGateway $paypalGateway
    ) {
        $this->nowPaymentsGateway = $nowPaymentsGateway;
        $this->paypalGateway = $paypalGateway;
    }

    /**
     * Show add funds interface
     */
    public function addFunds(): View
    {
        $user = Auth::user();
        
        // Get recent payment transactions
        $recentPayments = $user->paymentTransactions()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // Get available payment methods
        $paymentMethods = [
            'paypal' => [
                'name' => 'PayPal',
                'type' => 'paypal',
                'currencies' => ['USD', 'EUR', 'GBP'],
                'min_amount' => 1.00,
                'icon' => 'fab fa-paypal',
                'description' => 'Pay with your PayPal account or credit card'
            ],
            'crypto' => [
                'name' => 'Cryptocurrency',
                'type' => 'crypto',
                'currencies' => ['BTC', 'ETH', 'USDT', 'LTC'],
                'min_amount' => 0.01,
                'icon' => 'fab fa-bitcoin',
                'description' => 'Pay with Bitcoin, Ethereum, or other cryptocurrencies'
            ]
        ];
        
        return view('customer.payments.add-funds', compact('recentPayments', 'paymentMethods'));
    }

    /**
     * Show payment history
     */
    public function paymentHistory(Request $request): View
    {
        $user = Auth::user();
        
        $query = $user->paymentTransactions()->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('gateway')) {
            $query->where('gateway', $request->gateway);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $payments = $query->paginate(20);
        
        // Get unique statuses and gateways for filters
        $statuses = $user->paymentTransactions()
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->sort();
            
        $gateways = $user->paymentTransactions()
            ->select('gateway')
            ->distinct()
            ->pluck('gateway')
            ->sort();
        
        return view('customer.payments.history', compact('payments', 'statuses', 'gateways'));
    }

    /**
     * Show invoices
     */
    public function invoices(Request $request): View
    {
        $user = Auth::user();
        
        $query = $user->invoices()->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $invoices = $query->paginate(20);
        
        // Get unique statuses for filters
        $statuses = $user->invoices()
            ->select('status')
            ->distinct()
            ->pluck('status')
            ->sort();
        
        return view('customer.invoices.index', compact('invoices', 'statuses'));
    }

    /**
     * Download invoice PDF
     */
    public function downloadInvoice(Invoice $invoice)
    {
        // Verify the invoice belongs to the authenticated user
        if ($invoice->user_id !== Auth::id()) {
            abort(403, 'Access denied');
        }
        
        // Generate PDF using the invoice service
        $pdf = app(\App\Services\InvoiceService::class)->generatePDF($invoice);
        
        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Initiate payment (AJAX)
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:10000',
            'currency' => 'required|string|in:USD,EUR,GBP',
            'gateway' => 'required|string|in:nowpayments,paypal',
            'payment_method' => 'required|string',
        ]);

        $user = Auth::user();
        
        try {
            $amount = $request->amount;
            $currency = $request->currency;
            $gateway = $request->gateway;
            $paymentMethod = $request->payment_method;

            if ($gateway === 'nowpayments') {
                $transaction = $this->nowPaymentsGateway->createPayment($user, $amount, $currency, $paymentMethod);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'payment_id' => $transaction->gateway_transaction_id,
                        'payment_address' => $transaction->metadata['pay_address'] ?? null,
                        'pay_amount' => $transaction->metadata['pay_amount'] ?? null,
                        'pay_currency' => $paymentMethod,
                        'status' => $transaction->status,
                        'qr_code' => $transaction->metadata['qr_code'] ?? null,
                    ]
                ]);

            } elseif ($gateway === 'paypal') {
                $transaction = $this->paypalGateway->createPayment($user, $amount, $currency);
                $approvalUrl = $this->paypalGateway->getApprovalUrl($transaction);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'order_id' => $transaction->gateway_transaction_id,
                        'approval_url' => $approvalUrl,
                        'status' => $transaction->status,
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid payment gateway'
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment status (AJAX)
     */
    public function getPaymentStatus(PaymentTransaction $transaction): JsonResponse
    {
        // Verify the transaction belongs to the authenticated user
        if ($transaction->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $data = [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'gateway' => $transaction->gateway,
            'payment_method' => $transaction->payment_method,
            'status' => $transaction->status,
            'created_at' => $transaction->created_at,
            'completed_at' => $transaction->completed_at,
        ];

        // Add gateway-specific data
        if ($transaction->gateway === 'nowpayments') {
            $data['payment_address'] = $transaction->metadata['pay_address'] ?? null;
            $data['pay_amount'] = $transaction->metadata['pay_amount'] ?? null;
            $data['actually_paid'] = $transaction->metadata['actually_paid'] ?? null;
            $data['qr_code'] = $transaction->metadata['qr_code'] ?? null;
        } elseif ($transaction->gateway === 'paypal') {
            $data['approval_url'] = $this->paypalGateway->getApprovalUrl($transaction);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get available cryptocurrencies (AJAX)
     */
    public function getCryptoCurrencies(): JsonResponse
    {
        try {
            $currencies = $this->nowPaymentsGateway->getAvailableCurrencies();
            
            return response()->json([
                'success' => true,
                'currencies' => $currencies
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load cryptocurrencies'
            ], 500);
        }
    }

    /**
     * Get minimum payment amount for currency (AJAX)
     */
    public function getMinimumAmount(Request $request): JsonResponse
    {
        $request->validate([
            'currency' => 'required|string',
            'gateway' => 'required|string|in:nowpayments,paypal'
        ]);

        try {
            $minAmount = 1.00; // Default minimum
            
            if ($request->gateway === 'nowpayments') {
                $minAmount = $this->nowPaymentsGateway->getMinimumAmount($request->currency) ?? 0.01;
            }
            
            return response()->json([
                'success' => true,
                'minimum_amount' => $minAmount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get minimum amount'
            ], 500);
        }
    }
}