@extends('layouts.sneat-customer')

@section('title', 'My DID Numbers')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <!-- Statistics Cards -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Total DIDs</h6>
                            <h4 class="mb-0" id="total-dids">{{ $assignedDids->count() }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-phone text-primary"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Monthly Cost</h6>
                            <h4 class="mb-0" id="monthly-cost">${{ number_format($assignedDids->sum('monthly_cost'), 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-dollar text-success"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Expiring Soon</h6>
                            <h4 class="mb-0" id="expiring-soon">{{ $assignedDids->filter(function($did) { return $did->isExpiringSoon(); })->count() }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-time text-warning"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <a href="{{ route('customer.dids.browse') }}" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Purchase DID
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My DID Numbers</h5>
                    <a href="{{ route('customer.dids.browse') }}" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Purchase New DID
                    </a>
                </div>

                <div class="card-body">
                    @if($assignedDids->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>DID Number</th>
                                        <th>Country</th>
                                        <th>Monthly Cost</th>
                                        <th>Status</th>
                                        <th>Assigned Date</th>
                                        <th>Expires</th>
                                        <th>Features</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assignedDids as $did)
                                        <tr>
                                            <td>
                                                <strong>{{ $did->formatted_number }}</strong>
                                                @if($did->assigned_extension)
                                                    <br><small class="text-muted">Ext: {{ $did->assigned_extension }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="fi fi-{{ strtolower($did->country_code) }}"></span>
                                                {{ $did->countryRate->country_name ?? $did->country_code }}
                                            </td>
                                            <td>${{ number_format($did->monthly_cost, 2) }}</td>
                                            <td>
                                                @if($did->status === 'assigned')
                                                    <span class="badge bg-success">Active</span>
                                                @elseif($did->status === 'suspended')
                                                    <span class="badge bg-warning">Suspended</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ ucfirst($did->status) }}</span>
                                                @endif
                                                
                                                @if($did->isExpiringSoon())
                                                    <br><span class="badge bg-warning">Expiring Soon</span>
                                                @endif
                                            </td>
                                            <td>{{ $did->assigned_at->format('M d, Y') }}</td>
                                            <td>
                                                @if($did->expires_at)
                                                    {{ $did->expires_at->format('M d, Y') }}
                                                    @if($did->expires_at->isPast())
                                                        <br><span class="text-danger">Expired</span>
                                                    @elseif($did->isExpiringSoon())
                                                        <br><span class="text-warning">{{ $did->expires_at->diffForHumans() }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">No expiry</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($did->features)
                                                    @foreach($did->features as $feature)
                                                        <span class="badge bg-info">{{ ucfirst($feature) }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="badge bg-info">Voice</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        @if($did->status === 'assigned')
                                                            <a class="dropdown-item" href="{{ route('customer.dids.configure', $did->id) }}">
                                                                <i class="bx bx-cog"></i> Configure
                                                            </a>
                                                            @if($did->expires_at && $did->expires_at->isFuture())
                                                                <a class="dropdown-item" href="#" onclick="renewDid({{ $did->id }})">
                                                                    <i class="bx bx-refresh"></i> Renew
                                                                </a>
                                                            @endif
                                                            <div class="dropdown-divider"></div>
                                                        @endif
                                                        <a class="dropdown-item" href="#" onclick="viewBillingHistory({{ $did->id }})">
                                                            <i class="bx bx-history"></i> Billing History
                                                        </a>
                                                        @if($did->status === 'assigned')
                                                            <div class="dropdown-divider"></div>
                                                            <a class="dropdown-item" href="{{ route('customer.dids.transfer', $did->id) }}">
                                                                <i class="bx bx-transfer"></i> Transfer DID
                                                            </a>
                                                            <a class="dropdown-item text-danger" href="#" onclick="releaseDid({{ $did->id }})">
                                                                <i class="bx bx-trash"></i> Release DID
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-primary">
                                    <i class="bx bx-phone bx-lg"></i>
                                </span>
                            </div>
                            <h5>No DID Numbers</h5>
                            <p class="text-muted">You don't have any DID numbers yet. Purchase your first DID to start receiving calls.</p>
                            <a href="{{ route('customer.dids.browse') }}" class="btn btn-primary">
                                <i class="bx bx-plus"></i> Purchase Your First DID
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Billing History Modal -->
<div class="modal fade" id="billingHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">DID Billing History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="billing-history-content">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function renewDid(didId) {
    if (confirm('Are you sure you want to renew this DID number for another month?')) {
        $.ajax({
            url: `/customer/dids/${didId}/renew`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                if (response && response.required_amount) {
                    showAlert('error', `${response.message}<br>Required: $${response.required_amount.toFixed(2)}<br>Your Balance: $${response.current_balance.toFixed(2)}`);
                } else {
                    showAlert('error', xhr.responseJSON?.message || 'Failed to renew DID');
                }
            }
        });
    }
}

function releaseDid(didId) {
    if (confirm('Are you sure you want to release this DID number? You will lose access to this number and any prorated refund will be calculated.')) {
        $.ajax({
            url: `/customer/dids/${didId}/release`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            },
            error: function(xhr) {
                showAlert('error', xhr.responseJSON?.message || 'Failed to release DID');
            }
        });
    }
}

function viewBillingHistory(didId) {
    $('#billingHistoryModal').modal('show');
    
    $.ajax({
        url: `/customer/dids/${didId}/billing-history`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                let content = '';
                if (response.transactions.length > 0) {
                    content = `
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Balance After</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    
                    response.transactions.forEach(transaction => {
                        content += `
                            <tr>
                                <td>${transaction.processed_at}</td>
                                <td>${transaction.type}</td>
                                <td>${transaction.description}</td>
                                <td>${transaction.amount}</td>
                                <td>${transaction.balance_after}</td>
                            </tr>`;
                    });
                    
                    content += `
                                </tbody>
                            </table>
                        </div>`;
                } else {
                    content = '<p class="text-center text-muted">No billing history found for this DID number.</p>';
                }
                
                $('#billing-history-content').html(content);
            }
        },
        error: function(xhr) {
            $('#billing-history-content').html('<p class="text-center text-danger">Failed to load billing history.</p>');
        }
    });
}

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alert = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.container-xxl').prepend(alert);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>
@endpush