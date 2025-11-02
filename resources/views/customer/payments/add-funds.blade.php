@extends('layouts.customer')

@section('title', 'Add Funds')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Payment Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-credit-card"></i> Add Funds to Your Account</h5>
                </div>
                <div class="card-body">
                    <form id="paymentForm">
                        @csrf
                        
                        <!-- Amount Selection -->
                        <div class="mb-4">
                            <label for="amount" class="form-label">Amount</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       min="0.01" max="10000" step="0.01" placeholder="0.00" required>
                                <select class="form-select" id="currency" name="currency" style="max-width: 100px;">
                                    <option value="USD" {{ auth()->user()->currency === 'USD' ? 'selected' : '' }}>USD</option>
                                    <option value="EUR" {{ auth()->user()->currency === 'EUR' ? 'selected' : '' }}>EUR</option>
                                    <option value="GBP" {{ auth()->user()->currency === 'GBP' ? 'selected' : '' }}>GBP</option>
                                </select>
                            </div>
                            <div class="form-text">Minimum: $1.00, Maximum: $10,000.00</div>
                        </div>

                        <!-- Quick Amount Buttons -->
                        <div class="mb-4">
                            <label class="form-label">Quick Select</label>
                            <div class="btn-group d-flex" role="group">
                                <button type="button" class="btn btn-outline-primary" onclick="setAmount(10)">$10</button>
                                <button type="button" class="btn btn-outline-primary" onclick="setAmount(25)">$25</button>
                                <button type="button" class="btn btn-outline-primary" onclick="setAmount(50)">$50</button>
                                <button type="button" class="btn btn-outline-primary" onclick="setAmount(100)">$100</button>
                                <button type="button" class="btn btn-outline-primary" onclick="setAmount(250)">$250</button>
                            </div>
                        </div>

                        <!-- Payment Method Selection -->
                        <div class="mb-4">
                            <label class="form-label">Payment Method</label>
                            <div class="row">
                                @foreach($paymentMethods as $key => $method)
                                <div class="col-md-6 mb-3">
                                    <div class="card payment-method-card" data-method="{{ $key }}">
                                        <div class="card-body text-center">
                                            <input type="radio" class="btn-check" name="gateway" 
                                                   id="gateway_{{ $key }}" value="{{ $key === 'crypto' ? 'nowpayments' : $key }}">
                                            <label class="btn btn-outline-primary w-100" for="gateway_{{ $key }}">
                                                <i class="{{ $method['icon'] }} fa-2x mb-2"></i>
                                                <h6>{{ $method['name'] }}</h6>
                                                <small class="text-muted">{{ $method['description'] }}</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Cryptocurrency Selection (Hidden by default) -->
                        <div id="cryptoSelection" class="mb-4" style="display: none;">
                            <label for="crypto_currency" class="form-label">Select Cryptocurrency</label>
                            <select class="form-select" id="crypto_currency" name="payment_method">
                                <option value="">Loading cryptocurrencies...</option>
                            </select>
                            <div class="form-text">Minimum amounts vary by cryptocurrency</div>
                        </div>

                        <!-- PayPal Selection (Hidden by default) -->
                        <div id="paypalSelection" class="mb-4" style="display: none;">
                            <input type="hidden" name="payment_method" value="paypal">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                You will be redirected to PayPal to complete your payment securely.
                            </div>
                        </div>

                        <!-- Payment Summary -->
                        <div id="paymentSummary" class="alert alert-light" style="display: none;">
                            <h6>Payment Summary</h6>
                            <div class="row">
                                <div class="col-6">
                                    <strong>Amount:</strong> <span id="summaryAmount">$0.00</span>
                                </div>
                                <div class="col-6">
                                    <strong>Method:</strong> <span id="summaryMethod">-</span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <strong>Current Balance:</strong> ${{ number_format(auth()->user()->balance, 2) }}
                                </div>
                                <div class="col-6">
                                    <strong>New Balance:</strong> <span id="summaryNewBalance">${{ number_format(auth()->user()->balance, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="paymentButton" disabled>
                                <i class="fas fa-credit-card"></i> <span id="paymentButtonText">Select Payment Method</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Info & Recent Payments -->
        <div class="col-md-4">
            <!-- Account Balance -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-wallet"></i> Account Balance</h6>
                </div>
                <div class="card-body text-center">
                    <h3 class="text-success">${{ number_format(auth()->user()->balance, 2) }}</h3>
                    <p class="text-muted">{{ ucfirst(auth()->user()->account_type) }} Account</p>
                    @if(auth()->user()->isPostpaid())
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Credit Limit:</strong><br>
                                ${{ number_format(auth()->user()->credit_limit, 2) }}
                            </div>
                            <div class="col-6">
                                <strong>Available Credit:</strong><br>
                                ${{ number_format(auth()->user()->balance + auth()->user()->credit_limit, 2) }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Payments -->
            @if($recentPayments->count() > 0)
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fas fa-history"></i> Recent Payments</h6>
                    <a href="{{ route('customer.payments.history') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    @foreach($recentPayments as $payment)
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <div class="fw-bold">${{ number_format($payment->amount, 2) }}</div>
                            <small class="text-muted">{{ $payment->created_at->format('M d, H:i') }}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-{{ $payment->status === 'completed' ? 'success' : ($payment->status === 'pending' ? 'warning' : 'danger') }}">
                                {{ ucfirst($payment->status) }}
                            </span>
                            <br><small class="text-muted">{{ ucfirst($payment->gateway) }}</small>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Payment Security -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-shield-alt"></i> Secure Payments</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li><i class="fas fa-check text-success"></i> SSL Encrypted</li>
                        <li><i class="fas fa-check text-success"></i> PCI Compliant</li>
                        <li><i class="fas fa-check text-success"></i> Instant Processing</li>
                        <li><i class="fas fa-check text-success"></i> 24/7 Support</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Processing Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Processing Payment</h5>
            </div>
            <div class="modal-body" id="paymentModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Initializing payment...</p>
                </div>
            </div>
            <div class="modal-footer" id="paymentModalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentBalance = {{ auth()->user()->balance }};
let availableCryptos = [];

document.addEventListener('DOMContentLoaded', function() {
    // Load cryptocurrencies
    loadCryptocurrencies();
    
    // Setup form handlers
    setupFormHandlers();
});

function setupFormHandlers() {
    // Amount input handler
    document.getElementById('amount').addEventListener('input', updatePaymentSummary);
    document.getElementById('currency').addEventListener('change', updatePaymentSummary);
    
    // Payment method selection
    document.querySelectorAll('input[name="gateway"]').forEach(radio => {
        radio.addEventListener('change', function() {
            handlePaymentMethodChange(this.value);
        });
    });
    
    // Crypto currency selection
    document.getElementById('crypto_currency').addEventListener('change', updatePaymentSummary);
    
    // Form submission
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        processPayment();
    });
}

function loadCryptocurrencies() {
    fetch('{{ route("customer.payments.crypto-currencies") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                availableCryptos = data.currencies;
                const select = document.getElementById('crypto_currency');
                select.innerHTML = '<option value="">Select cryptocurrency</option>';
                
                data.currencies.forEach(crypto => {
                    const option = document.createElement('option');
                    option.value = crypto;
                    option.textContent = crypto.toUpperCase();
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading cryptocurrencies:', error);
            document.getElementById('crypto_currency').innerHTML = '<option value="">Error loading currencies</option>';
        });
}

function handlePaymentMethodChange(gateway) {
    // Hide all method-specific sections
    document.getElementById('cryptoSelection').style.display = 'none';
    document.getElementById('paypalSelection').style.display = 'none';
    
    // Show relevant section
    if (gateway === 'nowpayments') {
        document.getElementById('cryptoSelection').style.display = 'block';
    } else if (gateway === 'paypal') {
        document.getElementById('paypalSelection').style.display = 'block';
    }
    
    updatePaymentSummary();
}

function updatePaymentSummary() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const currency = document.getElementById('currency').value;
    const gateway = document.querySelector('input[name="gateway"]:checked')?.value;
    
    if (amount > 0 && gateway) {
        let methodName = '';
        let isValid = true;
        
        if (gateway === 'nowpayments') {
            const cryptoCurrency = document.getElementById('crypto_currency').value;
            methodName = cryptoCurrency ? cryptoCurrency.toUpperCase() : 'Cryptocurrency';
            isValid = !!cryptoCurrency;
        } else if (gateway === 'paypal') {
            methodName = 'PayPal';
        }
        
        // Update summary
        document.getElementById('summaryAmount').textContent = `$${amount.toFixed(2)} ${currency}`;
        document.getElementById('summaryMethod').textContent = methodName;
        document.getElementById('summaryNewBalance').textContent = `$${(currentBalance + amount).toFixed(2)}`;
        
        // Show summary and enable button
        document.getElementById('paymentSummary').style.display = 'block';
        
        const button = document.getElementById('paymentButton');
        const buttonText = document.getElementById('paymentButtonText');
        
        if (isValid && amount >= 0.01) {
            button.disabled = false;
            buttonText.textContent = `Pay $${amount.toFixed(2)} with ${methodName}`;
        } else {
            button.disabled = true;
            buttonText.textContent = 'Complete form to continue';
        }
    } else {
        document.getElementById('paymentSummary').style.display = 'none';
        document.getElementById('paymentButton').disabled = true;
        document.getElementById('paymentButtonText').textContent = 'Select Payment Method';
    }
}

function setAmount(amount) {
    document.getElementById('amount').value = amount;
    updatePaymentSummary();
}

function processPayment() {
    const formData = new FormData(document.getElementById('paymentForm'));
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    
    modal.show();
    
    fetch('{{ route("customer.payments.initiate") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            handlePaymentSuccess(data.data);
        } else {
            handlePaymentError(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        handlePaymentError('Payment processing failed. Please try again.');
    });
}

function handlePaymentSuccess(paymentData) {
    const modalBody = document.getElementById('paymentModalBody');
    const modalFooter = document.getElementById('paymentModalFooter');
    
    if (paymentData.approval_url) {
        // PayPal payment - redirect to approval URL
        modalBody.innerHTML = `
            <div class="text-center">
                <i class="fab fa-paypal fa-3x text-primary mb-3"></i>
                <h5>Redirecting to PayPal</h5>
                <p>You will be redirected to PayPal to complete your payment.</p>
            </div>
        `;
        
        modalFooter.innerHTML = `
            <a href="${paymentData.approval_url}" class="btn btn-primary">
                <i class="fab fa-paypal"></i> Continue to PayPal
            </a>
        `;
        
        // Auto-redirect after 3 seconds
        setTimeout(() => {
            window.location.href = paymentData.approval_url;
        }, 3000);
        
    } else if (paymentData.payment_address) {
        // Cryptocurrency payment - show payment details
        modalBody.innerHTML = `
            <div class="text-center">
                <i class="fab fa-bitcoin fa-3x text-warning mb-3"></i>
                <h5>Send ${paymentData.pay_currency.toUpperCase()}</h5>
                <div class="alert alert-info">
                    <strong>Amount:</strong> ${paymentData.pay_amount} ${paymentData.pay_currency.toUpperCase()}<br>
                    <strong>Address:</strong> <code>${paymentData.payment_address}</code>
                </div>
                ${paymentData.qr_code ? `<img src="${paymentData.qr_code}" class="img-fluid mb-3" alt="QR Code">` : ''}
                <p class="small text-muted">
                    Send the exact amount to the address above. 
                    Your account will be credited automatically once the payment is confirmed.
                </p>
            </div>
        `;
        
        modalFooter.innerHTML = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" onclick="checkPaymentStatus(${paymentData.transaction_id})">
                <i class="fas fa-sync-alt"></i> Check Status
            </button>
        `;
    }
}

function handlePaymentError(message) {
    const modalBody = document.getElementById('paymentModalBody');
    const modalFooter = document.getElementById('paymentModalFooter');
    
    modalBody.innerHTML = `
        <div class="text-center">
            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
            <h5>Payment Failed</h5>
            <p>${message}</p>
        </div>
    `;
    
    modalFooter.innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="location.reload()">Try Again</button>
    `;
}

function checkPaymentStatus(transactionId) {
    fetch(`{{ route("customer.payments.status", ":id") }}`.replace(':id', transactionId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const status = data.data.status;
                if (status === 'completed') {
                    showToast('Payment completed successfully!', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else if (status === 'failed') {
                    showToast('Payment failed. Please try again.', 'error');
                } else {
                    showToast(`Payment status: ${status}`, 'info');
                }
            }
        })
        .catch(error => {
            console.error('Error checking payment status:', error);
            showToast('Error checking payment status', 'error');
        });
}

function showToast(message, type = 'info') {
    // Use the toast function from the layout
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}
</script>
@endpush