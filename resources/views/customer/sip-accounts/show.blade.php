@extends('layouts.sneat-customer')

@section('title', 'SIP Account Details')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>SIP Account Details</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('customer.sip-accounts.index') }}">SIP Accounts</a></li>
                            <li class="breadcrumb-item active">{{ $sipAccount->sip_username }}</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('customer.sip-accounts.index') }}" class="btn btn-secondary">
                        <i class="bx bx-arrow-back"></i> Back to SIP Accounts
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- SIP Configuration -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bx bx-phone-call"></i> SIP Configuration</h5>
                            <div>
                                <span class="badge bg-{{ $sipAccount->status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($sipAccount->status) }}
                                </span>
                                @if($sipAccount->is_primary)
                                    <span class="badge bg-primary ms-1">Primary</span>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Extension Number:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="{{ $sipAccount->sip_username }}" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $sipAccount->sip_username }}')">
                                            <i class="bx bx-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Display Name:</label>
                                    <input type="text" class="form-control" value="{{ $sipAccount->display_name }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SIP Password:</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="sipPassword" value="{{ $sipAccount->sip_password }}" readonly>
                                        <button class="btn btn-outline-secondary" onclick="togglePassword('sipPassword', 'sipPasswordIcon')">
                                            <i class="bx bx-show" id="sipPasswordIcon"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $sipAccount->sip_password }}')">
                                            <i class="bx bx-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SIP Context:</label>
                                    <input type="text" class="form-control" value="{{ $sipAccount->sip_context }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SIP Server:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="{{ $sipAccount->sip_server }}" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $sipAccount->sip_server }}')">
                                            <i class="bx bx-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SIP Port:</label>
                                    <input type="text" class="form-control" value="{{ $sipAccount->sip_port }}" readonly>
                                </div>
                                @if($sipAccount->freepbx_settings && isset($sipAccount->freepbx_settings['voicemail_password']))
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Voicemail PIN:</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="vmPassword" value="{{ $sipAccount->freepbx_settings['voicemail_password'] }}" readonly>
                                        <button class="btn btn-outline-secondary" onclick="togglePassword('vmPassword', 'vmPasswordIcon')">
                                            <i class="bx bx-show" id="vmPasswordIcon"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $sipAccount->freepbx_settings['voicemail_password'] }}')">
                                            <i class="bx bx-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                @endif
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Complete SIP URI:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="{{ $sipAccount->sip_uri }}" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('{{ $sipAccount->sip_uri }}')">
                                            <i class="bx bx-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle"></i>
                                    <strong>Quick Setup:</strong> Use these credentials to configure your SIP client or softphone.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Voicemail Settings -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5><i class="bx bx-voicemail"></i> Voicemail Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="voicemailEnabled" 
                                               {{ $sipAccount->voicemail_enabled ? 'checked' : '' }} disabled>
                                        <label class="form-check-label" for="voicemailEnabled">
                                            Voicemail Enabled
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Voicemail Email:</label>
                                    <input type="email" class="form-control" value="{{ $sipAccount->voicemail_email }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Call Forwarding -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5><i class="bx bx-transfer"></i> Call Forwarding</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="callForwardEnabled" 
                                               {{ $sipAccount->call_forward_enabled ? 'checked' : '' }} disabled>
                                        <label class="form-check-label" for="callForwardEnabled">
                                            Call Forwarding Enabled
                                        </label>
                                    </div>
                                </div>
                                @if($sipAccount->call_forward_enabled && $sipAccount->call_forward_number)
                                <div class="col-md-6">
                                    <label class="form-label">Forward to Number:</label>
                                    <input type="text" class="form-control" value="{{ $sipAccount->call_forward_number }}" readonly>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bx bx-info-circle"></i> Account Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td>{{ $sipAccount->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Last Updated:</strong></td>
                                    <td>{{ $sipAccount->updated_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Last Registered:</strong></td>
                                    <td>{{ $sipAccount->last_registered_at ? $sipAccount->last_registered_at->format('M d, Y H:i') : 'Never' }}</td>
                                </tr>
                                @if($sipAccount->last_registered_ip)
                                <tr>
                                    <td><strong>Last IP:</strong></td>
                                    <td>{{ $sipAccount->last_registered_ip }}</td>
                                </tr>
                                @endif
                                @if($sipAccount->freepbx_extension_id)
                                <tr>
                                    <td><strong>FreePBX ID:</strong></td>
                                    <td>{{ $sipAccount->freepbx_extension_id }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="bx bx-cog"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('customer.sip-accounts.edit-password', $sipAccount) }}" class="btn btn-outline-warning">
                                    <i class="bx bx-key"></i> Change SIP Password
                                </a>
                                <button type="button" class="btn btn-outline-info" onclick="downloadConfig()">
                                    <i class="bx bx-download"></i> Download Config
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="testConnection()">
                                    <i class="bx bx-test-tube"></i> Test Connection
                                </button>
                            </div>
                        </div>
                    </div>

                    @if($sipAccount->notes)
                    <!-- Notes -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="bx bx-note"></i> Notes</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">{{ $sipAccount->notes }}</p>
                        </div>
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
function togglePassword(fieldId, iconId) {
    const passwordField = document.getElementById(fieldId);
    const icon = document.getElementById(iconId);
    
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

function downloadConfig() {
    const config = `[{{ $sipAccount->sip_username }}]
type=friend
host=dynamic
username={{ $sipAccount->sip_username }}
secret={{ $sipAccount->sip_password }}
context={{ $sipAccount->sip_context }}
qualify=yes
nat=yes
canreinvite=no
dtmfmode=rfc2833
`;

    const blob = new Blob([config], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sip_{{ $sipAccount->sip_username }}.conf';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function testConnection() {
    alert('SIP connection test feature coming soon!');
}
</script>
@endpush