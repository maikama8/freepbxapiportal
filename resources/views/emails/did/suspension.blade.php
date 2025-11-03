@extends('emails.layout')

@section('title', 'DID Number Suspended - Insufficient Balance')

@section('content')
<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
    <h2 style="color: #856404; margin: 0 0 10px 0;">⚠️ DID Number Suspended</h2>
    <p style="color: #856404; margin: 0;">Your DID number has been suspended due to insufficient account balance.</p>
</div>

<p>Dear {{ $user->name }},</p>

<p>We're writing to inform you that your DID number <strong>{{ $did_number }}</strong> has been suspended due to insufficient account balance.</p>

<div style="background-color: #f8f9fa; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0;">
    <h3 style="margin: 0 0 10px 0; color: #dc3545;">Suspension Details</h3>
    <ul style="margin: 0; padding-left: 20px;">
        <li><strong>DID Number:</strong> {{ $did_number }}</li>
        <li><strong>Required Amount:</strong> ${{ number_format($required_amount, 2) }}</li>
        <li><strong>Current Balance:</strong> ${{ number_format($current_balance, 2) }}</li>
        <li><strong>Shortfall:</strong> ${{ number_format($shortfall, 2) }}</li>
        <li><strong>Suspension Date:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</li>
    </ul>
</div>

<h3>What This Means</h3>
<p>While your DID number is suspended:</p>
<ul>
    <li>Incoming calls to this number will not be routed to your extension</li>
    <li>The number is reserved for you and will not be assigned to another customer</li>
    <li>You will not be charged monthly fees while the number is suspended</li>
</ul>

<h3>How to Reactivate</h3>
<p>To reactivate your DID number, please:</p>
<ol>
    <li>Add funds to your account to cover the monthly charge</li>
    <li>Ensure your balance is at least ${{ number_format($required_amount, 2) }}</li>
    <li>Contact our support team or the number will be automatically reactivated on your next billing cycle</li>
</ol>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ config('app.url') }}/customer/payments/add-funds" 
       style="background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
        Add Funds to Account
    </a>
</div>

<h3>Need Help?</h3>
<p>If you have any questions about this suspension or need assistance with adding funds to your account, please don't hesitate to contact our support team:</p>

<ul>
    <li><strong>Email:</strong> {{ config('voip.support.email', 'support@example.com') }}</li>
    <li><strong>Phone:</strong> {{ config('voip.support.phone', '+1-555-0123') }}</li>
    <li><strong>Support Hours:</strong> {{ config('voip.support.hours', 'Monday - Friday, 9 AM - 6 PM EST') }}</li>
</ul>

<p>We appreciate your business and look forward to restoring your service soon.</p>

<p>Best regards,<br>
{{ config('app.name') }} Billing Team</p>
@endsection