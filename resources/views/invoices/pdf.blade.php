<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .company-info {
            float: left;
            width: 50%;
        }
        .invoice-info {
            float: right;
            width: 45%;
            text-align: right;
        }
        .clear {
            clear: both;
        }
        .billing-section {
            margin: 30px 0;
        }
        .billing-to {
            float: left;
            width: 50%;
        }
        .invoice-details {
            float: right;
            width: 45%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .items-table .text-right {
            text-align: right;
        }
        .totals {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 5px 10px;
            border-bottom: 1px solid #ddd;
        }
        .totals .total-row {
            font-weight: bold;
            font-size: 14px;
            background-color: #f8f9fa;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
        }
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status.paid {
            background-color: #d4edda;
            color: #155724;
        }
        .status.overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status.sent {
            background-color: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h1>{{ $company['name'] ?? 'VoIP Platform' }}</h1>
            <p>
                {{ $company['address'] ?? '123 Business St' }}<br>
                {{ $company['city'] ?? 'Business City' }}, {{ $company['state'] ?? 'ST' }} {{ $company['zip'] ?? '12345' }}<br>
                Phone: {{ $company['phone'] ?? '(555) 123-4567' }}<br>
                Email: {{ $company['email'] ?? 'billing@voipplatform.com' }}
            </p>
        </div>
        <div class="invoice-info">
            <h2>INVOICE</h2>
            <p>
                <strong>Invoice #:</strong> {{ $invoice->invoice_number }}<br>
                <strong>Date:</strong> {{ $invoice->invoice_date->format('M j, Y') }}<br>
                <strong>Due Date:</strong> {{ $invoice->due_date->format('M j, Y') }}<br>
                <strong>Status:</strong> <span class="status {{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
            </p>
        </div>
        <div class="clear"></div>
    </div>

    <div class="billing-section">
        <div class="billing-to">
            <h3>Bill To:</h3>
            <p>
                <strong>{{ $invoice->billing_address['name'] ?? $user->name }}</strong><br>
                {{ $invoice->billing_address['email'] ?? $user->email }}<br>
                @if(isset($invoice->billing_address['phone']) || $user->phone)
                    Phone: {{ $invoice->billing_address['phone'] ?? $user->phone }}<br>
                @endif
                @if(isset($invoice->billing_address['address']))
                    {{ $invoice->billing_address['address'] }}<br>
                @endif
            </p>
        </div>
        <div class="invoice-details">
            <h3>Invoice Details:</h3>
            <p>
                <strong>Account Type:</strong> {{ ucfirst($user->account_type) }}<br>
                <strong>Billing Period:</strong> {{ $invoice->getPeriodDescription() }}<br>
                <strong>Currency:</strong> {{ $invoice->currency }}<br>
                @if($user->extension)
                    <strong>Extension:</strong> {{ $user->extension }}<br>
                @endif
            </p>
        </div>
        <div class="clear"></div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ $item->getFormattedUnitPrice() }}</td>
                    <td class="text-right">{{ $item->getFormattedTotalPrice() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; font-style: italic;">No charges for this period</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">{{ number_format($invoice->subtotal, 2) }} {{ $invoice->currency }}</td>
            </tr>
            @if($invoice->tax_amount > 0)
                <tr>
                    <td>Tax:</td>
                    <td class="text-right">{{ number_format($invoice->tax_amount, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td>Total:</td>
                <td class="text-right">{{ $invoice->getFormattedTotal() }}</td>
            </tr>
        </table>
    </div>
    <div class="clear"></div>

    @if($invoice->notes)
        <div style="margin-top: 30px;">
            <h3>Notes:</h3>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>
            <strong>Payment Terms:</strong> Payment is due within {{ config('voip.billing.payment_terms_days', 30) }} days of invoice date.<br>
            <strong>Late Fees:</strong> A late fee may be applied to overdue accounts.<br>
            <strong>Questions?</strong> Contact us at {{ $company['email'] ?? 'billing@voipplatform.com' }} or {{ $company['phone'] ?? '(555) 123-4567' }}.
        </p>
        <p style="text-align: center; margin-top: 20px;">
            Thank you for your business!
        </p>
    </div>
</body>
</html>