<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\PaymentTransaction;
use App\Models\User;

class PaymentFailedEmail extends BaseEmail
{
    protected PaymentTransaction $payment;
    protected User $user;
    protected string $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(PaymentTransaction $payment, string $reason = '')
    {
        $this->payment = $payment;
        $this->user = $payment->user;
        $this->reason = $reason;
        
        parent::__construct([
            'payment' => $payment,
            'user' => $this->user,
            'transaction_id' => $payment->gateway_transaction_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'gateway' => $payment->gateway,
            'payment_method' => $payment->payment_method,
            'reason' => $reason,
            'retry_url' => route('customer.payments.add-funds'),
            'support_email' => config('mail.from.address'),
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Failed - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.failed',
            with: $this->getData(),
        );
    }
}