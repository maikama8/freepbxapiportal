<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="{{ asset('sneat/') }}/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Login - {{ config('app.name') }}</title>
    <meta name="description" content="FreePBX VoIP Platform Login" />

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
    <link rel="stylesheet" href="{{ asset('sneat/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{ asset('sneat/vendor/css/pages/page-auth.css') }}" />
    
    <!-- Custom CSS for password toggle -->
    <style>
        #password-toggle {
            cursor: pointer !important;
            user-select: none;
            transition: color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 100%;
            border: none;
            background: transparent;
        }
        
        #password-toggle:hover {
            color: #696cff !important;
            background-color: rgba(105, 108, 255, 0.1);
        }
        
        #password-toggle:active {
            transform: scale(0.95);
        }
        
        #password-toggle i {
            font-size: 1.125rem;
            pointer-events: none;
        }
        
        .form-password-toggle .input-group-text {
            cursor: pointer !important;
        }
        
        /* Ensure the toggle is always visible and clickable */
        .input-group-merge .input-group-text {
            z-index: 10;
            position: relative;
        }
    </style>

    <!-- Helpers -->
    <script src="{{ asset('sneat/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('sneat/js/config.js') }}"></script>
</head>

<body>
    <!-- Content -->
    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <!-- Login -->
                <div class="card">
                    <div class="card-body">
                        <!-- Logo -->
                        <div class="app-brand justify-content-center">
                            <a href="{{ url('/') }}" class="app-brand-link gap-2">
                                <span class="app-brand-logo demo">
                                    <i class="bx bx-phone text-primary" style="font-size: 2rem;"></i>
                                </span>
                                <span class="app-brand-text demo text-body fw-bold">{{ config('app.name') }}</span>
                            </a>
                        </div>
                        <!-- /Logo -->
                        
                        <h4 class="mb-2">Welcome to VoIP Platform! 👋</h4>
                        <p class="mb-4">Please sign-in to your account and start the adventure</p>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="alert alert-success">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form id="formAuthentication" class="mb-3" action="{{ url('/login') }}" method="POST" autocomplete="on">
                            @csrf
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       id="email" 
                                       name="email" 
                                       placeholder="Enter your email" 
                                       value="{{ old('email') }}" 
                                       autocomplete="username"
                                       autofocus />
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3 form-password-toggle">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label" for="password">Password</label>
                                    <a href="#">
                                        <small>Forgot Password?</small>
                                    </a>
                                </div>
                                <div class="input-group input-group-merge">
                                    <input type="password" 
                                           id="password" 
                                           class="form-control @error('password') is-invalid @enderror" 
                                           name="password" 
                                           placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
                                           autocomplete="current-password"
                                           aria-describedby="password" />
                                    <span class="input-group-text cursor-pointer" id="password-toggle" onclick="togglePassword()"><i class="bx bx-hide"></i></span>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember-me" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} />
                                    <label class="form-check-label" for="remember-me"> Remember Me </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-primary d-grid w-100" type="submit">Sign in</button>
                            </div>
                        </form>

                        <p class="text-center">
                            <span>New on our platform?</span>
                            <a href="{{ route('register') }}">
                                <span>Create an account</span>
                            </a>
                        </p>

                        <!-- Demo Credentials -->
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <h6 class="alert-heading mb-2">Demo Credentials:</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Admin:</strong><br>
                                        <small>admin@voipplatform.com</small><br>
                                        <small>admin123</small>
                                    </div>
                                    <div class="col-6">
                                        <strong>Customer:</strong><br>
                                        <small>john@example.com</small><br>
                                        <small>customer123</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Login -->
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('sneat/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('sneat/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('sneat/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('sneat/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('sneat/vendor/js/menu.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('sneat/js/main.js') }}"></script>

    <!-- Page JS -->
    <script>
        // Wait for jQuery and all scripts to load
        $(document).ready(function() {
            // Password toggle functionality - using jQuery for better compatibility
            const passwordToggle = $('#password-toggle');
            const passwordInput = $('#password');
            
            if (passwordToggle.length && passwordInput.length) {
                // Remove any existing event handlers
                passwordToggle.off('click');
                
                // Add our click handler
                passwordToggle.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const icon = $(this).find('i');
                    const input = passwordInput[0];
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.removeClass('bx-hide').addClass('bx-show');
                        $(this).attr('title', 'Hide password');
                    } else {
                        input.type = 'password';
                        icon.removeClass('bx-show').addClass('bx-hide');
                        $(this).attr('title', 'Show password');
                    }
                });
                
                // Set initial title
                passwordToggle.attr('title', 'Show password');
            }
            
            // Handle form submission for browser credential saving
            const loginForm = $('#formAuthentication');
            if (loginForm.length) {
                loginForm.on('submit', function(e) {
                    // Allow browser to save credentials if remember me is checked
                    const rememberCheckbox = $('#remember-me');
                    if (rememberCheckbox.length && rememberCheckbox.is(':checked')) {
                        // Browser will automatically prompt to save credentials
                        console.log('Remember me is checked - browser will prompt to save credentials');
                    }
                });
            }
        });
        
        // Fallback for vanilla JavaScript if jQuery fails
        document.addEventListener('DOMContentLoaded', function() {
            // Only run if jQuery version didn't work
            setTimeout(function() {
                const passwordToggle = document.getElementById('password-toggle');
                const passwordInput = document.getElementById('password');
                
                if (passwordToggle && passwordInput && !passwordToggle.hasAttribute('data-initialized')) {
                    passwordToggle.setAttribute('data-initialized', 'true');
                    
                    passwordToggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const icon = this.querySelector('i');
                        
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            icon.className = 'bx bx-show';
                            this.setAttribute('title', 'Hide password');
                        } else {
                            passwordInput.type = 'password';
                            icon.className = 'bx bx-hide';
                            this.setAttribute('title', 'Show password');
                        }
                    });
                    
                    passwordToggle.setAttribute('title', 'Show password');
                }
            }, 100);
        });
        
        // Simple inline function as backup
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('#password-toggle i');
            
            if (passwordInput && icon) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.className = 'bx bx-show';
                } else {
                    passwordInput.type = 'password';
                    icon.className = 'bx bx-hide';
                }
            }
        }
    </script>
</body>
</html>