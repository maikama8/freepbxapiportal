@extends('layouts.sneat-admin')

@section('title', 'System Settings')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bx bx-cog me-2"></i>System Settings
                </h4>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" onclick="testFreepbxConnection()">
                        <i class="bx bx-plug"></i> Test FreePBX Connection
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="checkSipServerStatus()">
                        <i class="bx bx-server"></i> Check SIP Server
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

                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="accordion" id="settingsAccordion">
                            @foreach($settings as $group => $groupSettings)
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading{{ ucfirst($group) }}">
                                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#collapse{{ ucfirst($group) }}" 
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}" 
                                                aria-controls="collapse{{ ucfirst($group) }}">
                                            <i class="bx bx-{{ $group === 'sip' ? 'phone' : ($group === 'freepbx' ? 'server' : ($group === 'company' ? 'building' : 'cog')) }} me-2"></i>
                                            {{ ucfirst(str_replace('_', ' ', $group)) }} Settings
                                            <span class="badge bg-primary ms-2">{{ count($groupSettings) }}</span>
                                        </button>
                                    </h2>
                                    <div id="collapse{{ ucfirst($group) }}" 
                                         class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" 
                                         aria-labelledby="heading{{ ucfirst($group) }}" 
                                         data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="row">
                                                @foreach($groupSettings as $setting)
                                                    <div class="col-md-6 mb-3">
                                                        <label for="setting_{{ $setting->key }}" class="form-label">
                                                            {{ $setting->label }}
                                                            @if($setting->description)
                                                                <i class="bx bx-info-circle text-muted ms-1" 
                                                                   data-bs-toggle="tooltip" 
                                                                   title="{{ $setting->description }}"></i>
                                                            @endif
                                                        </label>
                                                        
                                                        @if($setting->type === 'boolean')
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" 
                                                                       id="setting_{{ $setting->key }}" 
                                                                       name="settings[{{ $setting->key }}]" 
                                                                       value="1" 
                                                                       {{ $setting->value ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="setting_{{ $setting->key }}">
                                                                    {{ $setting->label }}
                                                                </label>
                                                            </div>
                                                        @elseif($setting->key === 'freepbx_api_password' || $setting->key === 'sip_password')
                                                            <div class="input-group">
                                                                <input type="password" 
                                                                       class="form-control" 
                                                                       id="setting_{{ $setting->key }}" 
                                                                       name="settings[{{ $setting->key }}]" 
                                                                       value="{{ $setting->value }}"
                                                                       placeholder="Enter {{ strtolower($setting->label) }}">
                                                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('setting_{{ $setting->key }}')">
                                                                    <i class="bx bx-show"></i>
                                                                </button>
                                                            </div>
                                                        @elseif($setting->type === 'json')
                                                            <textarea class="form-control" 
                                                                      id="setting_{{ $setting->key }}" 
                                                                      name="settings[{{ $setting->key }}]" 
                                                                      rows="3"
                                                                      placeholder="Enter JSON data">{{ is_array($setting->value) ? json_encode($setting->value, JSON_PRETTY_PRINT) : $setting->value }}</textarea>
                                                        @else
                                                            <input type="{{ $setting->type === 'integer' || $setting->type === 'float' ? 'number' : 'text' }}" 
                                                                   class="form-control" 
                                                                   id="setting_{{ $setting->key }}" 
                                                                   name="settings[{{ $setting->key }}]" 
                                                                   value="{{ $setting->value }}"
                                                                   {{ $setting->type === 'float' ? 'step=0.01' : '' }}
                                                                   placeholder="Enter {{ strtolower($setting->label) }}">
                                                        @endif
                                                        
                                                        @if($setting->description)
                                                            <div class="form-text">{{ $setting->description }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                            
                                            <div class="mt-3 pt-3 border-top">
                                                <button type="button" class="btn btn-outline-warning btn-sm" 
                                                        onclick="resetGroupToDefaults('{{ $group }}')">
                                                    <i class="bx bx-reset"></i> Reset {{ ucfirst($group) }} to Defaults
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Save All Settings
                                </button>
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary ms-2">
                                    <i class="bx bx-arrow-back"></i> Back to Dashboard
                                </a>
                            </div>
                            <div>
                                <div id="connectionStatus" class="d-none">
                                    <span class="badge bg-success" id="statusOnline" style="display: none;">
                                        <i class="bx bx-check-circle"></i> Connected
                                    </span>
                                    <span class="badge bg-danger" id="statusOffline" style="display: none;">
                                        <i class="bx bx-x-circle"></i> Disconnected
                                    </span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset <strong id="resetGroupName"></strong> settings to their default values?</p>
                <p class="text-warning"><i class="bx bx-error"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="resetForm" method="POST" action="{{ route('admin.settings.reset') }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="group" id="resetGroupInput">
                    <button type="submit" class="btn btn-warning">Reset to Defaults</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bx-show');
        icon.classList.add('bx-hide');
    } else {
        field.type = 'password';
        icon.classList.remove('bx-hide');
        icon.classList.add('bx-show');
    }
}

// Test FreePBX connection
function testFreepbxConnection() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Testing...';
    btn.disabled = true;
    
    fetch('{{ route("admin.settings.test-freepbx") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'FreePBX Connection Successful', data.message);
        } else {
            showAlert('danger', 'FreePBX Connection Failed', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Connection Error', 'Failed to test FreePBX connection');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Check SIP server status
function checkSipServerStatus() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Checking...';
    btn.disabled = true;
    
    fetch('{{ route("admin.settings.sip-status") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        const statusDiv = document.getElementById('connectionStatus');
        const onlineStatus = document.getElementById('statusOnline');
        const offlineStatus = document.getElementById('statusOffline');
        
        statusDiv.classList.remove('d-none');
        
        if (data.success) {
            onlineStatus.style.display = 'inline';
            offlineStatus.style.display = 'none';
            showAlert('success', 'SIP Server Status', data.message);
        } else {
            onlineStatus.style.display = 'none';
            offlineStatus.style.display = 'inline';
            showAlert('warning', 'SIP Server Status', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Status Check Error', 'Failed to check SIP server status');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Reset group to defaults
function resetGroupToDefaults(group) {
    document.getElementById('resetGroupName').textContent = group;
    document.getElementById('resetGroupInput').value = group;
    
    const modal = new bootstrap.Modal(document.getElementById('resetModal'));
    modal.show();
}

// Show alert
function showAlert(type, title, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <strong>${title}:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.card-body');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}
</script>
@endpush