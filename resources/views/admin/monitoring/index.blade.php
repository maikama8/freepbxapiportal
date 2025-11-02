@extends('layouts.admin')

@section('title', 'System Monitoring')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">System Monitoring</h1>
                <div>
                    <button class="btn btn-outline-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-outline-secondary" onclick="toggleAutoRefresh()">
                        <i class="fas fa-clock"></i> <span id="autoRefreshText">Enable Auto-refresh</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-database fa-2x" id="db-icon"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-1">Database</h6>
                            <p class="card-text mb-0" id="db-status">{{ $health['database']['status'] ?? 'Unknown' }}</p>
                            <small class="text-muted" id="db-response-time">
                                @if(isset($health['database']['response_time_ms']))
                                    {{ $health['database']['response_time_ms'] }}ms
                                @endif
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-memory fa-2x" id="cache-icon"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-1">Cache</h6>
                            <p class="card-text mb-0" id="cache-status">{{ $health['cache']['status'] ?? 'Unknown' }}</p>
                            <small class="text-muted" id="cache-driver">{{ $health['cache']['driver'] ?? '' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-hdd fa-2x" id="disk-icon"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-1">Disk Space</h6>
                            <p class="card-text mb-0" id="disk-status">{{ $health['disk_space']['status'] ?? 'Unknown' }}</p>
                            <small class="text-muted" id="disk-usage">
                                @if(isset($health['disk_space']['usage_percentage']))
                                    {{ $health['disk_space']['usage_percentage'] }}% used
                                @endif
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-1">Active Users</h6>
                            <p class="card-text mb-0" id="active-users">{{ $health['active_users'] ?? 0 }}</p>
                            <small class="text-muted">Last 24 hours</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Call Metrics (24h)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-primary" id="total-calls">{{ $health['call_metrics']['total_calls'] ?? 0 }}</h4>
                                <small class="text-muted">Total Calls</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-success" id="successful-calls">{{ $health['call_metrics']['successful_calls'] ?? 0 }}</h4>
                                <small class="text-muted">Successful</small>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-danger" id="failed-calls">{{ $health['call_metrics']['failed_calls'] ?? 0 }}</h4>
                                <small class="text-muted">Failed</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-info" id="avg-duration">
                                    @if(isset($health['call_metrics']['average_duration_seconds']))
                                        {{ round($health['call_metrics']['average_duration_seconds']) }}s
                                    @else
                                        0s
                                    @endif
                                </h4>
                                <small class="text-muted">Avg Duration</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Metrics (24h)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-primary" id="total-payments">{{ $health['payment_metrics']['total_transactions'] ?? 0 }}</h4>
                                <small class="text-muted">Total Transactions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-success" id="successful-payments">{{ $health['payment_metrics']['successful_payments'] ?? 0 }}</h4>
                                <small class="text-muted">Successful</small>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-danger" id="failed-payments">{{ $health['payment_metrics']['failed_payments'] ?? 0 }}</h4>
                                <small class="text-muted">Failed</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-warning" id="total-amount">
                                    ${{ number_format($health['payment_metrics']['total_amount'] ?? 0, 2) }}
                                </h4>
                                <small class="text-muted">Total Amount</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Resources -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">System Resources</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Memory Usage</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar" style="width: 45%" id="memory-progress"></div>
                            </div>
                            <small class="text-muted" id="memory-details">
                                @if(isset($health['memory_usage']))
                                    {{ $health['memory_usage']['current_mb'] }}MB / {{ $health['memory_usage']['limit_mb'] }}
                                @endif
                            </small>
                        </div>
                        <div class="col-md-4">
                            <h6>Disk Usage</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: {{ $health['disk_space']['usage_percentage'] ?? 0 }}%" 
                                     id="disk-progress"></div>
                            </div>
                            <small class="text-muted" id="disk-details">
                                @if(isset($health['disk_space']))
                                    {{ $health['disk_space']['used_gb'] }}GB / {{ $health['disk_space']['total_gb'] }}GB
                                @endif
                            </small>
                        </div>
                        <div class="col-md-4">
                            <h6>Database Connections</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar" style="width: 30%" id="db-connections-progress"></div>
                            </div>
                            <small class="text-muted" id="db-connections-details">
                                {{ $health['database']['connections'] ?? 0 }} active connections
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Viewer -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">System Logs</h5>
                        <div>
                            <select class="form-select form-select-sm" id="log-channel" onchange="loadLogs()">
                                <option value="laravel">Application</option>
                                <option value="audit">Audit</option>
                                <option value="payment">Payment</option>
                                <option value="calls">Calls</option>
                                <option value="monitoring">Monitoring</option>
                                <option value="alerts">Alerts</option>
                                <option value="security">Security</option>
                                <option value="performance">Performance</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="logs-container" style="height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                        <div class="text-center text-muted">
                            <i class="fas fa-spinner fa-spin"></i> Loading logs...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval = null;
let autoRefreshEnabled = false;

function refreshData() {
    // Refresh system health
    fetch('/admin/monitoring/health')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateHealthDisplay(data.data);
            }
        })
        .catch(error => console.error('Error refreshing health data:', error));
    
    // Refresh logs
    loadLogs();
}

function updateHealthDisplay(health) {
    // Update database status
    updateStatusIcon('db-icon', health.database.status);
    document.getElementById('db-status').textContent = health.database.status;
    if (health.database.response_time_ms) {
        document.getElementById('db-response-time').textContent = health.database.response_time_ms + 'ms';
    }
    
    // Update cache status
    updateStatusIcon('cache-icon', health.cache.status);
    document.getElementById('cache-status').textContent = health.cache.status;
    
    // Update disk status
    updateStatusIcon('disk-icon', health.disk_space.status);
    document.getElementById('disk-status').textContent = health.disk_space.status;
    document.getElementById('disk-usage').textContent = health.disk_space.usage_percentage + '% used';
    
    // Update active users
    document.getElementById('active-users').textContent = health.active_users;
    
    // Update call metrics
    document.getElementById('total-calls').textContent = health.call_metrics.total_calls;
    document.getElementById('successful-calls').textContent = health.call_metrics.successful_calls;
    document.getElementById('failed-calls').textContent = health.call_metrics.failed_calls;
    document.getElementById('avg-duration').textContent = Math.round(health.call_metrics.average_duration_seconds || 0) + 's';
    
    // Update payment metrics
    document.getElementById('total-payments').textContent = health.payment_metrics.total_transactions;
    document.getElementById('successful-payments').textContent = health.payment_metrics.successful_payments;
    document.getElementById('failed-payments').textContent = health.payment_metrics.failed_payments;
    document.getElementById('total-amount').textContent = '$' + parseFloat(health.payment_metrics.total_amount || 0).toFixed(2);
    
    // Update progress bars
    document.getElementById('disk-progress').style.width = health.disk_space.usage_percentage + '%';
    document.getElementById('disk-details').textContent = health.disk_space.used_gb + 'GB / ' + health.disk_space.total_gb + 'GB';
}

function updateStatusIcon(iconId, status) {
    const icon = document.getElementById(iconId);
    icon.className = icon.className.replace(/text-\w+/, '');
    
    switch (status) {
        case 'healthy':
            icon.classList.add('text-success');
            break;
        case 'warning':
            icon.classList.add('text-warning');
            break;
        case 'critical':
        case 'unhealthy':
            icon.classList.add('text-danger');
            break;
        default:
            icon.classList.add('text-secondary');
    }
}

function loadLogs() {
    const channel = document.getElementById('log-channel').value;
    const container = document.getElementById('logs-container');
    
    container.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading logs...</div>';
    
    fetch(`/admin/monitoring/logs?channel=${channel}&lines=50`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLogs(data.data.logs);
            } else {
                container.innerHTML = '<div class="text-danger">Error loading logs: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="text-danger">Error loading logs: ' + error.message + '</div>';
        });
}

function displayLogs(logs) {
    const container = document.getElementById('logs-container');
    
    if (logs.length === 0) {
        container.innerHTML = '<div class="text-muted">No logs found</div>';
        return;
    }
    
    let html = '';
    logs.forEach(log => {
        const levelClass = getLevelClass(log.level);
        html += `<div class="mb-1">
            <span class="text-muted">[${log.timestamp || 'Unknown'}]</span>
            <span class="badge ${levelClass}">${log.level}</span>
            <span>${escapeHtml(log.message)}</span>
        </div>`;
    });
    
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

function getLevelClass(level) {
    switch (level?.toLowerCase()) {
        case 'error':
        case 'critical':
        case 'alert':
        case 'emergency':
            return 'bg-danger';
        case 'warning':
            return 'bg-warning';
        case 'info':
        case 'notice':
            return 'bg-info';
        case 'debug':
            return 'bg-secondary';
        default:
            return 'bg-light text-dark';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleAutoRefresh() {
    if (autoRefreshEnabled) {
        clearInterval(autoRefreshInterval);
        autoRefreshEnabled = false;
        document.getElementById('autoRefreshText').textContent = 'Enable Auto-refresh';
    } else {
        autoRefreshInterval = setInterval(refreshData, 30000); // 30 seconds
        autoRefreshEnabled = true;
        document.getElementById('autoRefreshText').textContent = 'Disable Auto-refresh';
    }
}

// Load logs on page load
document.addEventListener('DOMContentLoaded', function() {
    loadLogs();
});
</script>
@endsection