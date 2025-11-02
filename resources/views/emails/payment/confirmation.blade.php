@extends('emails.layout')

@section('content')
<h2>Payment Confirmation</h2>

<p>Dear {{ $user->name }},</p>

<p>We're pleased to confirm that your payment has been successfully processed!</p>

<div class="alert alert-success">
    <strong>Payment Successful!</strong> Your account has been credited with the payment amount.
</div>

<h3>Payment Details</h3>
<table class="table">
    <tr>
        <th>Transaction ID:</th>
        <td>{{ $transaction_id }}</td>
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
        <td>{{ $completed_at->format('F j, Y \a\t g:i A T') }}</td>
    </tr>
    <tr>
        <th>New Account Balance:</th>
        <td><strong>{{ number_format($new_balance, 2) }} {{ $currency }}</strong></td>
    </tr>
</table>

<p>
    <a href="{{ $app_url }}/customer/dashboard" class="button">
        View Account Dashboard
    </a>
</p>

<h3>What's Next?</h3>
<ul>
    <li>Your account balance has been updated immediately</li>
    <li>You can now make calls using your VoIP services</li>
    <li>View your payment history in your account dashboard</li>
    <li>Download invoices and receipts from your account</li>
</ul>

<p>If you have any questions about this payment or need assistance, please don't hesitate to contact our support team.</p>

<p>Thank you for choosing {{ $app_name }}!</p>

<p>Best regards,<br>{{ $app_name }} Team</p>
@endsection