@extends('emails.layout')

@section('title', 'DID Monthly Charge - Account Overdue')

@section('content')
<div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
    <h2 style="color: #856404; margin: 0 0 10px 0;">⚠️ Account Overdue Notice</h2>
    <p style="color: #856404; margin: 0;">Your account has been charged for DID services but has a negative balance.</p>
</div>

<p>Dear {{ $user->name }},</p>

<p>We've processed your monthly DID charge, but your account now has a negative balance. Please add funds to your account to avoid service interruptions.</p>

<div style="background-color: #f8f9fa; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
    <h3 style="margin: 0 0 10px 0; color: #856404;">Charge Details</h3>
    <ul style="margin: 0; padding-left: 20px;">
        <li><strong>DID Number:</strong> {{ $did_number }}</li>
        <li><strong>Monthly Charge:</strong> ${{ number_format($charge_amount, 2) }}</li>
        <li><strong>Current Balance:</strong> ${{ number_format($current_balance, 2) }}</li>
        <li><strong>Charge Date:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</li>
    </ul>
</div>

<h3>Important Notice</h3>
<p>Your DID number <strong>{{ $did_number }}</strong> remains active for now, but to avoid suspension and ensure uninterrupted service:</p>

<ul>
    <li>Please add funds to bring your account balance to positive</li>
    <li>Future charges may result in service suspension if your balance remains negative</li>
    <li>Late fees may apply to overdue accounts</li>
</ul>

<h3>Add Funds Now</h3>
<p>You can quickly add funds to your account using any of our supported payment methods:</p>

<ul>
    <li>Credit/Debit Card</li>
    <li>PayPal</li>
    <li>Cryptocurrency (Bitcoin, Ethereum, USDT)</li>
    <li>Bank Transfer</li>
</ul>

<div style="text-align: center; margin: 30px 0;">
    <a href="{{ config('app.url') }}/customer/payments/add-funds" 
       style="background-color: #ffc107; color: #212529; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">
        Add Funds Now
    </a>
</div>

<h3>Account Summary</h3>
<p>You can view your complete account summary, including all charges and payments, in your customer portal:</p>

<div style="text-align: center; margin: 20px 0;">
    <a href="{{ config('app.url') }}/customer/balance-history" 
       style="background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
        View Account Summary
    </a>
</div>

<h3>Questions or Concerns?</h3>
<p>If you believe this charge is incorrect or if you need assistance with your account, please contact our billing support team:</p>

<ul>
    <li><strong>Email:</strong> {{ config('voip.billing.email', 'billing@example.com') }}</li>
    <li><strong>Phone:</strong> {{ config('voip.billing.phone', '+1-555-0124') }}</li>
    <li><strong>Support Hours:</strong> {{ config('voip.billing.hours', 'Monday - Friday, 9 AM - 6 PM EST') }}</li>
</ul>

<p>Thank you for your prompt attention to this matter.</p>

<p>Best regards,<br>
{{ config('app.name') }} Billing Department</p>

<div style="background-color: #e9ecef; padding: 15px; margin-top: 30px; border-radius: 4px; font-size: 12px; color: #6c757d;">
    <p style="margin: 0;"><strong>Note:</strong> This is an automated billing notice. Please do not reply to this email. For billing inquiries, use the contact information provided above.</p>
</div>
@endsection