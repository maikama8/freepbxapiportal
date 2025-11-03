<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AlertingService
{
    protected array $alertChannels = [];
    protected array $alertThresholds = [];
    protected string $cachePrefix = 'alert_throttle_';

    public function __construct()
    {
        $this->alertChannels = config('voip.alerting.channels', []);
        $this->alertThresholds = config('voip.alerting.thresholds', []);
    }

    /**
     * Send a critical system alert
     */
    public function sendCriticalAlert(string $title, string $message, array $context = []): void
    {
        $alert = $this->createAlert('critical', $title, $message, $context);
        
        if ($this->shouldSendAlert($alert)) {
            $this->dispatchAlert($alert);
            $this->recordAlert($alert);
        }
    }

    /**
     * Send a warning alert
     */
    public function sendWarningAlert(string $title, string $message, array $context = []): void
    {
        $alert = $this->createAlert('warning', $title, $message, $context);
        
        if ($this->shouldSendAlert($alert)) {
            $this->dispatchAlert($alert);
            $this->recordAlert($alert);
        }
    }

    /**
     * Send an informational alert
     */
    public function sendInfoAlert(string $title, string $message, array $context = []): void
    {
        $alert = $this->createAlert('info', $title, $message, $context);
        
        if ($this->shouldSendAlert($alert)) {
            $this->dispatchAlert($alert);
            $this->recordAlert($alert);
        }
    }

    /**
     * Send DID inventory alert
     */
    public function sendDidInventoryAlert(string $countryCode, int $available, int $total): void
    {
        $percentage = $total > 0 ? ($available / $total) * 100 : 0;
        
        $title = "Low DID Inventory - {$countryCode}";
        $message = "DID inventory for {$countryCode} is running low: {$available} available out of {$total} total ({$percentage}%)";
        
        $context = [
            'country_code' => $countryCode,
            'available_count' => $available,
            'total_count' => $total,
            'percentage' => round($percentage, 2),
            'alert_type' => 'did_inventory'
        ];

        if ($percentage < 5) {
            $this->sendCriticalAlert($title, $message, $context);
        } elseif ($percentage < 10) {
            $this->sendWarningAlert($title, $message, $context);
        }
    }

    /**
     * Send billing accuracy alert
     */
    public function sendBillingAccuracyAlert(string $issueType, array $details): void
    {
        $title = "Billing Accuracy Issue - {$issueType}";
        $message = $this->formatBillingMessage($issueType, $details);
        
        $context = array_merge([
            'alert_type' => 'billing_accuracy',
            'issue_type' => $issueType
        ], $details);

        $this->sendCriticalAlert($title, $message, $context);
    }

    /**
     * Send real-time billing alert
     */
    public function sendRealTimeBillingAlert(string $event, array $callData): void
    {
        $title = "Real-time Billing Alert - {$event}";
        $message = $this->formatRealTimeBillingMessage($event, $callData);
        
        $context = array_merge([
            'alert_type' => 'real_time_billing',
            'event' => $event
        ], $callData);

        if (in_array($event, ['call_termination_needed', 'billing_error'])) {
            $this->sendCriticalAlert($title, $message, $context);
        } else {
            $this->sendWarningAlert($title, $message, $context);
        }
    }

    /**
     * Send system performance alert
     */
    public function sendPerformanceAlert(string $metric, float $value, float $threshold): void
    {
        $title = "System Performance Alert - {$metric}";
        $message = "System {$metric} is {$value}% (threshold: {$threshold}%)";
        
        $context = [
            'alert_type' => 'system_performance',
            'metric' => $metric,
            'value' => $value,
            'threshold' => $threshold
        ];

        if ($value > $threshold * 1.2) { // 20% above threshold
            $this->sendCriticalAlert($title, $message, $context);
        } else {
            $this->sendWarningAlert($title, $message, $context);
        }
    }

    /**
     * Send cron job failure alert
     */
    public function sendCronJobAlert(string $jobName, string $status, array $details = []): void
    {
        $title = "Cron Job Alert - {$jobName}";
        $message = "Cron job '{$jobName}' status: {$status}";
        
        if (isset($details['error_message'])) {
            $message .= "\nError: {$details['error_message']}";
        }
        
        $context = array_merge([
            'alert_type' => 'cron_job',
            'job_name' => $jobName,
            'status' => $status
        ], $details);

        if ($status === 'failed' || $status === 'overdue') {
            $this->sendCriticalAlert($title, $message, $context);
        } else {
            $this->sendWarningAlert($title, $message, $context);
        }
    }

    /**
     * Send payment gateway alert
     */
    public function sendPaymentGatewayAlert(string $gateway, string $status, array $details = []): void
    {
        $title = "Payment Gateway Alert - {$gateway}";
        $message = "Payment gateway '{$gateway}' status: {$status}";
        
        $context = array_merge([
            'alert_type' => 'payment_gateway',
            'gateway' => $gateway,
            'status' => $status
        ], $details);

        if ($status === 'down' || $status === 'error') {
            $this->sendCriticalAlert($title, $message, $context);
        } else {
            $this->sendWarningAlert($title, $message, $context);
        }
    }

    /**
     * Send FreePBX integration alert
     */
    public function sendFreePBXAlert(string $event, array $details = []): void
    {
        $title = "FreePBX Integration Alert - {$event}";
        $message = $this->formatFreePBXMessage($event, $details);
        
        $context = array_merge([
            'alert_type' => 'freepbx_integration',
            'event' => $event
        ], $details);

        if (in_array($event, ['connection_failed', 'sync_failed', 'api_error'])) {
            $this->sendCriticalAlert($title, $message, $context);
        } else {
            $this->sendWarningAlert($title, $message, $context);
        }
    }

    /**
     * Send security alert
     */
    public function sendSecurityAlert(string $event, array $securityData): void
    {
        $title = "Security Alert - {$event}";
        $message = $this->formatSecurityMessage($event, $securityData);
        
        $context = array_merge([
            'alert_type' => 'security',
            'event' => $event
        ], $securityData);

        $threatLevel = $securityData['threat_level'] ?? 'medium';
        
        if (in_array($threatLevel, ['critical', 'high'])) {
            $this->sendCriticalAlert($title, $message, $context);
        } else {
            $this->sendWarningAlert($title, $message, $context);
        }
    }

    /**
     * Create an alert object
     */
    protected function createAlert(string $severity, string $title, string $message, array $context): array
    {
        return [
            'id' => uniqid('alert_'),
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'timestamp' => Carbon::now(),
            'server' => gethostname(),
            'environment' => app()->environment()
        ];
    }

    /**
     * Check if alert should be sent (throttling)
     */
    protected function shouldSendAlert(array $alert): bool
    {
        $alertKey = $this->generateAlertKey($alert);
        $throttleKey = $this->cachePrefix . $alertKey;
        
        // Check if we've sent this alert recently
        if (Cache::has($throttleKey)) {
            return false;
        }
        
        // Set throttle based on severity
        $throttleMinutes = match($alert['severity']) {
            'critical' => 5,   // Critical alerts every 5 minutes max
            'warning' => 15,   // Warning alerts every 15 minutes max
            'info' => 60,      // Info alerts every hour max
            default => 30
        };
        
        Cache::put($throttleKey, true, now()->addMinutes($throttleMinutes));
        
        return true;
    }

    /**
     * Generate a unique key for alert throttling
     */
    protected function generateAlertKey(array $alert): string
    {
        $keyData = [
            $alert['severity'],
            $alert['title'],
            $alert['context']['alert_type'] ?? 'general'
        ];
        
        return md5(implode('|', $keyData));
    }

    /**
     * Dispatch alert to all configured channels
     */
    protected function dispatchAlert(array $alert): void
    {
        // Always log the alert
        Log::channel('alerts')->log($alert['severity'], $alert['message'], $alert['context']);
        
        // Send to email if configured
        if (isset($this->alertChannels['email']) && $this->alertChannels['email']['enabled']) {
            $this->sendEmailAlert($alert);
        }
        
        // Send to Slack if configured
        if (isset($this->alertChannels['slack']) && $this->alertChannels['slack']['enabled']) {
            $this->sendSlackAlert($alert);
        }
        
        // Send to SMS if configured
        if (isset($this->alertChannels['sms']) && $this->alertChannels['sms']['enabled']) {
            $this->sendSmsAlert($alert);
        }
        
        // Send to webhook if configured
        if (isset($this->alertChannels['webhook']) && $this->alertChannels['webhook']['enabled']) {
            $this->sendWebhookAlert($alert);
        }
    }

    /**
     * Send email alert
     */
    protected function sendEmailAlert(array $alert): void
    {
        try {
            $recipients = $this->alertChannels['email']['recipients'] ?? [];
            
            foreach ($recipients as $recipient) {
                Mail::raw($this->formatEmailMessage($alert), function ($message) use ($alert, $recipient) {
                    $message->to($recipient)
                           ->subject("[{$alert['severity']}] {$alert['title']} - FreePBX VoIP Platform");
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send email alert', [
                'alert_id' => $alert['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send Slack alert
     */
    protected function sendSlackAlert(array $alert): void
    {
        try {
            $webhookUrl = $this->alertChannels['slack']['webhook_url'] ?? null;
            
            if (!$webhookUrl) {
                return;
            }
            
            $payload = [
                'text' => $this->formatSlackMessage($alert),
                'username' => 'FreePBX VoIP Platform',
                'icon_emoji' => $this->getSlackEmoji($alert['severity']),
                'attachments' => [
                    [
                        'color' => $this->getSlackColor($alert['severity']),
                        'fields' => [
                            [
                                'title' => 'Severity',
                                'value' => strtoupper($alert['severity']),
                                'short' => true
                            ],
                            [
                                'title' => 'Server',
                                'value' => $alert['server'],
                                'short' => true
                            ],
                            [
                                'title' => 'Time',
                                'value' => $alert['timestamp']->format('Y-m-d H:i:s T'),
                                'short' => true
                            ]
                        ]
                    ]
                ]
            ];
            
            Http::post($webhookUrl, $payload);
            
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert', [
                'alert_id' => $alert['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send SMS alert (placeholder for SMS service integration)
     */
    protected function sendSmsAlert(array $alert): void
    {
        // This would integrate with your SMS service (Twilio, etc.)
        Log::info('SMS alert would be sent', [
            'alert_id' => $alert['id'],
            'message' => $this->formatSmsMessage($alert)
        ]);
    }

    /**
     * Send webhook alert
     */
    protected function sendWebhookAlert(array $alert): void
    {
        try {
            $webhookUrl = $this->alertChannels['webhook']['url'] ?? null;
            
            if (!$webhookUrl) {
                return;
            }
            
            Http::post($webhookUrl, $alert);
            
        } catch (\Exception $e) {
            Log::error('Failed to send webhook alert', [
                'alert_id' => $alert['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record alert in database for tracking
     */
    protected function recordAlert(array $alert): void
    {
        try {
            // This would store the alert in a database table for tracking
            Log::info('Alert recorded', [
                'alert_id' => $alert['id'],
                'severity' => $alert['severity'],
                'title' => $alert['title']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record alert', [
                'alert_id' => $alert['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Format billing message
     */
    protected function formatBillingMessage(string $issueType, array $details): string
    {
        return match($issueType) {
            'unprocessed_calls' => "There are {$details['count']} unprocessed calls that need billing attention.",
            'billing_discrepancies' => "Billing discrepancies detected affecting {$details['affected_users']} users with $" . number_format($details['unbilled_amount'], 2) . " in unbilled calls.",
            'balance_integrity' => "Balance calculation discrepancies detected for {$details['affected_users']} users.",
            'negative_prepaid_balances' => "{$details['count']} prepaid accounts have negative balances.",
            default => "Billing issue detected: {$issueType}"
        };
    }

    /**
     * Format real-time billing message
     */
    protected function formatRealTimeBillingMessage(string $event, array $callData): string
    {
        return match($event) {
            'call_termination_needed' => "Call {$callData['call_id']} needs termination due to insufficient balance (User: {$callData['user_id']}, Balance: {$callData['user_balance']}, Cost: {$callData['estimated_cost']}).",
            'billing_increment_errors' => "{$callData['count']} calls have billing increment calculation errors.",
            default => "Real-time billing event: {$event}"
        };
    }

    /**
     * Format FreePBX message
     */
    protected function formatFreePBXMessage(string $event, array $details): string
    {
        return match($event) {
            'connection_failed' => "Failed to connect to FreePBX API: " . ($details['error'] ?? 'Unknown error'),
            'sync_failed' => "FreePBX synchronization failed: " . ($details['error'] ?? 'Unknown error'),
            'api_error' => "FreePBX API error: " . ($details['error'] ?? 'Unknown error'),
            default => "FreePBX integration event: {$event}"
        };
    }

    /**
     * Format security message
     */
    protected function formatSecurityMessage(string $event, array $securityData): string
    {
        $message = "Security event: {$event}";
        
        if (isset($securityData['ip_address'])) {
            $message .= " from IP: {$securityData['ip_address']}";
        }
        
        if (isset($securityData['user_id'])) {
            $message .= " (User ID: {$securityData['user_id']})";
        }
        
        if (isset($securityData['additional_data'])) {
            $message .= " - {$securityData['additional_data']}";
        }
        
        return $message;
    }

    /**
     * Format email message
     */
    protected function formatEmailMessage(array $alert): string
    {
        $message = "FreePBX VoIP Platform Alert\n";
        $message .= "==========================\n\n";
        $message .= "Severity: " . strtoupper($alert['severity']) . "\n";
        $message .= "Title: {$alert['title']}\n";
        $message .= "Time: {$alert['timestamp']->format('Y-m-d H:i:s T')}\n";
        $message .= "Server: {$alert['server']}\n";
        $message .= "Environment: {$alert['environment']}\n\n";
        $message .= "Message:\n{$alert['message']}\n\n";
        
        if (!empty($alert['context'])) {
            $message .= "Additional Details:\n";
            foreach ($alert['context'] as $key => $value) {
                if (is_scalar($value)) {
                    $message .= "- {$key}: {$value}\n";
                }
            }
        }
        
        return $message;
    }

    /**
     * Format Slack message
     */
    protected function formatSlackMessage(array $alert): string
    {
        return "*{$alert['title']}*\n{$alert['message']}";
    }

    /**
     * Format SMS message
     */
    protected function formatSmsMessage(array $alert): string
    {
        return "[{$alert['severity']}] {$alert['title']}: {$alert['message']}";
    }

    /**
     * Get Slack emoji for severity
     */
    protected function getSlackEmoji(string $severity): string
    {
        return match($severity) {
            'critical' => ':rotating_light:',
            'warning' => ':warning:',
            'info' => ':information_source:',
            default => ':bell:'
        };
    }

    /**
     * Get Slack color for severity
     */
    protected function getSlackColor(string $severity): string
    {
        return match($severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'good',
            default => '#36a64f'
        };
    }
}