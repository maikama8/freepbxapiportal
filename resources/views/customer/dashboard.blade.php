@extends('layouts.sneat-customer')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Account Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Account Balance</h5>
                            <h3>${{ number_format($user->balance, 2) }}</h3>
                            <small>{{ ucfirst($user->account_type) }} Account</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bx bx-wallet bx-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Today's Calls</h5>
                            <h3>{{ $stats['total_calls_today'] }}</h3>
                            <small>Spent: ${{ number_format($stats['total_spent_today'], 2) }}</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bx bx-phone bx-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">This Month</h5>
                            <h3>{{ $stats['total_calls_month'] }} calls</h3>
                            <small>Spent: ${{ number_format($stats['total_spent_month'], 2) }}</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bx bx-line-chart bx-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white {{ $stats['active_calls'] > 0 ? 'bg-danger' : 'bg-secondary' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Active Calls</h5>
                            <h3>{{ $stats['active_calls'] }}</h3>
                            <small>{{ $user->status === 'active' ? 'Online' : ucfirst($user->status) }}</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bx bx-{{ $stats['active_calls'] > 0 ? 'phone-call' : 'phone-off' }} bx-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bx bx-zap"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('customer.calls.make') }}" class="btn btn-primary">
                            <i class="bx bx-phone"></i> Make a Call
                        </a>
                        <a href="{{ route('customer.payments.add-funds') }}" class="btn btn-success">
                            <i class="bx bx-plus-circle"></i> Add Funds
                        </a>
                        <a href="{{ route('customer.call-history') }}" class="btn btn-info">
                            <i class="bx bx-history"></i> View Call History
                        </a>
                        <a href="{{ route('customer.account-settings') }}" class="btn btn-secondary">
                            <i class="bx bx-cog"></i> Account Settings
                        </a>
                    </div>
                </div>
            </div>

            <!-- SIP Account Information -->
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bx bx-phone"></i> SIP Account</h5>
                    <a href="{{ route('customer.sip-accounts.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bx bx-cog"></i> Manage
                    </a>
                </div>
                <div class="card-body">
                    @if($user->primarySipAccount)
                        @php $primarySip = $user->primarySipAccount @endphp
                        <div class="mb-2">
                            <small class="text-muted">SIP Username:</small>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>{{ $primarySip->sip_username }}</strong>
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('{{ $primarySip->sip_username }}')">
                                    <i class="bx bx-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">SIP Server:</small>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>{{ $primarySip->sip_server }}</span>
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('{{ $primarySip->sip_server }}')">
                                    <i class="bx bx-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Status:</small>
                            <span class="badge bg-{{ $primarySip->status === 'active' ? 'success' : 'secondary' }}">
                                {{ ucfirst($primarySip->status) }}
                            </span>
                        </div>
                        @if($user->sipAccounts->count() > 1)
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    +{{ $user->sipAccounts->count() - 1 }} more accounts
                                </small>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-2">
                            <i class="bx bx-phone text-muted"></i>
                            <div class="text-muted small">No SIP account configured</div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Account Information -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="bx bx-user"></i> Account Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>{{ $user->name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>{{ $user->email }}</td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td>{{ $user->phone ?? 'Not set' }}</td>
                        </tr>
                        <tr>
                            <td><strong>SIP Extension:</strong></td>
                            <td>{{ $user->extension ?? 'Not assigned' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Credit Limit:</strong></td>
                            <td>${{ number_format($user->credit_limit, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Currency:</strong></td>
                            <td>{{ $user->currency }}</td>
                        </tr>
                        <tr>
                            <td><strong>Timezone:</strong></td>
                            <td>{{ $user->timezone }}</td>
                        </tr>
                        <tr>
                            <td><strong>Member Since:</strong></td>
                            <td>{{ $user->created_at->format('M d, Y') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-md-8">
            <!-- Recent Calls -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bx bx-phone"></i> Recent Calls</h5>
                    <a href="{{ route('customer.call-history') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    @if($recentCalls->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Destination</th>
                                        <th>Duration</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentCalls as $call)
                                    <tr>
                                        <td>{{ $call->created_at->format('M d, H:i') }}</td>
                                        <td>{{ $call->destination }}</td>
                                        <td>{{ $call->getFormattedDuration() }}</td>
                                        <td>${{ number_format($call->cost, 4) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $call->status === 'completed' ? 'success' : ($call->isActive() ? 'warning' : 'danger') }}">
                                                {{ ucfirst($call->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bx bx-phone-off bx-lg mb-3"></i>
                            <p>No calls made yet. <a href="{{ route('customer.calls.make') }}">Make your first call</a></p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bx bx-transfer"></i> Recent Transactions</h5>
                    <a href="{{ route('customer.balance-history') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    @if($recentTransactions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Balance After</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentTransactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->created_at->format('M d, H:i') }}</td>
                                        <td>{{ $transaction->description }}</td>
                                        <td>
                                            <span class="text-{{ $transaction->isCredit() ? 'success' : 'danger' }}">
                                                {{ $transaction->getFormattedAmount() }}
                                            </span>
                                        </td>
                                        <td>${{ number_format($transaction->balance_after, 4) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="bx bx-receipt bx-lg mb-3"></i>
                            <p>No transactions yet. <a href="{{ route('customer.payments.add-funds') }}">Add funds to get started</a></p>
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
// Auto-refresh active calls count every 30 seconds
setInterval(function() {
    fetch('{{ route("customer.active-calls-count") }}')
        .then(response => response.json())
        .then(data => {
            // Update active calls display
            const activeCallsCard = document.querySelector('.card.text-white.bg-danger, .card.text-white.bg-secondary');
            if (activeCallsCard) {
                const countElement = activeCallsCard.querySelector('h3');
                const statusElement = activeCallsCard.querySelector('small');
                
                if (countElement) countElement.textContent = data.active_calls;
                
                // Update card color based on active calls
                if (data.active_calls > 0) {
                    activeCallsCard.className = activeCallsCard.className.replace('bg-secondary', 'bg-danger');
                } else {
                    activeCallsCard.className = activeCallsCard.className.replace('bg-danger', 'bg-secondary');
                }
            }
        })
        .catch(error => console.log('Error fetching active calls:', error));
}, 30000);

// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    Copied to clipboard!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Remove toast after it's hidden
        toast.addEventListener('hidden.bs.toast', function() {
            document.body.removeChild(toast);
        });
    });
}
</script>
@endpush