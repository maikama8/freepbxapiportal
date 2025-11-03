@extends('layouts.sneat-customer')

@section('title', 'Account Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bx bx-user-circle"></i> Account Settings</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('customer.account-settings.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="{{ $user->email }}" disabled>
                                    <div class="form-text">Email cannot be changed. Contact support if needed.</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control @error('phone') is-invalid @enderror" 
                                           id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select @error('timezone') is-invalid @enderror" 
                                            id="timezone" name="timezone" required>
                                        <option value="">Select Timezone</option>
                                        @foreach(timezone_identifiers_list() as $timezone)
                                            <option value="{{ $timezone }}" 
                                                    {{ old('timezone', $user->timezone) === $timezone ? 'selected' : '' }}>
                                                {{ $timezone }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('timezone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Preferred Currency</label>
                                    <select class="form-select @error('currency') is-invalid @enderror" 
                                            id="currency" name="currency" required>
                                        <option value="">Select Currency</option>
                                        <option value="USD" {{ old('currency', $user->currency) === 'USD' ? 'selected' : '' }}>USD - US Dollar</option>
                                        <option value="EUR" {{ old('currency', $user->currency) === 'EUR' ? 'selected' : '' }}>EUR - Euro</option>
                                        <option value="GBP" {{ old('currency', $user->currency) === 'GBP' ? 'selected' : '' }}>GBP - British Pound</option>
                                        <option value="CAD" {{ old('currency', $user->currency) === 'CAD' ? 'selected' : '' }}>CAD - Canadian Dollar</option>
                                        <option value="AUD" {{ old('currency', $user->currency) === 'AUD' ? 'selected' : '' }}>AUD - Australian Dollar</option>
                                    </select>
                                    @error('currency')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Update Settings
                            </button>
                            <a href="{{ route('customer.dashboard') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Account Information -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bx bx-info-circle"></i> Account Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Account Type:</strong></td>
                            <td>
                                <span class="badge bg-{{ $user->account_type === 'prepaid' ? 'info' : 'warning' }}">
                                    {{ ucfirst($user->account_type) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge bg-{{ $user->status === 'active' ? 'success' : 'danger' }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Balance:</strong></td>
                            <td>${{ number_format($user->balance, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Credit Limit:</strong></td>
                            <td>${{ number_format($user->credit_limit, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>SIP Extension:</strong></td>
                            <td>{{ $user->extension ?? 'Not assigned' }}</td>
                        </tr>
                        <tr>
                            <td><strong>SIP Username:</strong></td>
                            <td>{{ $user->sip_username ?? 'Not assigned' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Member Since:</strong></td>
                            <td>{{ $user->created_at->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <td><strong>Last Login:</strong></td>
                            <td>{{ $user->last_login_at ? $user->last_login_at->format('M d, Y H:i') : 'Never' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- SIP Configuration Details -->
            @if($user->sipAccounts->where('is_primary', true)->first())
                @php $primarySip = $user->sipAccounts->where('is_primary', true)->first(); @endphp
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="bx bx-phone-call"></i> SIP Configuration</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleSipPassword()">
                            <i class="bx bx-show" id="sipPasswordIcon"></i> Show Password
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Extension Number:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="{{ $primarySip->sip_username }}" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $primarySip->sip_username }}')">
                                        <i class="bx bx-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">SIP Password:</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="sipPassword" value="{{ $primarySip->sip_password }}" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $primarySip->sip_password }}')">
                                        <i class="bx bx-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">SIP Server:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="{{ $primarySip->sip_server }}" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $primarySip->sip_server }}')">
                                        <i class="bx bx-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">SIP Port:</label>
                                <input type="text" class="form-control" value="{{ $primarySip->sip_port }}" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Context:</label>
                                <input type="text" class="form-control" value="{{ $primarySip->sip_context }}" readonly>
                            </div>
                            @if($primarySip->freepbx_settings && isset($primarySip->freepbx_settings['voicemail_password']))
                            <div class="col-12">
                                <label class="form-label fw-semibold">Voicemail PIN:</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="vmPassword" value="{{ $primarySip->freepbx_settings['voicemail_password'] }}" readonly>
                                    <button class="btn btn-outline-secondary" onclick="toggleVmPassword()">
                                        <i class="bx bx-show" id="vmPasswordIcon"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $primarySip->freepbx_settings['voicemail_password'] }}')">
                                        <i class="bx bx-copy"></i>
                                    </button>
                                </div>
                            </div>
                            @endif
                        </div>
                        
                        <div class="mt-3">
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle"></i>
                                <strong>SIP URI:</strong> {{ $primarySip->sip_uri }}
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bx bx-time"></i> 
                                Last registered: {{ $primarySip->last_registered_at ? $primarySip->last_registered_at->diffForHumans() : 'Never' }}
                            </small>
                        </div>
                    </div>
                </div>
            @endif

            <!-- SIP Accounts Section -->
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="bx bx-phone"></i> All SIP Accounts</h5>
                    <a href="{{ route('customer.sip-accounts.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bx bx-cog"></i> Manage
                    </a>
                </div>
                <div class="card-body">
                    @if($user->sipAccounts->count() > 0)
                        @foreach($user->sipAccounts->take(3) as $sipAccount)
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                <div>
                                    <div class="fw-semibold">{{ $sipAccount->sip_username }}</div>
                                    <small class="text-muted">{{ $sipAccount->display_name }}</small>
                                    @if($sipAccount->is_primary)
                                        <span class="badge bg-primary ms-1">Primary</span>
                                    @endif
                                </div>
                                <span class="badge bg-{{ $sipAccount->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($sipAccount->status) }}
                                </span>
                            </div>
                        @endforeach
                        
                        @if($user->sipAccounts->count() > 3)
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    +{{ $user->sipAccounts->count() - 3 }} more accounts
                                </small>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-3">
                            <i class="bx bx-phone text-muted display-6"></i>
                            <div class="text-muted">No SIP accounts</div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Security Settings -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="bx bx-shield"></i> Security</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="bx bx-key"></i> Change Password
                        </button>
                        <button type="button" class="btn btn-outline-info">
                            <i class="bx bx-mobile"></i> Two-Factor Auth
                        </button>
                    </div>
                    
                    <hr>
                    
                    <div class="small">
                        <p><strong>Failed Login Attempts:</strong> {{ $user->failed_login_attempts ?? 0 }}</p>
                        @if($user->locked_until)
                            <p class="text-danger">
                                <strong>Account Locked Until:</strong> 
                                {{ $user->locked_until->format('M d, Y H:i') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('customer.password.update') }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" 
                               name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" 
                               name="new_password" required>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                    </div>
                    <div class="mb-3">
                        <label for="new_password_confirmation" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="new_password_confirmation" 
                               name="new_password_confirmation" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleSipPassword() {
    const passwordField = document.getElementById('sipPassword');
    const icon = document.getElementById('sipPasswordIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.className = 'bx bx-hide';
    } else {
        passwordField.type = 'password';
        icon.className = 'bx bx-show';
    }
}

function toggleVmPassword() {
    const passwordField = document.getElementById('vmPassword');
    const icon = document.getElementById('vmPasswordIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.className = 'bx bx-hide';
    } else {
        passwordField.type = 'password';
        icon.className = 'bx bx-show';
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bx bx-check"></i> Copied to clipboard!
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
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
        alert('Failed to copy to clipboard');
    });
}
</script>
@endpush