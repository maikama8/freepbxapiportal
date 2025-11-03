@extends('layouts.sneat-admin')

@section('title', 'Billing & Invoices')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bx bx-receipt me-2"></i>Billing & Invoices
                </h4>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Generate Invoice
                    </button>
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="bx bx-export"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-receipt bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\Invoice') ? \App\Models\Invoice::count() : 0 }}</h4>
                                <p class="mb-0">Total Invoices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-check-circle bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\Invoice') ? \App\Models\Invoice::where('status', 'paid')->count() : 0 }}</h4>
                                <p class="mb-0">Paid Invoices</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-time bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\Invoice') ? \App\Models\Invoice::where('status', 'pending')->count() : 0 }}</h4>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-x-circle bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\Invoice') ? \App\Models\Invoice::where('status', 'overdue')->count() : 0 }}</h4>
                                <p class="mb-0">Overdue</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(class_exists('\App\Models\Invoice'))
                                @forelse(\App\Models\Invoice::with('user')->latest()->limit(20)->get() as $invoice)
                                <tr>
                                    <td><code>{{ $invoice->invoice_number }}</code></td>
                                    <td>{{ $invoice->user->name ?? 'Unknown' }}</td>
                                    <td>${{ number_format($invoice->total_amount, 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($invoice->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : '-' }}</td>
                                    <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"><i class="bx bx-show me-1"></i> View</a>
                                                <a class="dropdown-item" href="#"><i class="bx bx-download me-1"></i> Download PDF</a>
                                                <a class="dropdown-item" href="#"><i class="bx bx-envelope me-1"></i> Send Email</a>
                                                @if($invoice->status !== 'paid')
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item text-success" href="#"><i class="bx bx-check me-1"></i> Mark as Paid</a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bx bx-receipt bx-lg text-muted mb-2"></i>
                                            <p class="text-muted">No invoices found</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            @else
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bx bx-info-circle bx-lg text-info mb-2"></i>
                                            <p class="text-muted">Invoice table not available</p>
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