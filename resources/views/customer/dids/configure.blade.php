@extends('layouts.sneat-customer')

@section('title', 'Configure DID - ' . $didNumber->formatted_number)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('customer.dids.index') }}">My DIDs</a></li>
                    <li class="breadcrumb-item active">Configure {{ $didNumber->formatted_number }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- DID Information Card -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">DID Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">DID Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bx bx-phone"></i></span>
                            <input type="text" class="form-control" value="{{ $didNumber->formatted_number }}" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Country</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <span class="fi fi-{{ strtolower($didNumber->country_code) }}"></span>
                            </span>
                            <input type="text" class="form-control" value="{{ $didNumber->countryRate->country_name ?? $didNumber->country_code }}" readonly>
                        </div>
                    </div>
                    
                    @if($didNumber->area_code)
                    <div class="mb-3">
                        <label class="form-label">Area Code</label>
                        <input type="text" class="form-control" value="{{ $didNumber->area_code }}" readonly>
                    </div>
                    @endif
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div>
                            @if($didNumber->status === 'active')
                                <span class="badge bg-success">Active</span>
                            @elseif($didNumber->status === 'suspended')
                                <span class="badge bg-warning">Suspended</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($didNumber->status) }}</span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monthly Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" class="form-control" value="{{ number_format($didNumber->monthly_cost, 2) }}" readonly>
                        </div>
                    </div>
                    
                    @if($didNumber->expires_at)
                    <div class="mb-3">
                        <label class="form-label">Expires</label>
                        <input type="text" class="form-control" value="{{ $didNumber->expires_at->format('M d, Y') }}" readonly>
                        @if($didNumber->isExpiringSoon())
                            <small class="text-warning">Expires {{ $didNumber->expires_at->diffForHumans() }}</small>
                        @endif
                    </div>
                    @endif
                    
                    <div class="mb-3">
                        <label class="form-label">Features</label>
                        <div>
                            @if($didNumber->features)
                                @foreach($didNumber->features as $feature)
                                    <span class="badge bg-info me-1">{{ ucfirst($feature) }}</span>
                                @endforeach
                            @else
                                <span class="badge bg-info">Voice</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration Settings -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">DID Configuration</h5>
                </div>
                <div class="card-body">
                    <form id="did-config-form">
                        @csrf
                        
                        <!-- Extension Assignment -->
                        <div class="mb-4">
                            <h6 class="mb-3">Extension Assignment</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Assigned Extension</label>
                                    <select class="form-select" name="assigned_extension" id="assigned_extension">
                                        <option value="">Select Extension</option>
                                        @foreach($userExtensions as $extension)
                                            <option value="{{ $extension->extension }}" 
                                                    {{ $didNumber->assigned_extension == $extension->extension ? 'selected' : '' }}>
                                                {{ $extension->extension }} - {{ $extension->caller_id_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Choose which extension receives calls to this DID</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Call Routing</label>
                                    <select class="form-select" name="call_routing" id="call_routing">
                                        <option value="direct">Direct to Extension</option>
                                        <option value="voicemail">Direct to Voicemail</option>
                                        <option value="ivr">Route to IVR</option>
                                        <option value="forward">Forward to External Number</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Call Forwarding Settings -->
                        <div class="mb-4" id="forwarding-settings" style="display: none;">
                            <h6 class="mb-3">Call Forwarding</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Forward to Number</label>
                                    <input type="tel" class="form-control" name="forward_number" id="forward_number" 
                                           placeholder="+1234567890">
                                    <small class="text-muted">Enter the number to forward calls to</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Forward on Busy</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="forward_on_busy" id="forward_on_busy">
                                        <label class="form-check-label" for="forward_on_busy">
                                            Forward when extension is busy
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Voicemail Settings -->
                        <div class="mb-4">
                            <h6 class="mb-3">Voicemail Settings</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="voicemail_enabled" id="voicemail_enabled" checked>
                                        <label class="form-check-label" for="voicemail_enabled">
                                            Enable Voicemail
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="voicemail_email" id="voicemail_email">
                                        <label class="form-check-label" for="voicemail_email">
                                            Email Voicemail Notifications
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Call Recording -->
                        <div class="mb-4">
                            <h6 class="mb-3">Call Recording</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Recording Mode</label>
                                    <select class="form-select" name="recording_mode" id="recording_mode">
                                        <option value="disabled">Disabled</option>
                                        <option value="inbound">Inbound Only</option>
                                        <option value="outbound">Outbound Only</option>
                                        <option value="both">Both Directions</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" name="recording_announcement" id="recording_announcement">
                                        <label class="form-check-label" for="recording_announcement">
                                            Play Recording Announcement
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Business Hours -->
                        <div class="mb-4">
                            <h6 class="mb-3">Business Hours Routing</h6>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="business_hours_enabled" id="business_hours_enabled">
                                <label class="form-check-label" for="business_hours_enabled">
                                    Enable Business Hours Routing
                                </label>
                            </div>
                            
                            <div id="business-hours-config" style="display: none;">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Time</label>
                                        <input type="time" class="form-control" name="business_start" value="09:00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Time</label>
                                        <input type="time" class="form-control" name="business_end" value="17:00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Timezone</label>
                                        <select class="form-select" name="timezone">
                                            <option value="America/New_York">Eastern Time</option>
                                            <option value="America/Chicago">Central Time</option>
                                            <option value="America/Denver">Mountain Time</option>
                                            <option value="America/Los_Angeles">Pacific Time</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">After Hours Action</label>
                                        <select class="form-select" name="after_hours_action">
                                            <option value="voicemail">Send to Voicemail</option>
                                            <option value="forward">Forward to Number</option>
                                            <option value="busy">Play Busy Signal</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('customer.dids.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Back to DIDs
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save"></i> Save Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Statistics -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Usage Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="border-end">
                                <h4 class="mb-1" id="total-calls">{{ $usageStats['total_calls'] ?? 0 }}</h4>
                                <small class="text-muted">Total Calls</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border-end">
                                <h4 class="mb-1" id="total-minutes">{{ $usageStats['total_minutes'] ?? 0 }}</h4>
                                <small class="text-muted">Total Minutes</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border-end">
                                <h4 class="mb-1" id="monthly-calls">{{ $usageStats['monthly_calls'] ?? 0 }}</h4>
                                <small class="text-muted">This Month</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <h4 class="mb-1" id="avg-duration">{{ $usageStats['avg_duration'] ?? '0:00' }}</h4>
                            <small class="text-muted">Avg Duration</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Show/hide forwarding settings based on call routing selection
    $('#call_routing').on('change', function() {
        if ($(this).val() === 'forward') {
            $('#forwarding-settings').show();
        } else {
            $('#forwarding-settings').hide();
        }
    });

    // Show/hide business hours config
    $('#business_hours_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#business-hours-config').show();
        } else {
            $('#business-hours-config').hide();
        }
    });

    // Form submission
    $('#did-config-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const btn = $(this).find('button[type="submit"]');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
        
        $.ajax({
            url: '{{ route("customer.dids.configure", $didNumber->id) }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'DID configuration saved successfully!');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('error', response?.message || 'Failed to save configuration');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
});

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alert = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.container-xxl').prepend(alert);
    
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>
@endpush
</content>