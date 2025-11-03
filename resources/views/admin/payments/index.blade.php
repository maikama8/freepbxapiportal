@extends('layouts.sneat-admin')

@section('title', 'Payment Management')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bx bx-credit-card me-2"></i>Payment Management
                </h4>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Manual Payment
                    </button>
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="bx bx-export"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-check-circle bx-lg mb-2"></i>
                                <h4>${{ class_exists('\App\Models\PaymentTransaction') ? number_format(\App\Models\PaymentTransaction::where('status', 'completed')->sum('amount'), 2) : '0.00' }}</h4>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-credit-card bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\PaymentTransaction') ? \App\Models\PaymentTransaction::whereDate('created_at', today())->count() : 0 }}</h4>
                                <p class="mb-0">Today's Payments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-time bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\PaymentTransaction') ? \App\Models\PaymentTransaction::where('status', 'pending')->count() : 0 }}</h4>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-x-circle bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\PaymentTransaction') ? \App\Models\PaymentTransaction::where('status', 'failed')->count() : 0 }}</h4>
                                <p class="mb-0">Failed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Gateway</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(class_exists('\App\Models\PaymentTransaction'))
                                @forelse(\App\Models\PaymentTransaction::with('user')->latest()->limit(20)->get() as $payment)
                                <tr>
                                    <td><code>{{ $payment->transaction_id }}</code></td>
                                    <td>{{ $payment->user->name ?? 'Unknown' }}</td>
                                    <td>${{ number_format($payment->amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ ucfirst($payment->gateway) }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $payment->status === 'completed' ? 'success' : ($payment->status === 'failed' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $payment->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"><i class="bx bx-show me-1"></i> View Details</a>
                                                @if($payment->status === 'pending')
                                                <a class="dropdown-item text-success" href="#"><i class="bx bx-check me-1"></i> Approve</a>
                                                <a class="dropdown-item text-danger" href="#"><i class="bx bx-x me-1"></i> Reject</a>
                                                @endif
                                                <a class="dropdown-item" href="#"><i class="bx bx-receipt me-1"></i> Receipt</a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bx bx-credit-card bx-lg text-muted mb-2"></i>
                                            <p class="text-muted">No payments found</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            @else
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bx bx-info-circle bx-lg text-info mb-2"></i>
                                            <p class="text-muted">Payment transactions table not available</p>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection