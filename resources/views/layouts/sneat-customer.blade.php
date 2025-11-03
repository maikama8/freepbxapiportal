<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="{{ asset('sneat/') }}/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Customer Portal') - {{ config('app.name') }}</title>
    <meta name="description" content="FreePBX VoIP Platform Customer Portal" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('sneat/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    
    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('sneat/vendor/fonts/iconify-icons.css') }}" />
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('sneat/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('sneat/css/demo.css') }}" />
    
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('sneat/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" 
          onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/perfect-scrollbar/1.5.5/css/perfect-scrollbar.min.css'" />
    
    @stack('styles')
    
    <!-- Custom fixes for missing assets -->
    <link rel="stylesheet" href="{{ asset('css/fixes.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('sneat/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('sneat/js/config.js') }}"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="{{ route('customer.dashboard') }}" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <i class="bx bx-phone text-primary" style="font-size: 2rem;"></i>
                        </span>
                        <span class="app-brand-text demo menu-text fw-bold ms-2">VoIP Portal</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
                        <a href="{{ route('customer.dashboard') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Dashboard">Dashboard</div>
                        </a>
                    </li>

                    <!-- Calls Section -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Calls</span>
                    </li>

                    <!-- Make Call -->
                    <li class="menu-item {{ request()->routeIs('customer.calls.make') ? 'active' : '' }}">
                        <a href="{{ route('customer.calls.make') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-phone-call"></i>
                            <div data-i18n="MakeCall">Make Call</div>
                        </a>
                    </li>

                    <!-- Call History -->
                    <li class="menu-item {{ request()->routeIs('customer.call-history') ? 'active' : '' }}">
                        <a href="{{ route('customer.call-history') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-history"></i>
                            <div data-i18n="CallHistory">Call History</div>
                        </a>
                    </li>

                    <!-- Monitor Calls -->
                    <li class="menu-item {{ request()->routeIs('customer.calls.monitor') ? 'active' : '' }}">
                        <a href="{{ route('customer.calls.monitor') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-radio-circle-marked"></i>
                            <div data-i18n="MonitorCalls">Monitor Calls</div>
                        </a>
                    </li>

                    <!-- Billing Section -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Billing</span>
                    </li>

                    <!-- Add Funds -->
                    <li class="menu-item {{ request()->routeIs('customer.payments.add-funds') ? 'active' : '' }}">
                        <a href="{{ route('customer.payments.add-funds') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-credit-card"></i>
                            <div data-i18n="AddFunds">Add Funds</div>
                        </a>
                    </li>

                    <!-- Payment History -->
                    <li class="menu-item {{ request()->routeIs('customer.payments.history') ? 'active' : '' }}">
                        <a href="{{ route('customer.payments.history') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-receipt"></i>
                            <div data-i18n="PaymentHistory">Payment History</div>
                        </a>
                    </li>

                    <!-- Balance History -->
                    <li class="menu-item {{ request()->routeIs('customer.balance-history') ? 'active' : '' }}">
                        <a href="{{ route('customer.balance-history') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-wallet"></i>
                            <div data-i18n="BalanceHistory">Balance History</div>
                        </a>
                    </li>

                    <!-- Invoices -->
                    <li class="menu-item {{ request()->routeIs('customer.invoices') ? 'active' : '' }}">
                        <a href="{{ route('customer.invoices') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-file"></i>
                            <div data-i18n="Invoices">Invoices</div>
                        </a>
                    </li>

                    <!-- Real-time Billing -->
                    <li class="menu-item {{ request()->routeIs('customer.billing.realtime') ? 'active' : '' }}">
                        <a href="{{ route('customer.billing.realtime') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-trending-up"></i>
                            <div data-i18n="RealtimeBilling">Real-time Billing</div>
                        </a>
                    </li>

                    <!-- Account Section -->
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Account</span>
                    </li>

                    <!-- Account Settings -->
                    <li class="menu-item {{ request()->routeIs('customer.account-settings') ? 'active' : '' }}">
                        <a href="{{ route('customer.account-settings') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-cog"></i>
                            <div data-i18n="AccountSettings">Account Settings</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Balance Display -->
                        <div class="navbar-nav align-items-center me-3">
                            <div class="nav-item d-flex align-items-center">
                                <div class="badge bg-primary me-2">
                                    <i class="bx bx-wallet"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Balance</small>
                                    <div class="fw-bold" id="current-balance">${{ number_format(auth()->user()->balance, 2) }}</div>
                                </div>
                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- SIP Status -->
                            <li class="nav-item me-3">
                                <div class="d-flex align-items-center">
                                    <div class="badge bg-success me-2">
                                        <i class="bx bx-phone"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted">Extension</small>
                                        <div class="fw-bold">{{ auth()->user()->sip_username ?? 'Not Set' }}</div>
                                    </div>
                                </div>
                            </li>

                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <x-user-avatar :user="auth()->user()" :size="40" class="w-px-40 h-auto" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <x-user-avatar :user="auth()->user()" :size="40" class="w-px-40 h-auto" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-medium d-block">{{ auth()->user()->name }}</span>
                                                    <small class="text-muted">{{ ucfirst(auth()->user()->account_type) }} Customer</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('customer.account-settings') }}">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    @if(auth()->user()->isAdmin())
                                    <li>
                                        <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                            <i class="bx bx-cog me-2"></i>
                                            <span class="align-middle">Switch to Admin</span>
                                        </a>
                                    </li>
                                    @endif
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                <i class="bx bx-power-off me-2"></i>
                                                <span class="align-middle">Log Out</span>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                            <!--/ User -->
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible" role="alert">
                                {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible" role="alert">
                                {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @if(session('warning'))
                            <div class="alert alert-warning alert-dismissible" role="alert">
                                {{ session('warning') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @yield('content')
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                © {{ date('Y') }}, made with ❤️ by <strong>{{ config('app.name') }}</strong>
                            </div>
                            <div class="d-none d-lg-inline-block">
                                <a href="#" class="footer-link me-4">Support</a>
                                <a href="#" class="footer-link me-4">Help</a>
                                <a href="#" class="footer-link">Contact</a>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <script src="{{ asset('sneat/vendor/libs/jquery/jquery.js') }}" 
            onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js'"></script>
    <script src="{{ asset('sneat/vendor/libs/popper/popper.js') }}" 
            onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.8/umd/popper.min.js'"></script>
    <script src="{{ asset('sneat/vendor/js/bootstrap.js') }}" 
            onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js'"></script>
    <script src="{{ asset('sneat/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}" 
            onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/perfect-scrollbar/1.5.5/perfect-scrollbar.min.js'"></script>
    <script src="{{ asset('sneat/vendor/js/menu.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('sneat/js/main.js') }}"></script>

    @stack('scripts')

    <!-- Auto-logout functionality -->
    <script src="{{ asset('js/auto-logout.js') }}"></script>
    
    <!-- Asset fallbacks -->
    <script src="{{ asset('js/asset-fallbacks.js') }}"></script>

    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        // Add CSRF token to all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Auto-refresh balance every 30 seconds
        setInterval(function() {
            fetch('{{ route("customer.balance.refresh") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('current-balance').textContent = '$' + data.balance;
                    }
                })
                .catch(error => console.log('Balance refresh error:', error));
        }, 30000);
    </script>
</body>
</html>