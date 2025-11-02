<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">{{ config('app.name') }} - Admin</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})
                </span>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-2">
                <div class="list-group">
                    <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action active">Dashboard</a>
                    <a href="{{ route('admin.customers.index') }}" class="list-group-item list-group-item-action">Customer Management</a>
                    <a href="{{ route('admin.rates.index') }}" class="list-group-item list-group-item-action">Rate Management</a>
                    <a href="{{ route('admin.calls.index') }}" class="list-group-item list-group-item-action">Call Management</a>
                    <a href="{{ route('admin.billing.index') }}" class="list-group-item list-group-item-action">Billing & Invoices</a>
                    <a href="{{ route('admin.payments.index') }}" class="list-group-item list-group-item-action">Payment Management</a>
                    <a href="{{ route('admin.reports.index') }}" class="list-group-item list-group-item-action">Reports & Analytics</a>
                    <a href="{{ route('admin.system.index') }}" class="list-group-item list-group-item-action">System Settings</a>
                    <a href="{{ route('admin.audit.index') }}" class="list-group-item list-group-item-action">Audit Logs</a>
                </div>
            </div>
            
            <div class="col-md-10">
                <h2>Admin Dashboard</h2>
                
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <h3>{{ \App\Models\User::count() }}</h3>
                                <small>Registered Users</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Active Users</h5>
                                <h3>{{ \App\Models\User::where('status', 'active')->count() }}</h3>
                                <small>Currently Active</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Customers</h5>
                                <h3>{{ \App\Models\User::where('role', 'customer')->count() }}</h3>
                                <small>Customer Accounts</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Permissions</h5>
                                <h3>{{ \App\Models\Permission::count() }}</h3>
                                <small>System Permissions</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Users</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach(\App\Models\User::latest()->limit(5)->get() as $user)
                                            <tr>
                                                <td>{{ $user->name }}</td>
                                                <td><span class="badge bg-secondary">{{ $user->role }}</span></td>
                                                <td>
                                                    <span class="badge bg-{{ $user->status === 'active' ? 'success' : 'warning' }}">
                                                        {{ $user->status }}
                                                    </span>
                                                </td>
                                                <td>{{ $user->created_at->format('M d') }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>System Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <h6>Account Types</h6>
                                        <p>Prepaid: {{ \App\Models\User::where('account_type', 'prepaid')->count() }}</p>
                                        <p>Postpaid: {{ \App\Models\User::where('account_type', 'postpaid')->count() }}</p>
                                    </div>
                                    <div class="col-6">
                                        <h6>User Roles</h6>
                                        <p>Admins: {{ \App\Models\User::where('role', 'admin')->count() }}</p>
                                        <p>Operators: {{ \App\Models\User::where('role', 'operator')->count() }}</p>
                                        <p>Customers: {{ \App\Models\User::where('role', 'customer')->count() }}</p>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-grid gap-2">
                                    <a href="{{ route('admin.customers.index') }}" class="btn btn-primary btn-sm">Manage Customers</a>
                                    <a href="{{ route('admin.system.index') }}" class="btn btn-success btn-sm">System Settings</a>
                                    <a href="{{ route('admin.audit.index') }}" class="btn btn-info btn-sm">View Audit Logs</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>