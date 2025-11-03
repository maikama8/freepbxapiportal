@extends('layouts.sneat-customer')

@section('title', 'Payment History')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-credit-card"></i> Payment History</h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="gateway" class="form-label">Gateway</label>
                            <select class="form-select" id="gateway" name="gateway">
                                <option value="">All Gateways</option>
                                @foreach($gateways as $gateway)
                                    <option value="{{ $gateway }}" {{ request('gateway') === $gateway ? 'selected' : '' }}>
                                        {{ ucfirst($gateway) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="{{ route('customer.payments.history') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>

                    @if(request()->hasAny(['date_from', 'date_to', 'status', 'gateway']))
                        <div class="mb-3">
                            <span class="badge bg-info">
                                Filtered results - <a href="{{ route('customer.payments.history') }}" class="text-white">Clear filters</a>
                            </span>
                        </div>
                    @endif

                    <!-- Payment Statistics -->
                    @php
                        $totalAmount = $payments->sum('amount');
                        $completedAmount = $payments->where('status', 'completed')->sum('amount');
                        $pendingAmount = $payments->where('status', 'pending')->sum('amount');
                    @endphp
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total Payments</h5>
                                    <h3>${{ number_format($totalAmount, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Completed</h5>
                                    <h3>${{ number_format($completedAmount, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5>Pending</h5>
                                    <h3>${{ number_format($pendingAmount, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Transactions</h5>
                                    <h3>{{ $payments->total() }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Records Table -->
                    @if($payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Amount</th>
                                        <th>Gateway</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Transaction ID</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payments as $payment)
                                    <tr>
                                        <td>
                                            <div>{{ $payment->created_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $payment->created_at->format('H:i:s') }}</small>
                                        </td>
                                        <td>
                                            <strong>${{ number_format($payment->amount, 2) }}</strong>
                                            <br><small class="text-muted">{{ $payment->currency }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                {{ ucfirst($payment->gateway) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($payment->gateway === 'nowpayments')
                                                <i class="fab fa-bitcoin text-warning"></i> {{ strtoupper($payment->payment_method) }}
                                            @elseif($payment->gateway === 'paypal')
                                                <i class="fab fa-paypal text-primary"></i> PayPal
                                            @else
                                                {{ ucfirst($payment->payment_method) }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ 
                                                $payment->status === 'completed' ? 'success' : 
                                                ($payment->status === 'pending' ? 'warning' : 
                                                ($payment->status === 'failed' ? 'danger' : 'secondary')) 
                                            }}">
                                                {{ ucfirst($payment->status) }}
                                            </span>
                                            @if($payment->completed_at)
                                                <br><small class="text-muted">{{ $payment->completed_at->format('M d, H:i') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <code class="small">{{ $payment->gateway_transaction_id }}</code>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#paymentDetailsModal{{ $payment->id }}">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                @if($payment->status === 'pending')
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="checkPaymentStatus({{ $payment->id }})">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Payment Details Modal -->
                                    <div class="modal fade" id="paymentDetailsModal{{ $payment->id }}" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Payment Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Transaction Information</h6>
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <td><strong>ID:</strong></td>
                                                                    <td>{{ $payment->id }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Amount:</strong></td>
                                                                    <td>${{ number_format($payment->amount, 2) }} {{ $payment->currency }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Gateway:</strong></td>
                                                                    <td>{{ ucfirst($payment->gateway) }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Payment Method:</strong></td>
                                                                    <td>{{ ucfirst($payment->payment_method) }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Status:</strong></td>
                                                                    <td>
                                                                        <span class="badge bg-{{ 
                                                                            $payment->status === 'completed' ? 'success' : 
                                                                            ($payment->status === 'pending' ? 'warning' : 'danger') 
                                                                        }}">
                                                                            {{ ucfirst($payment->status) }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Created:</strong></td>
                                                                    <td>{{ $payment->created_at->format('M d, Y H:i:s') }}</td>
                                                                </tr>
                                                                @if($payment->completed_at)
                                                                <tr>
                                                                    <td><strong>Completed:</strong></td>
                                                                    <td>{{ $payment->completed_at->format('M d, Y H:i:s') }}</td>
                                                                </tr>
                                                                @endif
                                                            </table>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Gateway Information</h6>
                                                            @if($payment->gateway === 'nowpayments' && $payment->metadata)
                                                                <table class="table table-sm">
                                                                    @if(isset($payment->metadata['pay_address']))
                                                                    <tr>
                                                                        <td><strong>Payment Address:</strong></td>
                                                                        <td><code class="small">{{ $payment->metadata['pay_address'] }}</code></td>
                                                                    </tr>
                                                                    @endif
                                                                    @if(isset($payment->metadata['pay_amount']))
                                                                    <tr>
                                                                        <td><strong>Pay Amount:</strong></td>
                                                                        <td>{{ $payment->metadata['pay_amount'] }} {{ strtoupper($payment->payment_method) }}</td>
                                                                    </tr>
                                                                    @endif
                                                                    @if(isset($payment->metadata['actually_paid']))
                                                                    <tr>
                                                                        <td><strong>Actually Paid:</strong></td>
                                                                        <td>{{ $payment->metadata['actually_paid'] }} {{ strtoupper($payment->payment_method) }}</td>
                                                                    </tr>
                                                                    @endif
                                                                </table>
                                                            @else
                                                                <p class="text-muted">Gateway transaction ID: <code>{{ $payment->gateway_transaction_id }}</code></p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    @if($payment->status === 'pending')
                                                        <button type="button" class="btn btn-primary" 
                                                                onclick="checkPaymentStatus({{ $payment->id }})">
                                                            <i class="fas fa-sync-alt"></i> Check Status
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                Showing {{ $payments->firstItem() }} to {{ $payments->lastItem() }} of {{ $payments->total() }} results
                            </div>
                            <div>
                                {{ $payments->appends(request()->query())->links() }}
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-credit-card fa-4x mb-3"></i>
                            <h5>No payments found</h5>
                            <p>
                                @if(request()->hasAny(['date_from', 'date_to', 'status', 'gateway']))
                                    Try adjusting your filters or <a href="{{ route('customer.payments.history') }}">clear all filters</a>.
                                @else
                                    You haven't made any payments yet. <a href="{{ route('customer.payments.add-funds') }}">Add funds to get started</a>
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function checkPaymentStatus(paymentId) {
    const button = document.querySelector(`button[onclick="checkPaymentStatus(${paymentId})"]`);
    const icon = button.querySelector('i');
    
    // Add spinning animation
    icon.classList.add('fa-spin');
    button.disabled = true;
    
    fetch(`{{ route("customer.payments.status", ":id") }}`.replace(':id', paymentId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const status = data.data.status;
                if (status === 'completed') {
                    showToast('Payment completed successfully!', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else if (status === 'failed') {
                    showToast('Payment failed.', 'error');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(`Payment status: ${status}`, 'info');
                }
            } else {
                showToast('Error checking payment status', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error checking payment status', 'error');
        })
        .finally(() => {
            // Remove spinning animation
            icon.classList.remove('fa-spin');
            button.disabled = false;
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