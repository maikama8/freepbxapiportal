@extends('layouts.admin')

@section('title', 'System Monitoring & Reports')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>System Monitoring & Reports</h2>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" onclick="refreshMetrics()">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="checkSystemHealth()">
                        <i class="fas fa-heartbeat"></i> Health Check
                    </button>
                </div>
            </div>

            <!-- System Health Status -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-heartbeat"></i> System Health Status
                                <span id="healthStatusBadge" class="badge bg-secondary ms-2">Checking...</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row" id="healthComponents">
                                <!-- Health components will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="row mb-4" id="metricsCards">
                <!-- Metrics cards will be loaded here -->
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Call Volume</h5>
                            <div class="btn-group btn-group-sm float-end">
                                <button type="button" class="btn btn-outline-secondary active" data-period="24h" onclick="loadCallVolumeChart('24h')">24H</button>
                                <button type="button" class="btn btn-outline-secondary" data-period="7d" onclick="loadCallVolumeChart('7d')">7D</button>
                                <button type="button" class="btn btn-outline-secondary" data-period="30d" onclick="loadCallVolumeChart('30d')">30D</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="callVolumeChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Revenue Trends</h5>
                            <div class="btn-group btn-group-sm float-end">
                                <button type="button" class="btn btn-outline-secondary active" data-period="7d" onclick="loadRevenueChart('7d')">7D</button>
                                <button type="button" class="btn btn-outline-secondary" data-period="30d" onclick="loadRevenueChart('30d')">30D</button>
                                <button type="button" class="btn btn-outline-secondary" data-period="90d" onclick="loadRevenueChart('90d')">90D</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Gateway Configuration -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card"></i> Payment Gateway Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row" id="paymentGatewayConfig">
                                <!-- Payment gateway config will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history"></i> Recent Audit Logs
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="auditLogsTable">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Audit logs will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> System Information
                            </h5>
                        </div>
                        <div class="card-body" id="systemInfo">
                            <!-- System info will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Gateway Config Modal -->
<div class="modal fade" id="paymentConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Gateway Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentConfigForm">
                <div class="modal-body">
                    <input type="hidden" id="config_gateway" name="gateway">
                    <div id="configFields">
                        <!-- Config fields will be loaded dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.metric-card {
    transition: transform 0.2s;
}
.metric-card:hover {
    transform: translateY(-2px);
}
.health-component {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}
.health-healthy {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.health-warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}
.health-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
.chart-container {
    position: relative;
    height: 300px;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let callVolumeChart = null;
let revenueChart = null;

$(document).ready(function() {
    // Load initial data
    refreshMetrics();
    checkSystemHealth();
    loadPaymentGatewayConfig();
    loadAuditLogs();
    
    // Load initial charts
    loadCallVolumeChart('24h');
    loadRevenueChart('7d');
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        refreshMetrics();
        checkSystemHealth();
    }, 300000);
});

// Refresh system metrics
function refreshMetrics() {
    $.ajax({
        url: '{{ route("admin.system.metrics") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                updateMetricsCards(response.metrics);
                updateSystemInfo(response.metrics.system);
                $('#lastUpdated').text('Last updated: ' + response.last_updated);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to refresh metrics');
        }
    });
}

// Update metrics cards
function updateMetricsCards(metrics) {
    const cardsHtml = `
        <div class="col-md-3">
            <div class="card metric-card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">${metrics.users.total_users}</h4>
                            <p class="mb-0">Total Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                    <small>Active: ${metrics.users.active_users} | New today: ${metrics.users.new_users_today}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">${metrics.calls.total_calls}</h4>
                            <p class="mb-0">Total Calls</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-phone fa-2x"></i>
                        </div>
                    </div>
                    <small>Active: ${metrics.calls.active_calls} | Today: ${metrics.calls.calls_today}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">$${parseFloat(metrics.financial.total_revenue).toFixed(2)}</h4>
                            <p class="mb-0">Total Revenue</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-dollar-sign fa-2x"></i>
                        </div>
                    </div>
                    <small>Today: $${parseFloat(metrics.financial.revenue_today).toFixed(2)} | This week: $${parseFloat(metrics.financial.revenue_this_week).toFixed(2)}</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0">$${parseFloat(metrics.financial.total_customer_balance).toFixed(2)}</h4>
                            <p class="mb-0">Customer Balance</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                    </div>
                    <small>Avg call cost: $${parseFloat(metrics.financial.average_call_cost).toFixed(4)}</small>
                </div>
            </div>
        </div>
    `;
    
    $('#metricsCards').html(cardsHtml);
}

// Update system info
function updateSystemInfo(systemMetrics) {
    const infoHtml = `
        <div class="row">
            <div class="col-12">
                <table class="table table-sm">
                    <tr><td><strong>PHP Version:</strong></td><td>${systemMetrics.php_version}</td></tr>
                    <tr><td><strong>Laravel Version:</strong></td><td>${systemMetrics.laravel_version}</td></tr>
                    <tr><td><strong>Database Size:</strong></td><td>${systemMetrics.database_size}</td></tr>
                    <tr><td><strong>Active Rates:</strong></td><td>${systemMetrics.active_rates}/${systemMetrics.total_rates}</td></tr>
                    <tr><td><strong>Audit Logs:</strong></td><td>${systemMetrics.audit_logs_count}</td></tr>
                    <tr><td><strong>System Uptime:</strong></td><td>${systemMetrics.uptime}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    $('#systemInfo').html(infoHtml);
}

// Check system health
function checkSystemHealth() {
    $.ajax({
        url: '{{ route("admin.system.health") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                updateHealthStatus(response.overall_status, response.components);
            }
        },
        error: function() {
            $('#healthStatusBadge').removeClass().addClass('badge bg-danger').text('Error');
        }
    });
}

// Update health status
function updateHealthStatus(overallStatus, components) {
    // Update overall status badge
    const badgeClass = overallStatus === 'healthy' ? 'bg-success' : 
                      overallStatus === 'warning' ? 'bg-warning' : 'bg-danger';
    $('#healthStatusBadge').removeClass().addClass(`badge ${badgeClass}`).text(overallStatus.toUpperCase());
    
    // Update components
    let componentsHtml = '';
    Object.keys(components).forEach(key => {
        const component = components[key];
        const statusClass = `health-${component.status}`;
        
        componentsHtml += `
            <div class="col-md-3">
                <div class="health-component ${statusClass}">
                    <h6 class="mb-1">${key.toUpperCase()}</h6>
                    <p class="mb-0 small">${component.message}</p>
                    ${component.response_time ? `<small>Response: ${component.response_time}</small>` : ''}
                    ${component.used_percent ? `<small>Usage: ${component.used_percent}%</small>` : ''}
                </div>
            </div>
        `;
    });
    
    $('#healthComponents').html(componentsHtml);
}

// Load call volume chart
function loadCallVolumeChart(period) {
    // Update active button
    $('[data-period]').removeClass('active');
    $(`[data-period="${period}"]`).addClass('active');
    
    $.ajax({
        url: '{{ route("admin.system.call-volume") }}',
        method: 'GET',
        data: { period: period },
        success: function(response) {
            if (response.success) {
                updateCallVolumeChart(response.data);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load call volume data');
        }
    });
}

// Update call volume chart
function updateCallVolumeChart(data) {
    const ctx = document.getElementById('callVolumeChart').getContext('2d');
    
    if (callVolumeChart) {
        callVolumeChart.destroy();
    }
    
    callVolumeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.label),
            datasets: [{
                label: 'Calls',
                data: data.map(item => item.calls),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Load revenue chart
function loadRevenueChart(period) {
    // Update active button
    $('[data-period]').removeClass('active');
    $(`[data-period="${period}"]`).addClass('active');
    
    $.ajax({
        url: '{{ route("admin.system.revenue") }}',
        method: 'GET',
        data: { period: period },
        success: function(response) {
            if (response.success) {
                updateRevenueChart(response.data);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load revenue data');
        }
    });
}

// Update revenue chart
function updateRevenueChart(data) {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    if (revenueChart) {
        revenueChart.destroy();
    }
    
    revenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.label),
            datasets: [{
                label: 'Revenue ($)',
                data: data.map(item => item.revenue),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Load payment gateway configuration
function loadPaymentGatewayConfig() {
    $.ajax({
        url: '{{ route("admin.system.payment-config") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                updatePaymentGatewayConfig(response.config);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load payment gateway configuration');
        }
    });
}

// Update payment gateway configuration display
function updatePaymentGatewayConfig(config) {
    let configHtml = '';
    
    Object.keys(config).forEach(gateway => {
        const gatewayConfig = config[gateway];
        const statusBadge = gatewayConfig.enabled ? 
            '<span class="badge bg-success">Enabled</span>' : 
            '<span class="badge bg-secondary">Disabled</span>';
        
        const sandboxBadge = gatewayConfig.sandbox_mode ? 
            '<span class="badge bg-warning ms-1">Sandbox</span>' : '';
        
        configHtml += `
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">${gateway.toUpperCase()} ${statusBadge} ${sandboxBadge}</h6>
                        <p class="card-text small">
                            API Key: ${gatewayConfig.api_key_set || gatewayConfig.client_id_set ? '✓ Set' : '✗ Not Set'}<br>
                            ${gatewayConfig.client_secret_set !== undefined ? 
                                `Client Secret: ${gatewayConfig.client_secret_set ? '✓ Set' : '✗ Not Set'}<br>` : ''}
                        </p>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="configureGateway('${gateway}')">
                            Configure
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    $('#paymentGatewayConfig').html(configHtml);
}

// Configure payment gateway
function configureGateway(gateway) {
    $('#config_gateway').val(gateway);
    
    let fieldsHtml = '';
    if (gateway === 'nowpayments') {
        fieldsHtml = `
            <div class="mb-3">
                <label class="form-label">API Key</label>
                <input type="password" class="form-control" name="config[api_key]" placeholder="Enter NowPayments API Key">
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="config[sandbox]" id="nowpayments_sandbox">
                <label class="form-check-label" for="nowpayments_sandbox">
                    Sandbox Mode
                </label>
            </div>
        `;
    } else if (gateway === 'paypal') {
        fieldsHtml = `
            <div class="mb-3">
                <label class="form-label">Client ID</label>
                <input type="text" class="form-control" name="config[client_id]" placeholder="Enter PayPal Client ID">
            </div>
            <div class="mb-3">
                <label class="form-label">Client Secret</label>
                <input type="password" class="form-control" name="config[client_secret]" placeholder="Enter PayPal Client Secret">
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="config[sandbox]" id="paypal_sandbox">
                <label class="form-check-label" for="paypal_sandbox">
                    Sandbox Mode
                </label>
            </div>
        `;
    }
    
    $('#configFields').html(fieldsHtml);
    $('#paymentConfigModal').modal('show');
}

// Load audit logs
function loadAuditLogs() {
    $.ajax({
        url: '{{ route("admin.system.audit-logs") }}',
        method: 'GET',
        data: { limit: 20 },
        success: function(response) {
            if (response.success) {
                updateAuditLogsTable(response.logs);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load audit logs');
        }
    });
}

// Update audit logs table
function updateAuditLogsTable(logs) {
    let tableHtml = '';
    
    logs.forEach(log => {
        tableHtml += `
            <tr>
                <td class="small">${log.created_at}</td>
                <td class="small">${log.user}</td>
                <td class="small">
                    <span class="badge bg-info">${log.action}</span>
                </td>
                <td class="small">${log.description}</td>
                <td class="small">${log.ip_address}</td>
            </tr>
        `;
    });
    
    $('#auditLogsTable tbody').html(tableHtml);
}

// Payment config form submission
$('#paymentConfigForm').submit(function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    $.ajax({
        url: '{{ route("admin.system.payment-config.update") }}',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $('#paymentConfigModal').modal('hide');
                loadPaymentGatewayConfig();
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr) {
            showAlert('danger', xhr.responseJSON?.message || 'An error occurred');
        }
    });
});

// Show alert function
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top of the container
    $('.container-fluid').prepend(alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>
@endpush