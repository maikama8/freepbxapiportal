@extends('layouts.customer')

@section('title', 'Invoices')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-file-invoice"></i> Invoices</h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-3">
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
                                <a href="{{ route('customer.invoices') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>

                    @if(request()->hasAny(['date_from', 'date_to', 'status']))
                        <div class="mb-3">
                            <span class="badge bg-info">
                                Filtered results - <a href="{{ route('customer.invoices') }}" class="text-white">Clear filters</a>
                            </span>
                        </div>
                    @endif

                    <!-- Invoice Statistics -->
                    @php
                        $totalAmount = $invoices->sum('total_amount');
                        $paidAmount = $invoices->where('status', 'paid')->sum('total_amount');
                        $unpaidAmount = $invoices->where('status', 'unpaid')->sum('total_amount');
                        $overdueAmount = $invoices->where('status', 'overdue')->sum('total_amount');
                    @endphp
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total Amount</h5>
                                    <h3>${{ number_format($totalAmount, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Paid</h5>
                                    <h3>${{ number_format($paidAmount, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5>Unpaid</h5>
                                    <h3>${{ number_format($unpaidAmount, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5>Overdue</h5>
                                    <h3>${{ number_format($overdueAmount, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices Table -->
                    @if($invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices as $invoice)
                                    <tr>
                                        <td>
                                            <strong>{{ $invoice->invoice_number }}</strong>
                                        </td>
                                        <td>
                                            <div>{{ $invoice->created_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $invoice->created_at->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <div>{{ $invoice->due_date->format('M d, Y') }}</div>
                                            @if($invoice->due_date->isPast() && $invoice->status !== 'paid')
                                                <small class="text-danger">
                                                    <i class="fas fa-exclamation-triangle"></i> 
                                                    {{ $invoice->due_date->diffForHumans() }}
                                                </small>
                                            @else
                                                <small class="text-muted">{{ $invoice->due_date->diffForHumans() }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ number_format($invoice->total_amount, 2) }}</strong>
                                            @if($invoice->tax_amount > 0)
                                                <br><small class="text-muted">
                                                    Subtotal: ${{ number_format($invoice->subtotal_amount, 2) }}<br>
                                                    Tax: ${{ number_format($invoice->tax_amount, 2) }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ 
                                                $invoice->status === 'paid' ? 'success' : 
                                                ($invoice->status === 'unpaid' ? 'warning' : 
                                                ($invoice->status === 'overdue' ? 'danger' : 'secondary')) 
                                            }}">
                                                {{ ucfirst($invoice->status) }}
                                            </span>
                                            @if($invoice->paid_at)
                                                <br><small class="text-muted">Paid: {{ $invoice->paid_at->format('M d, Y') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#invoiceDetailsModal{{ $invoice->id }}">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <a href="{{ route('customer.invoices.download', $invoice) }}" 
                                                   class="btn btn-outline-primary" target="_blank">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                @if($invoice->status !== 'paid')
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="payInvoice({{ $invoice->id }})">
                                                        <i class="fas fa-credit-card"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Invoice Details Modal -->
                                    <div class="modal fade" id="invoiceDetailsModal{{ $invoice->id }}" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Invoice {{ $invoice->invoice_number }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Invoice Information</h6>
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <td><strong>Invoice Number:</strong></td>
                                                                    <td>{{ $invoice->invoice_number }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Date:</strong></td>
                                                                    <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Due Date:</strong></td>
                                                                    <td>{{ $invoice->due_date->format('M d, Y') }}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Status:</strong></td>
                                                                    <td>
                                                                        <span class="badge bg-{{ 
                                                                            $invoice->status === 'paid' ? 'success' : 
                                                                            ($invoice->status === 'unpaid' ? 'warning' : 'danger') 
                                                                        }}">
                                                                            {{ ucfirst($invoice->status) }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                @if($invoice->paid_at)
                                                                <tr>
                                                                    <td><strong>Paid Date:</strong></td>
                                                                    <td>{{ $invoice->paid_at->format('M d, Y H:i') }}</td>
                                                                </tr>
                                                                @endif
                                                            </table>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Amount Breakdown</h6>
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <td><strong>Subtotal:</strong></td>
                                                                    <td>${{ number_format($invoice->subtotal_amount, 2) }}</td>
                                                                </tr>
                                                                @if($invoice->tax_amount > 0)
                                                                <tr>
                                                                    <td><strong>Tax:</strong></td>
                                                                    <td>${{ number_format($invoice->tax_amount, 2) }}</td>
                                                                </tr>
                                                                @endif
                                                                <tr class="table-primary">
                                                                    <td><strong>Total:</strong></td>
                                                                    <td><strong>${{ number_format($invoice->total_amount, 2) }}</strong></td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Invoice Items -->
                                                    @if($invoice->items->count() > 0)
                                                    <hr>
                                                    <h6>Invoice Items</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Description</th>
                                                                    <th>Quantity</th>
                                                                    <th>Rate</th>
                                                                    <th>Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($invoice->items as $item)
                                                                <tr>
                                                                    <td>{{ $item->description }}</td>
                                                                    <td>{{ $item->quantity }}</td>
                                                                    <td>${{ number_format($item->rate, 4) }}</td>
                                                                    <td>${{ number_format($item->amount, 2) }}</td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <a href="{{ route('customer.invoices.download', $invoice) }}" 
                                                       class="btn btn-primary" target="_blank">
                                                        <i class="fas fa-download"></i> Download PDF
                                                    </a>
                                                    @if($invoice->status !== 'paid')
                                                        <button type="button" class="btn btn-success" 
                                                                onclick="payInvoice({{ $invoice->id }})">
                                                            <i class="fas fa-credit-card"></i> Pay Now
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
                                Showing {{ $invoices->firstItem() }} to {{ $invoices->lastItem() }} of {{ $invoices->total() }} results
                            </div>
                            <div>
                                {{ $invoices->appends(request()->query())->links() }}
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-file-invoice fa-4x mb-3"></i>
                            <h5>No invoices found</h5>
                            <p>
                                @if(request()->hasAny(['date_from', 'date_to', 'status']))
                                    Try adjusting your filters or <a href="{{ route('customer.invoices') }}">clear all filters</a>.
                                @else
                                    @if(auth()->user()->isPostpaid())
                                        You don't have any invoices yet. Invoices are generated monthly for postpaid accounts.
                                    @else
                                        Invoices are only available for postpaid accounts. Your account is prepaid.
                                    @endif
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
function payInvoice(invoiceId) {
    // Redirect to add funds page with invoice context
    const url = new URL('{{ route("customer.payments.add-funds") }}', window.location.origin);
    url.searchParams.set('invoice_id', invoiceId);
    window.location.href = url.toString();
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