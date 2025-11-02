<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name') }}</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .balance-indicator {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">{{ config('app.name') }}</h4>
                        <div class="text-white-50 small">Customer Portal</div>
                    </div>
                    
                    <!-- User Info -->
                    <div class="card bg-transparent border-light mb-3">
                        <div class="card-body text-white text-center">
                            <div class="mb-2">
                                <i class="fas fa-user-circle fa-3x"></i>
                            </div>
                            <h6>{{ auth()->user()->name }}</h6>
                            <div class="balance-indicator bg-success text-white">
                                Balance: ${{ number_format(auth()->user()->balance, 2) }}
                            </div>
                            <small class="text-white-50">{{ ucfirst(auth()->user()->account_type) }} Account</small>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}" 
                               href="{{ route('customer.dashboard') }}">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.calls.*') ? 'active' : '' }}" 
                               href="{{ route('customer.calls.make') }}">
                                <i class="fas fa-phone me-2"></i>
                                Make Call
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.call-history') ? 'active' : '' }}" 
                               href="{{ route('customer.call-history') }}">
                                <i class="fas fa-history me-2"></i>
                                Call History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.payments.*') ? 'active' : '' }}" 
                               href="{{ route('customer.payments.add-funds') }}">
                                <i class="fas fa-credit-card me-2"></i>
                                Add Funds
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.balance-history') ? 'active' : '' }}" 
                               href="{{ route('customer.balance-history') }}">
                                <i class="fas fa-receipt me-2"></i>
                                Balance History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.invoices.*') ? 'active' : '' }}" 
                               href="{{ route('customer.invoices') }}">
                                <i class="fas fa-file-invoice me-2"></i>
                                Invoices
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('customer.account-settings') ? 'active' : '' }}" 
                               href="{{ route('customer.account-settings') }}">
                                <i class="fas fa-cog me-2"></i>
                                Settings
                            </a>
                        </li>
                    </ul>

                    <hr class="text-white-50">

                    <!-- Support & Logout -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#supportModal">
                                <i class="fas fa-question-circle me-2"></i>
                                Support
                            </a>
                        </li>
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="nav-link border-0 bg-transparent text-start w-100">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Top Navigation -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">@yield('title')</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshBalance">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> {{ auth()->user()->name }}
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('customer.account-settings') }}">
                                    <i class="fas fa-cog"></i> Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-sign-out-alt"></i> Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Page Content -->
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Support Modal -->
    <div class="modal fade" id="supportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Contact Support</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Need help? Contact our support team:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope"></i> Email: support@{{ config('app.domain', 'example.com') }}</li>
                        <li><i class="fas fa-phone"></i> Phone: +1 (555) 123-4567</li>
                        <li><i class="fas fa-clock"></i> Hours: 24/7 Support Available</li>
                    </ul>
                    <hr>
                    <p><strong>Account Information:</strong></p>
                    <ul class="list-unstyled small">
                        <li>Account ID: {{ auth()->user()->id }}</li>
                        <li>Email: {{ auth()->user()->email }}</li>
                        <li>Account Type: {{ ucfirst(auth()->user()->account_type) }}</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Scripts -->
    <script>
        // Refresh balance functionality
        document.getElementById('refreshBalance').addEventListener('click', function() {
            const button = this;
            const icon = button.querySelector('i');
            
            // Add spinning animation
            icon.classList.add('fa-spin');
            button.disabled = true;
            
            fetch('{{ route("customer.balance.refresh") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update balance displays
                        document.querySelectorAll('.balance-indicator').forEach(el => {
                            el.textContent = `Balance: $${data.balance}`;
                        });
                        
                        // Show success message
                        showToast('Balance refreshed successfully', 'success');
                    } else {
                        showToast('Failed to refresh balance', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while refreshing balance', 'error');
                })
                .finally(() => {
                    // Remove spinning animation
                    icon.classList.remove('fa-spin');
                    button.disabled = false;
                });
        });

        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast element after it's hidden
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '1055';
            document.body.appendChild(container);
            return container;
        }
    </script>

    @stack('scripts')
</body>
</html>