<?php

namespace App\Services\Email;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Exception;

class EmailService
{
    /**
     * Send an email using Laravel's mail system
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @return bool
     */
    public function send($to, Mailable $mailable): bool
    {
        try {
            Mail::to($to)->send($mailable);
            
            Log::info('Email sent successfully', [
                'to' => is_array($to) ? implode(', ', $to) : $to,
                'mailable' => get_class($mailable)
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to send email', [
                'to' => is_array($to) ? implode(', ', $to) : $to,
                'mailable' => get_class($mailable),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send email to a user
     *
     * @param User $user
     * @param Mailable $mailable
     * @return bool
     */
    public function sendToUser(User $user, Mailable $mailable): bool
    {
        return $this->send($user->email, $mailable);
    }

    /**
     * Send email to multiple users
     *
     * @param array $users
     * @param Mailable $mailable
     * @return array
     */
    public function sendToUsers(array $users, Mailable $mailable): array
    {
        $results = [];
        
        foreach ($users as $user) {
            $results[$user->id] = $this->sendToUser($user, $mailable);
        }
        
        return $results;
    }

    /**
     * Queue an email for later sending
     *
     * @param string|array $to
     * @param Mailable $mailable
     * @return bool
     */
    public function queue($to, Mailable $mailable): bool
    {
        try {
            Mail::to($to)->queue($mailable);
            
            Log::info('Email queued successfully', [
                'to' => is_array($to) ? implode(', ', $to) : $to,
                'mailable' => get_class($mailable)
            ]);
            
            return true;
        } catch (Exception $e) {
            Log::error('Failed to queue email', [
                'to' => is_array($to) ? implode(', ', $to) : $to,
                'mailable' => get_class($mailable),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Queue email to a user
     *
     * @param User $user
     * @param Mailable $mailable
     * @return bool
     */
    public function queueToUser(User $user, Mailable $mailable): bool
    {
        return $this->queue($user->email, $mailable);
    }

    /**
     * Test email configuration by sending a test email
     *
     * @param string $to
     * @return bool
     */
    public function testConfiguration(string $to): bool
    {
        try {
            $testMailable = new \App\Mail\TestEmail();
            return $this->send($to, $testMailable);
        } catch (Exception $e) {
            Log::error('Email configuration test failed', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}