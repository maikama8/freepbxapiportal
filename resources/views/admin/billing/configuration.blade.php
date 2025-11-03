@extends('layouts.sneat-admin')

@section('title', 'Advanced Billing Configuration')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Advanced Billing Configuration</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-info" onclick="showMonitoring()">
                            <i class="bx bx-line-chart"></i> Real-time Monitoring
                        </button>
                        <button type="button" class="btn btn-warning" onclick="showRulesManagement()">
                            <i class="bx bx-cog"></i> Billing Rules
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportConfiguration()">
                            <i class="bx bx-download"></i> Export Config
                        </button>
                        <button type="button" class="btn btn-primary" onclick="showImportModal()">
                            <i class="bx bx-upload"></i> Import Config
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Performance Metrics Overview -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4 id="callsProcessedHour">{{ $performanceMetrics['calls_processed_last_hour'] ?? 0 }}</h4>
                                    <p class="mb-0">Calls/Hour</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4 id="billingAccuracy">{{ number_format($performanceMetrics['billing_accuracy'] ?? 100, 1) }}%</h4>
                                    <p class="mb-0">Accuracy</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4 id="pendingBilling">{{ $performanceMetrics['pending_billing_count'] ?? 0 }}</h4>
                                    <p class="mb-0">Pending</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h4 id="failedBilling">{{ $performanceMetrics['failed_billing_count'] ?? 0 }}</h4>
                                    <p class="mb-0">Failed</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuration Form -->
                    <form id="billingConfigForm">
                        <div class="row">
                            <!-- Basic Billing Settings -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Basic Billing Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Default Billing Increment</label>
                                            <select class="form-select" name="default_billing_increment" required>
                                                <option value="1" {{ ($billingConfig['default_billing_increment'] ?? 60) == 1 ? 'selected' : '' }}>1 second</option>
                                                <option value="6" {{ ($billingConfig['default_billing_increment'] ?? 60) == 6 ? 'selected' : '' }}>6 seconds</option>
                                                <option value="30" {{ ($billingConfig['default_billing_increment'] ?? 60) == 30 ? 'selected' : '' }}>30 seconds</option>
                                                <option value="60" {{ ($billingConfig['default_billing_increment'] ?? 60) == 60 ? 'selected' : '' }}>60 seconds</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Minimum Call Duration (seconds)</label>
                                            <input type="number" class="form-control" name="minimum_call_duration" 
                                                   value="{{ $billingConfig['minimum_call_duration'] ?? 0 }}" min="0" max="300" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Grace Period (seconds)</label>
                                            <input type="number" class="form-control" name="grace_period_seconds" 
                                                   value="{{ $billingConfig['grace_period_seconds'] ?? 5 }}" min="0" max="60" required>
                                            <div class="form-text">Time before billing starts after call connection</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Billing Precision (decimal places)</label>
                                            <select class="form-select" name="billing_precision" required>
                                                <option value="2" {{ ($billingConfig['billing_precision'] ?? 4) == 2 ? 'selected' : '' }}>2 places ($0.12)</option>
                                                <option value="4" {{ ($billingConfig['billing_precision'] ?? 4) == 4 ? 'selected' : '' }}>4 places ($0.1234)</option>
                                                <option value="6" {{ ($billingConfig['billing_precision'] ?? 4) == 6 ? 'selected' : '' }}>6 places ($0.123456)</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Rounding Method</label>
                                            <select class="form-select" name="rounding_method" required>
                                                <option value="up" {{ ($billingConfig['rounding_method'] ?? 'up') == 'up' ? 'selected' : '' }}>Round Up</option>
                                                <option value="down" {{ ($billingConfig['rounding_method'] ?? 'up') == 'down' ? 'selected' : '' }}>Round Down</option>
                                                <option value="nearest" {{ ($billingConfig['rounding_method'] ?? 'up') == 'nearest' ? 'selected' : '' }}>Round to Nearest</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Real-time Billing Settings -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Real-time Billing Settings</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="real_time_billing_enabled" 
                                                       {{ ($billingConfig['real_time_billing_enabled'] ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label">Enable Real-time Billing</label>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="auto_terminate_on_zero_balance" 
                                                       {{ ($billingConfig['auto_terminate_on_zero_balance'] ?? true) ? 'checked' : '' }}>
                                                <label class="form-check-label">Auto-terminate on Zero Balance</label>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Balance Check Interval (seconds)</label>
                                            <input type="number" class="form-control" name="balance_check_interval" 
                                                   value="{{ $billingConfig['balance_check_interval'] ?? 30 }}" min="1" max="60" required>
                                            <div class="form-text">How often to check balance during active calls</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Low Balance Threshold ($)</label>
                                            <input type="number" class="form-control" name="low_balance_threshold" 
                                                   value="{{ $billingConfig['low_balance_threshold'] ?? 10.0 }}" step="0.01" min="0" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Critical Balance Threshold ($)</label>
                                            <input type="number" class="form-control" name="critical_balance_threshold" 
                                                   value="{{ $billingConfig['critical_balance_threshold'] ?? 2.0 }}" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <!-- Time-based Rate Multipliers -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Time-based Rate Multipliers</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Weekend Rate Multiplier</label>
                                            <input type="number" class="form-control" name="weekend_rate_multiplier" 
                                                   value="{{ $billingConfig['weekend_rate_multiplier'] ?? 1.0 }}" step="0.1" min="0.1" max="10" required>
                                            <div class="form-text">1.0 = normal rate, 1.5 = 50% surcharge</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Holiday Rate Multiplier</label>
                                            <input type="number" class="form-control" name="holiday_rate_multiplier" 
                                                   value="{{ $billingConfig['holiday_rate_multiplier'] ?? 1.5 }}" step="0.1" min="0.1" max="10" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Peak Hours Start</label>
                                            <input type="time" class="form-control" name="peak_hours_start" 
                                                   value="{{ $billingConfig['peak_hours_start'] ?? '08:00' }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Peak Hours End</label>
                                            <input type="time" class="form-control" name="peak_hours_end" 
                                                   value="{{ $billingConfig['peak_hours_end'] ?? '18:00' }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Peak Rate Multiplier</label>
                                            <input type="number" class="form-control" name="peak_rate_multiplier" 
                                                   value="{{ $billingConfig['peak_rate_multiplier'] ?? 1.2 }}" step="0.1" min="0.1" max="10" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Billing Test Panel -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Billing Test & Validation</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Test Destination</label>
                                            <input type="text" class="form-control" id="testDestination" placeholder="+1234567890">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Test Duration (seconds)</label>
                                            <input type="number" class="form-control" id="testDuration" value="120" min="1">
                                        </div>
                                        <button type="button" class="btn btn-info w-100 mb-3" onclick="testBillingCalculation()">
                                            <i class="bx bx-calculator"></i> Test Billing Calculation
                                        </button>
                                        <div id="testResults" class="alert alert-info" style="display: none;">
                                            <h6>Test Results:</h6>
                                            <div id="testResultsContent"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">
                                        <i class="bx bx-reset"></i> Reset to Defaults
                                    </button>
                                    <div>
                                        <button type="button" class="btn btn-warning me-2" onclick="validateConfiguration()">
                                            <i class="bx bx-check-circle"></i> Validate Configuration
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bx bx-save"></i> Save Configuration
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Real-time Monitoring Modal -->
<div class="modal fade" id="monitoringModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Real-time Billing Monitoring</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="monitoringContent">
                    Loading monitoring data...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Billing Rules Management Modal -->
<div class="modal fade" id="rulesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Billing Rules Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="rulesContent">
                    Loading billing rules...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Import Configuration Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Billing Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="importConfigForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Configuration File</label>
                        <input type="file" class="form-control" name="config_file" accept=".json" required>
                        <div class="form-text">Select a JSON configuration file exported from this system</div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bx bx-warning"></i>
                        <strong>Warning:</strong> Importing will overwrite current billing configuration and rules.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let monitoringInterval;

$(document).ready(function() {
    initializeEventHandlers();
    startPerformanceUpdates();
});

function initializeEventHandlers() {
    // Configuration form submission
    $('#billingConfigForm').on('submit', function(e) {
        e.preventDefault();
        saveConfiguration();
    });

    // Import form submission
    $('#importConfigForm').on('submit', function(e) {
        e.preventDefault();
        importConfiguration();
    });

    // Real-time billing toggle
    $('input[name="real_time_billing_enabled"]').on('change', function() {
        const isEnabled = $(this).is(':checked');
        $('input[name="auto_terminate_on_zero_balance"], input[name="balance_check_interval"]')
            .prop('disabled', !isEnabled);
    });
}

function startPerformanceUpdates() {
    // Update performance metrics every 30 seconds
    setInterval(updatePerformanceMetrics, 30000);
}

function updatePerformanceMetrics() {
    $.get('{{ route("admin.billing.monitoring-data") }}', function(response) {
        if (response.success) {
            const metrics = response.data.performance_metrics;
            $('#callsProcessedHour').text(metrics.calls_processed_last_hour || 0);
            $('#billingAccuracy').text((metrics.billing_accuracy || 100).toFixed(1) + '%');
            $('#pendingBilling').text(metrics.pending_billing_count || 0);
            $('#failedBilling').text(metrics.failed_billing_count || 0);
        }
    });
}

function saveConfiguration() {
    const formData = new FormData($('#billingConfigForm')[0]);
    
    // Convert checkboxes to boolean values
    formData.set('real_time_billing_enabled', $('input[name="real_time_billing_enabled"]').is(':checked'));
    formData.set('auto_terminate_on_zero_balance', $('input[name="auto_terminate_on_zero_balance"]').is(':checked'));

    $.ajax({
        url: '{{ route("admin.billing.update-configuration") }}',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                updatePerformanceMetrics();
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(errorArray => {
                    errorArray.forEach(error => toastr.error(error));
                });
            } else {
                toastr.error('Failed to save configuration');
            }
        }
    });
}

function validateConfiguration() {
    const formData = new FormData($('#billingConfigForm')[0]);
    
    // Perform client-side validation
    let isValid = true;
    let errors = [];

    // Check required fields
    const requiredFields = [
        'default_billing_increment',
        'minimum_call_duration',
        'grace_period_seconds',
        'balance_check_interval',
        'billing_precision',
        'rounding_method'
    ];

    requiredFields.forEach(field => {
        if (!formData.get(field)) {
            errors.push(`${field.replace('_', ' ')} is required`);
            isValid = false;
        }
    });

    // Check numeric ranges
    const gracePeriod = parseInt(formData.get('grace_period_seconds'));
    if (gracePeriod < 0 || gracePeriod > 60) {
        errors.push('Grace period must be between 0 and 60 seconds');
        isValid = false;
    }

    const balanceInterval = parseInt(formData.get('balance_check_interval'));
    if (balanceInterval < 1 || balanceInterval > 60) {
        errors.push('Balance check interval must be between 1 and 60 seconds');
        isValid = false;
    }

    if (isValid) {
        toastr.success('Configuration validation passed');
    } else {
        errors.forEach(error => toastr.error(error));
    }

    return isValid;
}

function testBillingCalculation() {
    const destination = $('#testDestination').val();
    const duration = $('#testDuration').val();

    if (!destination || !duration) {
        toastr.warning('Please enter both destination and duration for testing');
        return;
    }

    $.post('{{ route("admin.billing.test") }}', {
        destination: destination,
        duration: parseInt(duration),
        _token: $('meta[name="csrf-token"]').attr('content')
    }, function(response) {
        if (response.success) {
            displayTestResults(response.result);
        } else {
            toastr.error(response.message || 'Failed to test billing calculation');
        }
    });
}

function displayTestResults(result) {
    const html = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>Destination:</strong> ${result.destination || 'N/A'}</p>
                <p><strong>Duration:</strong> ${result.duration} seconds</p>
                <p><strong>Rate Found:</strong> ${result.rate_found ? 'Yes' : 'No'}</p>
                <p><strong>Rate per Minute:</strong> $${result.rate_per_minute || 'N/A'}</p>
            </div>
            <div class="col-md-6">
                <p><strong>Billing Increment:</strong> ${result.billing_increment || 'N/A'} seconds</p>
                <p><strong>Billable Duration:</strong> ${result.billable_duration || 'N/A'} seconds</p>
                <p><strong>Calculated Cost:</strong> $${result.calculated_cost || 'N/A'}</p>
                <p><strong>Processing Time:</strong> ${result.processing_time || 'N/A'}ms</p>
            </div>
        </div>
    `;
    
    $('#testResultsContent').html(html);
    $('#testResults').show();
}

function showMonitoring() {
    $('#monitoringModal').modal('show');
    loadMonitoringData();
    
    // Start real-time updates
    monitoringInterval = setInterval(loadMonitoringData, 5000);
    
    $('#monitoringModal').on('hidden.bs.modal', function() {
        if (monitoringInterval) {
            clearInterval(monitoringInterval);
        }
    });
}

function loadMonitoringData() {
    $.get('{{ route("admin.billing.monitoring-data") }}', function(response) {
        if (response.success) {
            displayMonitoringData(response.data);
        }
    });
}

function displayMonitoringData(data) {
    const html = `
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4>${data.active_calls.total_active}</h4>
                        <p class="mb-0">Active Calls</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h4>${data.billing_queue.pending_count}</h4>
                        <p class="mb-0">Pending Billing</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h4>${data.billing_queue.failed_count}</h4>
                        <p class="mb-0">Failed Billing</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card ${data.system_health.status === 'healthy' ? 'bg-success' : 'bg-warning'} text-white">
                    <div class="card-body text-center">
                        <h4>${data.system_health.status.toUpperCase()}</h4>
                        <p class="mb-0">System Health</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h6>Recent Billing Activities</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Destination</th>
                                <th>Cost</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.recent_activities.slice(0, 10).map(activity => `
                                <tr>
                                    <td>${activity.user_name}</td>
                                    <td>${activity.destination}</td>
                                    <td>$${activity.cost}</td>
                                    <td><span class="badge bg-${activity.billing_status === 'completed' ? 'success' : 'warning'}">${activity.billing_status}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h6>Performance Metrics</h6>
                <p><strong>Calls Processed (Last Hour):</strong> ${data.performance_metrics.calls_processed_last_hour}</p>
                <p><strong>Billing Accuracy:</strong> ${data.performance_metrics.billing_accuracy.toFixed(2)}%</p>
                <p><strong>Average Processing Time:</strong> ${data.performance_metrics.average_processing_time}s</p>
                <p><strong>Queue Age:</strong> ${data.billing_queue.queue_age} minutes</p>
            </div>
        </div>
    `;
    
    $('#monitoringContent').html(html);
}

function showRulesManagement() {
    $('#rulesModal').modal('show');
    loadBillingRules();
}

function loadBillingRules() {
    // This would load the billing rules management interface
    $('#rulesContent').html(`
        <div class="alert alert-info">
            <i class="bx bx-info-circle"></i>
            Billing rules management interface will be implemented in the next phase.
            This will allow creating custom billing rules based on conditions like:
            <ul class="mt-2">
                <li>User balance thresholds</li>
                <li>Call duration limits</li>
                <li>Destination-based rules</li>
                <li>Time-based rate adjustments</li>
            </ul>
        </div>
    `);
}

function resetToDefaults() {
    Swal.fire({
        title: 'Reset to Defaults?',
        text: 'This will reset all billing configuration to default values.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, reset!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Reset form to default values
            $('select[name="default_billing_increment"]').val('60');
            $('input[name="minimum_call_duration"]').val('0');
            $('input[name="grace_period_seconds"]').val('5');
            $('select[name="billing_precision"]').val('4');
            $('select[name="rounding_method"]').val('up');
            $('input[name="real_time_billing_enabled"]').prop('checked', true);
            $('input[name="auto_terminate_on_zero_balance"]').prop('checked', true);
            $('input[name="balance_check_interval"]').val('30');
            $('input[name="weekend_rate_multiplier"]').val('1.0');
            $('input[name="holiday_rate_multiplier"]').val('1.5');
            $('input[name="peak_hours_start"]').val('08:00');
            $('input[name="peak_hours_end"]').val('18:00');
            $('input[name="peak_rate_multiplier"]').val('1.2');
            $('input[name="low_balance_threshold"]').val('10.0');
            $('input[name="critical_balance_threshold"]').val('2.0');
            
            toastr.info('Form reset to default values. Click Save to apply changes.');
        }
    });
}

function exportConfiguration() {
    window.open('{{ route("admin.billing.export-configuration") }}', '_blank');
}

function showImportModal() {
    $('#importModal').modal('show');
}

function importConfiguration() {
    const formData = new FormData($('#importConfigForm')[0]);

    $.ajax({
        url: '{{ route("admin.billing.import-configuration") }}',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#importModal').modal('hide');
                location.reload(); // Reload to show imported configuration
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to import configuration');
        }
    });
}
</script>
@endpush