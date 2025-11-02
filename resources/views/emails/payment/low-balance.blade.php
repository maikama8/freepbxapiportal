@extends('emails.layout')

@section('content')
<h2>Low Balance Warning</h2>

<p>Dear {{ $user->name }},</p>

<p>This is a friendly reminder that your account balance is running low.</p>

<div class="alert alert-warning">
    <strong>Low Balance Alert!</strong> Your current balance is below the recommended threshold.
</div>

<h3>Account Information</h3>
<table class="table">
    <tr>
        <th>Current Balance:</th>
        <td><strong>{{ number_format($current_balance, 2) }} {{ $currency }}</strong></td>
    </tr>
    <tr>
        <th>Warning Threshold:</th>
        <td>{{ number_format($threshold, 2) }} {{ $currency }}</td>
    </tr>
    <tr>
        <th>Account Type:</th>
        <td>{{ ucfirst($user->account_type) }}</td>
    </tr>
</table>

@if($user->isPrepaid())
<div class="alert alert-danger">
    <strong>Important:</strong> As a prepaid customer, you'll need to add funds to continue making calls when your balance reaches zero.
</div>
@endif

<p>
    <a href="{{ $add_funds_url }}" class="button">
        Add Funds Now
    </a>
</p>

<h3>Why Add Funds?</h3>
<ul>
    <li><strong>Uninterrupted Service:</strong> Ensure your calls don't get disconnected</li>
    <li><strong>Peace of Mind:</strong> Avoid service interruptions during important calls</li>
    <li><strong>Better Rates:</strong> Take advantage of bulk payment discounts</li>
    <li><strong>Convenience:</strong> Set up automatic top-ups to never worry about balance again</li>
</ul>

<h3>Easy Payment Options</h3>
<p>We accept multiple payment methods for your convenience:</p>
<ul>
    <li>💳 PayPal - Quick and secure</li>
    <li>₿ Cryptocurrency - Bitcoin, Ethereum, USDT and more</li>
    <li>🔒 All payments are secured with industry-standard encryption</li>
</ul>

<p>If you have any questions or need assistance with adding funds, please contact our support team.</p>

<p>Best regards,<br>{{ $app_name }} Team</p>
@endsection