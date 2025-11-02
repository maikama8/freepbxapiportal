@extends('layouts.customer')

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
                            <i class="fas fa-wallet fa-2x"></i>
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
                            <i class="fas fa-phone fa-2x"></i>
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
                            <i class="fas fa-chart-line fa-2x"></i>
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
                            <i class="fas fa-{{ $stats['active_calls'] > 0 ? 'phone-volume' : 'phone-slash' }} fa-2x"></i>
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
                    <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('customer.calls.make') }}" class="btn btn-primary">
                            <i class="fas fa-phone"></i> Make a Call
                        </a>
                        <a href="{{ route('customer.payments.add-funds') }}" class="btn btn-success">
                            <i class="fas fa-plus-circle"></i> Add Funds
                        </a>
                        <a href="{{ route('customer.call-history') }}" class="btn btn-info">
                            <i class="fas fa-history"></i> View Call History
                        </a>
                        <a href="{{ route('customer.account-settings') }}" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Account Settings
                        </a>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-user"></i> Account Information</h5>
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
                    <h5><i class="fas fa-phone"></i> Recent Calls</h5>
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
                            <i class="fas fa-phone-slash fa-3x mb-3"></i>
                            <p>No calls made yet. <a href="{{ route('customer.calls.make') }}">Make your first call</a></p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-exchange-alt"></i> Recent Transactions</h5>
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
                            <i class="fas fa-receipt fa-3x mb-3"></i>
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
</script>
@endpush