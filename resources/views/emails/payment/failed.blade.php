@extends('emails.layout')

@section('content')
<h2>Payment Failed</h2>

<p>Dear {{ $user->name }},</p>

<p>We're sorry to inform you that your recent payment attempt was not successful.</p>

<div class="alert alert-danger">
    <strong>Payment Failed!</strong> Your payment could not be processed at this time.
</div>

<h3>Payment Details</h3>
<table class="table">
    <tr>
        <th>Transaction ID:</th>
        <td>{{ $transaction_id ?: 'N/A' }}</td>
    </tr>
    <tr>
        <th>Amount:</th>
        <td>{{ number_format($amount, 2) }} {{ $currency }}</td>
    </tr>
    <tr>
        <th>Payment Method:</th>
        <td>{{ ucfirst($payment_method) }} ({{ ucfirst($gateway) }})</td>
    </tr>
    <tr>
        <th>Date & Time:</th>
        <td>{{ now()->format('F j, Y \a\t g:i A T') }}</td>
    </tr>
    @if($reason)
    <tr>
        <th>Reason:</th>
        <td>{{ $reason }}</td>
    </tr>
    @endif
</table>

<h3>What Can You Do?</h3>
<ul>
    <li><strong>Try Again:</strong> The issue might be temporary - you can retry your payment</li>
    <li><strong>Check Payment Details:</strong> Verify your payment information is correct</li>
    <li><strong>Use Different Method:</strong> Try an alternative payment method</li>
    <li><strong>Contact Support:</strong> Our team is here to help resolve any issues</li>
</ul>

<p>
    <a href="{{ $retry_url }}" class="button">
        Try Payment Again
    </a>
</p>

<h3>Common Reasons for Payment Failure</h3>
<ul>
    <li>Insufficient funds in your account</li>
    <li>Expired or invalid payment method</li>
    <li>Bank or payment provider security restrictions</li>
    <li>Network connectivity issues</li>
    <li>Incorrect payment information</li>
</ul>

<h3>Need Help?</h3>
<p>If you continue to experience issues or need assistance, please contact our support team:</p>
<ul>
    <li>📧 Email: <a href="mailto:{{ $support_email }}">{{ $support_email }}</a></li>
    <li>💬 Live Chat: Available in your account dashboard</li>
    <li>📞 Phone: Check your account for support phone numbers</li>
</ul>

<p>We apologize for any inconvenience and appreciate your patience as we work to resolve this issue.</p>

<p>Best regards,<br>{{ $app_name }} Team</p>
@endsection