@extends('layouts.sneat-admin')

@section('title', 'Reports & Analytics')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bx bx-bar-chart-alt-2 me-2"></i>Reports & Analytics
                </h4>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Generate Report
                    </button>
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="bx bx-export"></i> Export All
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Revenue Analytics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="d-flex flex-column">
                                            <div class="avatar mx-auto mb-2">
                                                <span class="avatar-initial rounded bg-label-success"><i class="bx bx-dollar"></i></span>
                                            </div>
                                            <span class="text-muted small">This Month</span>
                                            <h5 class="mb-0">${{ class_exists('\App\Models\PaymentTransaction') ? number_format(\App\Models\PaymentTransaction::where('status', 'completed')->whereMonth('created_at', now()->month)->sum('amount'), 2) : '0.00' }}</h5>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex flex-column">
                                            <div class="avatar mx-auto mb-2">
                                                <span class="avatar-initial rounded bg-label-info"><i class="bx bx-trending-up"></i></span>
                                            </div>
                                            <span class="text-muted small">Last Month</span>
                                            <h5 class="mb-0">${{ class_exists('\App\Models\PaymentTransaction') ? number_format(\App\Models\PaymentTransaction::where('status', 'completed')->whereMonth('created_at', now()->subMonth()->month)->sum('amount'), 2) : '0.00' }}</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Call Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="d-flex flex-column">
                                            <div class="avatar mx-auto mb-2">
                                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-phone"></i></span>
                                            </div>
                                            <span class="text-muted small">Total Calls</span>
                                            <h5 class="mb-0">{{ class_exists('\App\Models\CallRecord') ? \App\Models\CallRecord::count() : 0 }}</h5>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="d-flex flex-column">
                                            <div class="avatar mx-auto mb-2">
                                                <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-time"></i></span>
                                            </div>
                                            <span class="text-muted small">Avg Duration</span>
                                            <h5 class="mb-0">{{ class_exists('\App\Models\CallRecord') ? round(\App\Models\CallRecord::avg('duration') ?? 0) : 0 }}s</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title m-0">Top Customers</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#">View All</a>
                                        <a class="dropdown-item" href="#">Export</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <ul class="p-0 m-0">
                                    @foreach(\App\Models\User::where('role', 'customer')->withCount('paymentTransactions')->orderBy('payment_transactions_count', 'desc')->limit(5)->get() as $customer)
                                    <li class="d-flex mb-4 pb-1">
                                        <div class="avatar flex-shrink-0 me-3">
                                            <img src="{{ asset('sneat/img/avatars/1.png') }}" alt="User" class="rounded" />
                                        </div>
                                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                            <div class="me-2">
                                                <h6 class="mb-0">{{ $customer->name }}</h6>
                                                <small class="text-muted">{{ $customer->email }}</small>
                                            </div>
                                            <div class="user-progress">
                                                <small class="fw-medium">${{ number_format($customer->balance, 2) }}</small>
                                            </div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h5 class="card-title m-0">Recent Activity</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="#">Refresh</a>
                                        <a class="dropdown-item" href="#">Export</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>User</th>
                                                <th>Amount</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(class_exists('\App\Models\PaymentTransaction'))
                                                @foreach(\App\Models\PaymentTransaction::with('user')->latest()->limit(10)->get() as $activity)
                                                <tr>
                                                    <td>
                                                        <i class="bx bx-credit-card text-primary me-2"></i>
                                                        Payment {{ ucfirst($activity->status) }}
                                                    </td>
                                                    <td>{{ $activity->user->name ?? 'Unknown' }}</td>
                                                    <td>${{ number_format($activity->amount, 2) }}</td>
                                                    <td>{{ $activity->created_at->diffForHumans() }}</td>
                                                </tr>
                                                @endforeach
                                            @endif
                                            @if(class_exists('\App\Models\CallRecord'))
                                                @foreach(\App\Models\CallRecord::with('user')->latest()->limit(5)->get() as $call)
                                                <tr>
                                                    <td>
                                                        <i class="bx bx-phone text-success me-2"></i>
                                                        Call {{ ucfirst($call->status) }}
                                                    </td>
                                                    <td>{{ $call->user->name ?? 'Unknown' }}</td>
                                                    <td>${{ number_format($call->cost, 4) }}</td>
                                                    <td>{{ $call->created_at->diffForHumans() }}</td>
                                                </tr>
                                                @endforeach
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection