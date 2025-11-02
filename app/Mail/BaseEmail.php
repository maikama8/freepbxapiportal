<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

abstract class BaseEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    abstract public function envelope(): Envelope;

    /**
     * Get the message content definition.
     */
    abstract public function content(): Content;

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get the data for the email template
     */
    protected function getData(): array
    {
        return array_merge([
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'support_email' => config('mail.from.address'),
        ], $this->data);
    }
}