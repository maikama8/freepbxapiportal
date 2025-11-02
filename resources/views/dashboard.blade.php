<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">{{ config('app.name') }}</a>
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

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Customer Dashboard</h2>
                
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Account Balance</h5>
                                <h3>${{ number_format(auth()->user()->balance, 2) }}</h3>
                                <small>{{ ucfirst(auth()->user()->account_type) }} Account</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Account Status</h5>
                                <h4>{{ ucfirst(auth()->user()->status) }}</h4>
                                <small>SIP: {{ auth()->user()->sip_username }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Credit Limit</h5>
                                <h3>${{ number_format(auth()->user()->credit_limit, 2) }}</h3>
                                <small>Available Credit</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card text-white bg-secondary">
                            <div class="card-body">
                                <h5 class="card-title">Currency</h5>
                                <h4>{{ auth()->user()->currency }}</h4>
                                <small>{{ auth()->user()->timezone }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" type="button">Make a Call</button>
                                    <button class="btn btn-success" type="button">Add Funds</button>
                                    <button class="btn btn-info" type="button">View Call History</button>
                                    <button class="btn btn-secondary" type="button">Account Settings</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Account Information</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Name:</strong> {{ auth()->user()->name }}</p>
                                <p><strong>Email:</strong> {{ auth()->user()->email }}</p>
                                <p><strong>Phone:</strong> {{ auth()->user()->phone ?? 'Not set' }}</p>
                                <p><strong>Member Since:</strong> {{ auth()->user()->created_at->format('M d, Y') }}</p>
                                <p><strong>Last Login:</strong> {{ auth()->user()->last_login_at ? auth()->user()->last_login_at->format('M d, Y H:i') : 'Never' }}</p>
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