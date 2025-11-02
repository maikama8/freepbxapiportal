<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\PaymentTransaction;
use App\Models\User;

class PaymentConfirmationEmail extends BaseEmail
{
    protected PaymentTransaction $payment;
    protected User $user;

    /**
     * Create a new message instance.
     */
    public function __construct(PaymentTransaction $payment)
    {
        $this->payment = $payment;
        $this->user = $payment->user;
        
        parent::__construct([
            'payment' => $payment,
            'user' => $this->user,
            'transaction_id' => $payment->gateway_transaction_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'gateway' => $payment->gateway,
            'payment_method' => $payment->payment_method,
            'completed_at' => $payment->completed_at,
            'new_balance' => $this->user->balance,
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Confirmation - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.confirmation',
            with: $this->getData(),
        );
    }
}