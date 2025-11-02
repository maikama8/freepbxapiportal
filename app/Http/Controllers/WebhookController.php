<?php

namespace App\Http\Controllers;

use App\Services\Payment\NowPaymentsGateway;
use App\Services\Payment\PayPalGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Exception;

class WebhookController extends Controller
{
    private NowPaymentsGateway $nowPaymentsGateway;
    private PayPalGateway $paypalGateway;

    public function __construct(
        NowPaymentsGateway $nowPaymentsGateway,
        PayPalGateway $paypalGateway
    ) {
        $this->nowPaymentsGateway = $nowPaymentsGateway;
        $this->paypalGateway = $paypalGateway;
    }

    /**
     * Handle NowPayments webhook
     */
    public function handleNowPayments(Request $request): Response
    {
        try {
            $payload = $request->all();
            $signature = $request->header('x-nowpayments-sig');

            Log::info('NowPayments webhook received', [
                'payload' => $payload,
                'signature' => $signature ? 'present' : 'missing'
            ]);

            if (!$signature) {
                Log::warning('NowPayments webhook missing signature');
                return response('Missing signature', 400);
            }

            $success = $this->nowPaymentsGateway->processWebhook($payload, $signature);

            if ($success) {
                return response('OK', 200);
            } else {
                return response('Processing failed', 400);
            }

        } catch (Exception $e) {
            Log::error('NowPayments webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response('Internal error', 500);
        }
    }

    /**
     * Handle PayPal webhook
     */
    public function handlePayPal(Request $request): Response
    {
        try {
            $payload = $request->all();

            Log::info('PayPal webhook received', [
                'event_type' => $payload['event_type'] ?? 'unknown',
                'resource_id' => $payload['resource']['id'] ?? 'unknown'
            ]);

            // Note: In production, you should verify the webhook signature
            // PayPal provides webhook signature verification
            // For now, we'll process without verification

            $success = $this->paypalGateway->processWebhook($payload);

            if ($success) {
                return response('OK', 200);
            } else {
                return response('Processing failed', 400);
            }

        } catch (Exception $e) {
            Log::error('PayPal webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response('Internal error', 500);
        }
    }
}
