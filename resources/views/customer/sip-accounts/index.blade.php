@extends('layouts.sneat-customer')

@section('title', 'My SIP Accounts')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bx bx-phone me-2"></i>My SIP Accounts
                </h4>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-info" onclick="showSipServerInfo()">
                        <i class="bx bx-info-circle"></i> Server Info
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($sipAccounts->count() > 0)
                    <div class="row">
                        @foreach($sipAccounts as $sipAccount)
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 {{ $sipAccount->is_primary ? 'border-primary' : '' }}">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="card-title mb-0">
                                            {{ $sipAccount->display_name }}
                                            @if($sipAccount->is_primary)
                                                <span class="badge bg-primary ms-2">Primary</span>
                                            @endif
                                        </h6>
                                        <span class="badge bg-{{ $sipAccount->status === 'active' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($sipAccount->status) }}
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label small text-muted">SIP Username</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" value="{{ $sipAccount->sip_username }}" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('{{ $sipAccount->sip_username }}')">
                                                    <i class="bx bx-copy"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small text-muted">SIP Server</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" value="{{ $sipAccount->sip_server }}" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('{{ $sipAccount->sip_server }}')">
                                                    <i class="bx bx-copy"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small text-muted">SIP Port</label>
                                            <input type="text" class="form-control" value="{{ $sipAccount->sip_port }}" readonly>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small text-muted">Full SIP URI</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" value="{{ $sipAccount->sip_uri }}" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('{{ $sipAccount->sip_uri }}')">
                                                    <i class="bx bx-copy"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted">Voicemail:</small>
                                                <span class="badge bg-{{ $sipAccount->voicemail_enabled ? 'success' : 'secondary' }}">
                                                    {{ $sipAccount->voicemail_enabled ? 'Enabled' : 'Disabled' }}
                                                </span>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Call Forward:</small>
                                                <span class="badge bg-{{ $sipAccount->call_forward_enabled ? 'success' : 'secondary' }}">
                                                    {{ $sipAccount->call_forward_enabled ? 'Enabled' : 'Disabled' }}
                                                </span>
                                            </div>
                                        </div>

                                        @if($sipAccount->last_registered_at)
                                            <div class="mb-3">
                                                <small class="text-muted">Last Registered:</small>
                                                <div>{{ $sipAccount->last_registered_at->format('M j, Y g:i A') }}</div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-group w-100">
                                            <a href="{{ route('customer.sip-accounts.show', $sipAccount) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="bx bx-show"></i> View
                                            </a>
                                            <a href="{{ route('customer.sip-accounts.edit-password', $sipAccount) }}" class="btn btn-outline-warning btn-sm">
                                                <i class="bx bx-key"></i> Change Password
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bx bx-phone display-1 text-muted"></i>
                        <h5 class="mt-3">No SIP Accounts</h5>
                        <p class="text-muted">You don't have any SIP accounts yet. Contact your administrator to get one set up.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- SIP Server Info Modal -->
<div class="modal fade" id="sipServerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">SIP Server Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">SIP Server</label>
                    <input type="text" class="form-control" value="{{ config('voip.freepbx.sip.domain') }}" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">SIP Port</label>
                    <input type="text" class="form-control" value="{{ config('voip.freepbx.sip.port') }}" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Transport</label>
                    <input type="text" class="form-control" value="{{ strtoupper(config('voip.freepbx.sip.transport')) }}" readonly>
                </div>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    Use these settings to configure your SIP client or softphone.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    Copied to clipboard!
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
    });
}

function showSipServerInfo() {
    const modal = new bootstrap.Modal(document.getElementById('sipServerModal'));
    modal.show();
}
</script>
@endpush