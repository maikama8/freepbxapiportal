@extends('emails.layout')

@section('content')
<h2>Email Configuration Test</h2>

<p>Hello!</p>

<p>This is a test email to verify that your email configuration is working correctly.</p>

<div class="alert alert-success">
    <strong>Success!</strong> If you're reading this email, your SMTP configuration is working properly.
</div>

<p>Email sent at: {{ now()->format('Y-m-d H:i:s T') }}</p>

<p>Best regards,<br>{{ $app_name }} Team</p>
@endsection