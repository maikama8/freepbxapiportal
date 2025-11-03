@extends('layouts.sneat-admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8 mb-4 order-0">
        <div class="card">
            <div class="d-flex align-items-end row">
                <div class="col-sm-7">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Welcome {{ auth()->user()->name }}! 🎉</h5>
                        <p class="mb-4">
                            You have <span class="fw-medium">{{ \App\Models\User::where('status', 'active')->count() }}</span> active users in your VoIP platform today.
                        </p>
                        <a href="{{ route('admin.customers.index') }}" class="btn btn-sm btn-outline-primary">View Customers</a>
                    </div>
                </div>
                <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-4">
                        <img src="{{ asset('sneat/img/illustrations/man-with-laptop-light.png') }}" height="140" alt="View Badge User" data-app-dark-img="illustrations/man-with-laptop-dark.png" data-app-light-img="illustrations/man-with-laptop-light.png" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 order-1">
        <div class="row">
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <img src="{{ asset('sneat/img/icons/unicons/chart-success.png') }}" alt="chart success" class="rounded" />
                            </div>
                        </div>
                        <span class="fw-medium d-block mb-1">Total Revenue</span>
                        <h3 class="card-title mb-2">$12,628</h3>
                        <small class="text-success fw-medium"><i class="bx bx-up-arrow-alt"></i> +72.80%</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <img src="{{ asset('sneat/img/icons/unicons/wallet-info.png') }}" alt="Credit Card" class="rounded" />
                            </div>
                        </div>
                        <span class="d-block mb-1">Active Calls</span>
                        <h3 class="card-title text-nowrap mb-1">{{ class_exists('\App\Models\CallRecord') ? \App\Models\CallRecord::whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])->count() : 0 }}</h3>
                        <small class="text-success fw-medium"><i class="bx bx-up-arrow-alt"></i> +28.42%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Statistics Cards -->
    <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between pb-0">
                <div class="card-title mb-0">
                    <h5 class="m-0 me-2">User Statistics</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex flex-column align-items-center gap-1">
                        <h2 class="mb-2">{{ \App\Models\User::count() }}</h2>
                        <span>Total Users</span>
                    </div>
                    <div id="orderStatisticsChart"></div>
                </div>
                <ul class="p-0 m-0">
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-user"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Active Users</h6>
                                <small class="text-muted">Currently Active</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">{{ \App\Models\User::where('status', 'active')->count() }}</small>
                            </div>
                        </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-info"><i class="bx bx-group"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Customers</h6>
                                <small class="text-muted">Customer Accounts</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">{{ \App\Models\User::where('role', 'customer')->count() }}</small>
                            </div>
                        </div>
                    </li>
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-credit-card"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Prepaid</h6>
                                <small class="text-muted">Prepaid Accounts</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">{{ \App\Models\User::where('account_type', 'prepaid')->count() }}</small>
                            </div>
                        </div>
                    </li>
                    <li class="d-flex">
                        <div class="avatar flex-shrink-0 me-3">
                            <span class="avatar-initial rounded bg-label-secondary"><i class="bx bx-wallet"></i></span>
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <h6 class="mb-0">Postpaid</h6>
                                <small class="text-muted">Postpaid Accounts</small>
                            </div>
                            <div class="user-progress">
                                <small class="fw-medium">{{ \App\Models\User::where('account_type', 'postpaid')->count() }}</small>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="col-md-6 col-lg-4 order-1 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title m-0 me-2">Recent Users</h5>
            </div>
            <div class="card-body">
                <ul class="p-0 m-0">
                    @foreach(\App\Models\User::latest()->limit(5)->get() as $user)
                    <li class="d-flex mb-4 pb-1">
                        <div class="avatar flex-shrink-0 me-3">
                            <img src="{{ asset('sneat/img/avatars/1.png') }}" alt="User" class="rounded" />
                        </div>
                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                            <div class="me-2">
                                <small class="text-muted d-block mb-1">{{ $user->name }}</small>
                                <h6 class="mb-0">{{ $user->email }}</h6>
                            </div>
                            <div class="user-progress d-flex align-items-center gap-1">
                                <span class="badge bg-label-{{ $user->role === 'admin' ? 'primary' : ($user->role === 'customer' ? 'success' : 'info') }}">{{ $user->role }}</span>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-md-6 col-lg-4 order-2 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title m-0 me-2">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary d-flex align-items-center">
                        <i class="bx bx-user-plus me-2"></i>
                        Add New Customer
                    </a>
                    <a href="{{ route('admin.rates.index') }}" class="btn btn-outline-secondary d-flex align-items-center">
                        <i class="bx bx-dollar me-2"></i>
                        Manage Rates
                    </a>
                    <a href="{{ route('admin.settings') }}" class="btn btn-outline-info d-flex align-items-center">
                        <i class="bx bx-cog me-2"></i>
                        System Settings
                    </a>
                    <a href="{{ route('admin.monitoring.index') }}" class="btn btn-outline-warning d-flex align-items-center">
                        <i class="bx bx-line-chart me-2"></i>
                        View Monitoring
                    </a>
                </div>
                
                <hr class="my-4">
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="d-flex flex-column">
                            <div class="avatar mx-auto mb-2">
                                <span class="avatar-initial rounded bg-label-success"><i class="bx bx-phone"></i></span>
                            </div>
                            <span class="text-muted small">SIP Extensions</span>
                            <h6 class="mb-0">{{ \App\Models\User::whereNotNull('sip_username')->count() }}</h6>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column">
                            <div class="avatar mx-auto mb-2">
                                <span class="avatar-initial rounded bg-label-danger"><i class="bx bx-server"></i></span>
                            </div>
                            <span class="text-muted small">System Health</span>
                            <h6 class="mb-0 text-success">Online</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- System Overview -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title m-0">System Overview</h5>
                <div class="dropdown">
                    <button class="btn p-0" type="button" id="systemOverview" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="systemOverview">
                        <a class="dropdown-item" href="{{ route('admin.system.index') }}">System Information</a>
                        <a class="dropdown-item" href="{{ route('admin.monitoring.index') }}">Monitoring</a>
                        <a class="dropdown-item" href="{{ route('admin.audit.index') }}">Audit Logs</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar">
                                <div class="avatar-initial bg-primary rounded shadow">
                                    <i class="bx bx-group"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <div class="small mb-1">Total Users</div>
                                <h5 class="mb-0">{{ \App\Models\User::count() }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar">
                                <div class="avatar-initial bg-success rounded shadow">
                                    <i class="bx bx-user-check"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <div class="small mb-1">Active Users</div>
                                <h5 class="mb-0">{{ \App\Models\User::where('status', 'active')->count() }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar">
                                <div class="avatar-initial bg-info rounded shadow">
                                    <i class="bx bx-phone"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <div class="small mb-1">SIP Extensions</div>
                                <h5 class="mb-0">{{ \App\Models\User::whereNotNull('sip_username')->count() }}</h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar">
                                <div class="avatar-initial bg-warning rounded shadow">
                                    <i class="bx bx-dollar-circle"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <div class="small mb-1">Call Rates</div>
                                <h5 class="mb-0">{{ class_exists('\App\Models\CallRate') ? \App\Models\CallRate::count() : 0 }}</h5>
                            </div>
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
// Auto-refresh dashboard data every 30 seconds
setInterval(function() {
    // You can add AJAX calls here to refresh specific data
    console.log('Dashboard data refresh...');
}, 30000);
</script>
@endpush