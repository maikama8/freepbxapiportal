<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="{{ asset('sneat/') }}/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Register - {{ config('app.name') }}</title>
    <meta name="description" content="FreePBX VoIP Platform Registration" />

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
        .password-toggle {
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
        
        .password-toggle:hover {
            color: #696cff !important;
            background-color: rgba(105, 108, 255, 0.1);
        }
        
        .password-toggle i {
            font-size: 1.125rem;
            pointer-events: none;
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
                <!-- Register -->
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
                        
                        <h4 class="mb-2">Adventure starts here 🚀</h4>
                        <p class="mb-4">Make your VoIP communication easy and fun!</p>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if (session('info'))
                            <div class="alert alert-info">
                                {{ session('info') }}
                            </div>
                        @endif

                        <form id="formAuthentication" class="mb-3" action="{{ route('register') }}" method="POST" autocomplete="on">
                            @csrf
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" 
                                       class="form-control @error('name') is-invalid @enderror" 
                                       id="name" 
                                       name="name" 
                                       placeholder="Enter your full name" 
                                       value="{{ old('name') }}" 
                                       autocomplete="name"
                                       autofocus />
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" 
                                       class="form-control @error('email') is-invalid @enderror" 
                                       id="email" 
                                       name="email" 
                                       placeholder="Enter your email" 
                                       value="{{ old('email') }}" 
                                       autocomplete="email" />
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" 
                                       class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="Enter your phone number (e.g., +1234567890)" 
                                       value="{{ old('phone') }}" 
                                       autocomplete="tel" />
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="account_type" class="form-label">Account Type</label>
                                <select class="form-select @error('account_type') is-invalid @enderror" 
                                        id="account_type" 
                                        name="account_type">
                                    <option value="">Select Account Type</option>
                                    <option value="prepaid" {{ old('account_type') === 'prepaid' ? 'selected' : '' }}>
                                        Prepaid - Pay as you go
                                    </option>
                                    <option value="postpaid" {{ old('account_type') === 'postpaid' ? 'selected' : '' }}>
                                        Postpaid - Monthly billing
                                    </option>
                                </select>
                                @error('account_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 form-password-toggle">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" 
                                           id="password" 
                                           class="form-control @error('password') is-invalid @enderror" 
                                           name="password" 
                                           placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
                                           autocomplete="new-password"
                                           aria-describedby="password" />
                                    <span class="input-group-text cursor-pointer password-toggle" data-target="password">
                                        <i class="bx bx-hide"></i>
                                    </span>
                                </div>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">
                                    Password must contain at least 8 characters with uppercase, lowercase, number, and special character.
                                </small>
                            </div>

                            <div class="mb-3 form-password-toggle">
                                <label class="form-label" for="password_confirmation">Confirm Password</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" 
                                           id="password_confirmation" 
                                           class="form-control @error('password_confirmation') is-invalid @enderror" 
                                           name="password_confirmation" 
                                           placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
                                           autocomplete="new-password"
                                           aria-describedby="password_confirmation" />
                                    <span class="input-group-text cursor-pointer password-toggle" data-target="password_confirmation">
                                        <i class="bx bx-hide"></i>
                                    </span>
                                </div>
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input @error('terms') is-invalid @enderror" 
                                           type="checkbox" 
                                           id="terms" 
                                           name="terms" 
                                           value="1" 
                                           {{ old('terms') ? 'checked' : '' }} />
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
                                    </label>
                                </div>
                                @error('terms')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button class="btn btn-primary d-grid w-100" type="submit">Sign up</button>
                        </form>

                        <p class="text-center">
                            <span>Already have an account?</span>
                            <a href="{{ route('login') }}">
                                <span>Sign in instead</span>
                            </a>
                        </p>
                    </div>
                </div>
                <!-- /Register -->
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
        // Password toggle functionality
        $(document).ready(function() {
            $('.password-toggle').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const target = $(this).data('target');
                const input = $('#' + target);
                const icon = $(this).find('i');
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('bx-hide').addClass('bx-show');
                    $(this).attr('title', 'Hide password');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('bx-show').addClass('bx-hide');
                    $(this).attr('title', 'Show password');
                }
            });
            
            // Set initial titles
            $('.password-toggle').attr('title', 'Show password');
        });

        // Fallback for vanilla JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const toggles = document.querySelectorAll('.password-toggle');
                
                toggles.forEach(function(toggle) {
                    if (!toggle.hasAttribute('data-initialized')) {
                        toggle.setAttribute('data-initialized', 'true');
                        
                        toggle.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const target = this.getAttribute('data-target');
                            const input = document.getElementById(target);
                            const icon = this.querySelector('i');
                            
                            if (input.type === 'password') {
                                input.type = 'text';
                                icon.className = 'bx bx-show';
                                this.setAttribute('title', 'Hide password');
                            } else {
                                input.type = 'password';
                                icon.className = 'bx bx-hide';
                                this.setAttribute('title', 'Show password');
                            }
                        });
                        
                        toggle.setAttribute('title', 'Show password');
                    }
                });
            }, 100);
        });
    </script>
</body>
</html>