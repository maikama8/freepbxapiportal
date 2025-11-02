<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;

class LowBalanceWarningEmail extends BaseEmail
{
    protected User $user;
    protected float $threshold;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, float $threshold = 5.00)
    {
        $this->user = $user;
        $this->threshold = $threshold;
        
        parent::__construct([
            'user' => $user,
            'current_balance' => $user->balance,
            'threshold' => $threshold,
            'currency' => $user->currency ?? config('voip.default_currency', 'USD'),
            'add_funds_url' => route('customer.payments.add-funds'),
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Low Balance Warning - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.low-balance',
            with: $this->getData(),
        );
    }
}