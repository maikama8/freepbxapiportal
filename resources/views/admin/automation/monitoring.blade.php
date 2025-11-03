@extends('layouts.sneat-admin')

@section('title', 'Automation Monitoring Dashboard')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- System Health Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">System Health Overview</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-info" onclick="refreshDashboard()">
                            <i class="bx bx-refresh"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-warning" onclick="showAlertsConfig()">
                            <i class="bx bx-bell"></i> Configure Alerts
                        </button>
                        <button type="button" class="btn btn-success" onclick="triggerHealthCheck()">
                            <i class="bx bx-check-shield"></i> Health Check
                        </button>
                        <button type="button" class="btn btn-primary" onclick="showExportModal()">
                            <i class="bx bx-download"></i> Export Data
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="card bg-{{ $systemHealth['status'] === 'healthy' ? 'success' : ($systemHealth['status'] === 'warning' ? 'warning' : 'danger') }} text-white">
                                <div class="card-body text-center">
                                    <h4 id="systemStatus">{{ strtoupper($systemHealth['status']) }}</h4>
                                    <p class="mb-0">Overall Status</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4 id="runningJobs">{{ $systemHealth['running_jobs_count'] }}</h4>
                                    <p class="mb-0">Running Jobs</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4 id="recentFailures">{{ $systemHealth['recent_failures'] }}</h4>
                                    <p class="mb-0">Recent Failures</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h4 id="stuckJobs">{{ $systemHealth['stuck_jobs'] }}</h4>
                                    <p class="mb-0">Stuck Jobs</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h4 id="memoryUsage">{{ number_format($systemHealth['memory_usage'], 1) }}%</h4>
                                    <p class="mb-0">Memory Usage</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-dark text-white">
                                <div class="card-body text-center">
                                    <h4 id="diskUsage">{{ number_format($systemHealth['disk_usage'], 1) }}%</h4>
                                    <p class="mb-0">Disk Usage</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Automation Metrics -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">24-Hour Performance Metrics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-primary">
                                        <i class="bx bx-play-circle"></i>
                                    </span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Total Executions</small>
                                    <div class="fw-semibold" id="totalExecutions">{{ $automationMetrics['total_executions_24h'] }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-success">
                                        <i class="bx bx-check-circle"></i>
                                    </span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Success Rate</small>
                                    <div class="fw-semibold" id="successRate">{{ number_format($automationMetrics['success_rate_24h'], 1) }}%</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-warning">
                                        <i class="bx bx-time"></i>
                                    </span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Avg Duration</small>
                                    <div class="fw-semibold" id="avgDuration">{{ number_format($automationMetrics['avg_execution_time'], 1) }}s</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center mb-3">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-danger">
                                        <i class="bx bx-x-circle"></i>
                                    </span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Failed Jobs</small>
                                    <div class="fw-semibold" id="failedJobs">{{ $automationMetrics['failed_executions_24h'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Active Alerts</h6>
                </div>
                <div class="card-body">
                    <div id="activeAlerts">
                        <div class="text-center text-muted">
                            <i class="bx bx-loader bx-spin"></i> Loading alerts...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0">Success Rate Trend</h6>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <span id="successRatePeriod">Last 24 Hours</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="updateChart('success_rate', 'hour')">Last Hour</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateChart('success_rate', 'day')">Last 24 Hours</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateChart('success_rate', 'week')">Last Week</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="successRateChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0">Execution Time Trend</h6>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <span id="executionTimePeriod">Last 24 Hours</span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="updateChart('execution_time', 'hour')">Last Hour</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateChart('execution_time', 'day')">Last 24 Hours</a></li>
                            <li><a class="dropdown-item" href="#" onclick="updateChart('execution_time', 'week')">Last Week</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="executionTimeChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Performance Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Job Performance Overview</h6>
                    <button type="button" class="btn btn-sm btn-primary" onclick="showJobDetails()">
                        <i class="bx bx-detail"></i> View Details
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="jobPerformanceTable">
                            <thead>
                                <tr>
                                    <th>Job Name</th>
                                    <th>Executions (7d)</th>
                                    <th>Success Rate</th>
                                    <th>Avg Duration</th>
                                    <th>Max Duration</th>
                                    <th>Avg Memory</th>
                                    <th>Performance Score</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="jobPerformanceBody">
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="bx bx-loader bx-spin"></i> Loading job performance data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Failure Analysis -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Failure Analysis (Last 7 Days)</h6>
                </div>
                <div class="card-body">
                    <div id="failureAnalysis">
                        <div class="text-center text-muted">
                            <i class="bx bx-loader bx-spin"></i> Loading failure analysis...
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Resource Usage Trends</h6>
                </div>
                <div class="card-body">
                    <canvas id="resourceUsageChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Configuration Modal -->
<div class="modal fade" id="alertsConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Automation Alerts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="alertsConfigForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Failure Rate Alert</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="alerts[failure_rate][enabled]" checked>
                                            <label class="form-check-label">Enable Alert</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Threshold (%)</label>
                                        <input type="number" class="form-control" name="alerts[failure_rate][threshold]" value="10" step="0.1" min="0" max="100">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notification Channels</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="alerts[failure_rate][notification_channels][]" value="email" checked>
                                            <label class="form-check-label">Email</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Execution Time Alert</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="alerts[execution_time][enabled]" checked>
                                            <label class="form-check-label">Enable Alert</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Threshold (seconds)</label>
                                        <input type="number" class="form-control" name="alerts[execution_time][threshold]" value="300" min="1">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notification Channels</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="alerts[execution_time][notification_channels][]" value="email" checked>
                                            <label class="form-check-label">Email</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Resource Usage Alert</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="alerts[resource_usage][enabled]" checked>
                                            <label class="form-check-label">Enable Alert</label>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Memory Threshold (%)</label>
                                        <input type="number" class="form-control" name="alerts[resource_usage][threshold]" value="80" min="1" max="100">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notification Channels</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="alerts[resource_usage][notification_channels][]" value="email" checked>
                                            <label class="form-check-label">Email</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Test Alerts</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Alert Type</label>
                                        <select class="form-select" id="testAlertType">
                                            <option value="failure_rate">Failure Rate</option>
                                            <option value="execution_time">Execution Time</option>
                                            <option value="resource_usage">Resource Usage</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Test Value</label>
                                        <input type="number" class="form-control" id="testAlertValue" placeholder="Enter test value">
                                    </div>
                                    <button type="button" class="btn btn-info w-100" onclick="testAlert()">
                                        <i class="bx bx-test-tube"></i> Test Alert
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Alert Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Data Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Monitoring Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="exportForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <select class="form-select" name="format" required>
                            <option value="json">JSON</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time Period</label>
                        <select class="form-select" name="period" required>
                            <option value="day">Last 24 Hours</option>
                            <option value="week">Last Week</option>
                            <option value="month">Last Month</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_logs">
                            <label class="form-check-label">Include Execution Logs</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Job Details Modal -->
<div class="modal fade" id="jobDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Job Performance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="jobDetailsContent">
                    Loading job details...
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let refreshInterval;
let successRateChart, executionTimeChart, resourceUsageChart;

$(document).ready(function() {
    initializeCharts();
    loadMonitoringData();
    startAutoRefresh();
    initializeEventHandlers();
});

function initializeEventHandlers() {
    // Alerts configuration form
    $('#alertsConfigForm').on('submit', function(e) {
        e.preventDefault();
        saveAlertsConfiguration();
    });

    // Export form
    $('#exportForm').on('submit', function(e) {
        e.preventDefault();
        exportMonitoringData();
    });
}

function initializeCharts() {
    // Success Rate Chart
    const successRateCtx = document.getElementById('successRateChart').getContext('2d');
    successRateChart = new Chart(successRateCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Success Rate (%)',
                data: [],
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
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    // Execution Time Chart
    const executionTimeCtx = document.getElementById('executionTimeChart').getContext('2d');
    executionTimeChart = new Chart(executionTimeCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Avg Duration (seconds)',
                data: [],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
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

    // Resource Usage Chart
    const resourceUsageCtx = document.getElementById('resourceUsageChart').getContext('2d');
    resourceUsageChart = new Chart(resourceUsageCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Memory Usage (MB)',
                data: [],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
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

function loadMonitoringData() {
    $.get('{{ route("admin.cron-jobs.monitoring-data") }}', function(response) {
        if (response.success) {
            updateDashboard(response.data);
        }
    }).fail(function() {
        toastr.error('Failed to load monitoring data');
    });
}

function updateDashboard(data) {
    // Update system health metrics
    updateSystemHealth(data.system_health);
    
    // Update automation metrics
    updateAutomationMetrics(data.automation_metrics);
    
    // Update job performance table
    updateJobPerformanceTable(data.job_performance);
    
    // Update active alerts
    updateActiveAlerts(data.alerts);
    
    // Update failure analysis
    updateFailureAnalysis(data.failure_analysis);
    
    // Update charts with trends data
    updateChartsWithTrends(data.trends);
}

function updateSystemHealth(health) {
    $('#systemStatus').text(health.status.toUpperCase());
    $('#runningJobs').text(health.running_jobs_count);
    $('#recentFailures').text(health.recent_failures);
    $('#stuckJobs').text(health.stuck_jobs);
    $('#memoryUsage').text(health.memory_usage.toFixed(1) + '%');
    $('#diskUsage').text(health.disk_usage.toFixed(1) + '%');
    
    // Update status card color
    const statusCard = $('#systemStatus').closest('.card');
    statusCard.removeClass('bg-success bg-warning bg-danger');
    if (health.status === 'healthy') {
        statusCard.addClass('bg-success');
    } else if (health.status === 'warning') {
        statusCard.addClass('bg-warning');
    } else {
        statusCard.addClass('bg-danger');
    }
}

function updateAutomationMetrics(metrics) {
    $('#totalExecutions').text(metrics.total_executions_24h);
    $('#successRate').text(metrics.success_rate_24h.toFixed(1) + '%');
    $('#avgDuration').text(metrics.avg_execution_time.toFixed(1) + 's');
    $('#failedJobs').text(metrics.failed_executions_24h);
}

function updateJobPerformanceTable(jobPerformance) {
    const tbody = $('#jobPerformanceBody');
    tbody.empty();
    
    if (jobPerformance.length === 0) {
        tbody.append('<tr><td colspan="8" class="text-center text-muted">No job performance data available</td></tr>');
        return;
    }
    
    jobPerformance.forEach(job => {
        const scoreClass = job.performance_score >= 80 ? 'success' : (job.performance_score >= 60 ? 'warning' : 'danger');
        
        const row = `
            <tr>
                <td>${job.job_name}</td>
                <td>${job.executions}</td>
                <td>
                    <span class="badge bg-${job.success_rate >= 95 ? 'success' : (job.success_rate >= 90 ? 'warning' : 'danger')}">
                        ${job.success_rate.toFixed(1)}%
                    </span>
                </td>
                <td>${job.avg_duration.toFixed(1)}s</td>
                <td>${job.max_duration}s</td>
                <td>${job.avg_memory_mb.toFixed(1)} MB</td>
                <td>
                    <span class="badge bg-${scoreClass}">${job.performance_score}</span>
                </td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-info" onclick="viewJobHistory('${job.job_name}')">
                            <i class="bx bx-history"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="analyzeJobPerformance('${job.job_name}')">
                            <i class="bx bx-line-chart"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function updateActiveAlerts(alerts) {
    const alertsContainer = $('#activeAlerts');
    alertsContainer.empty();
    
    if (alerts.length === 0) {
        alertsContainer.html('<div class="text-center text-muted"><i class="bx bx-check-circle text-success"></i> No active alerts</div>');
        return;
    }
    
    alerts.forEach(alert => {
        const severityClass = alert.severity === 'critical' ? 'danger' : (alert.severity === 'warning' ? 'warning' : 'info');
        const alertHtml = `
            <div class="alert alert-${severityClass} alert-dismissible">
                <h6 class="alert-heading">${alert.type.replace('_', ' ').toUpperCase()}</h6>
                <p class="mb-0">${alert.message}</p>
                <small class="text-muted">Current: ${alert.current_value} | Threshold: ${alert.threshold}</small>
            </div>
        `;
        alertsContainer.append(alertHtml);
    });
}

function updateFailureAnalysis(analysis) {
    const container = $('#failureAnalysis');
    
    const html = `
        <div class="row">
            <div class="col-12">
                <h6>Total Failures: ${analysis.total_failures}</h6>
                <p class="text-muted">Trend: <span class="badge bg-${analysis.failure_trend === 'increasing' ? 'danger' : (analysis.failure_trend === 'decreasing' ? 'success' : 'secondary')}">${analysis.failure_trend}</span></p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <h6>Top Failing Jobs</h6>
                <ul class="list-unstyled">
                    ${Object.entries(analysis.failures_by_job).slice(0, 5).map(([job, count]) => 
                        `<li>${job}: <span class="badge bg-danger">${count}</span></li>`
                    ).join('')}
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Common Errors</h6>
                <ul class="list-unstyled">
                    ${Object.entries(analysis.common_errors).slice(0, 3).map(([error, count]) => 
                        `<li class="small">${error.substring(0, 30)}...: <span class="badge bg-warning">${count}</span></li>`
                    ).join('')}
                </ul>
            </div>
        </div>
    `;
    
    container.html(html);
}

function updateChartsWithTrends(trends) {
    // Update success rate chart
    const successRateLabels = Object.keys(trends);
    const successRateData = successRateLabels.map(period => trends[period].success_rate);
    
    successRateChart.data.labels = successRateLabels;
    successRateChart.data.datasets[0].data = successRateData;
    successRateChart.update();
    
    // Update execution time chart
    const executionTimeData = successRateLabels.map(period => trends[period].avg_duration);
    
    executionTimeChart.data.labels = successRateLabels;
    executionTimeChart.data.datasets[0].data = executionTimeData;
    executionTimeChart.update();
}

function updateChart(metric, period) {
    $.get('{{ route("admin.cron-jobs.performance-analytics") }}', {
        period: period,
        metric: metric
    }, function(response) {
        if (response.success) {
            const analytics = response.analytics;
            
            if (metric === 'success_rate') {
                const labels = analytics.data.map(item => item.hour);
                const data = analytics.data.map(item => item.success_rate);
                
                successRateChart.data.labels = labels;
                successRateChart.data.datasets[0].data = data;
                successRateChart.update();
                
                $('#successRatePeriod').text(getPeriodLabel(period));
            } else if (metric === 'execution_time') {
                const labels = analytics.data.map(item => item.hour);
                const data = analytics.data.map(item => item.avg_duration);
                
                executionTimeChart.data.labels = labels;
                executionTimeChart.data.datasets[0].data = data;
                executionTimeChart.update();
                
                $('#executionTimePeriod').text(getPeriodLabel(period));
            }
        }
    });
}

function getPeriodLabel(period) {
    switch(period) {
        case 'hour': return 'Last Hour';
        case 'day': return 'Last 24 Hours';
        case 'week': return 'Last Week';
        case 'month': return 'Last Month';
        default: return 'Unknown Period';
    }
}

function refreshDashboard() {
    toastr.info('Refreshing dashboard...');
    loadMonitoringData();
}

function startAutoRefresh() {
    // Refresh every 30 seconds
    refreshInterval = setInterval(loadMonitoringData, 30000);
}

function showAlertsConfig() {
    $('#alertsConfigModal').modal('show');
}

function saveAlertsConfiguration() {
    const formData = new FormData($('#alertsConfigForm')[0]);
    
    // Convert form data to proper structure
    const alerts = {};
    const alertTypes = ['failure_rate', 'execution_time', 'resource_usage'];
    
    alertTypes.forEach(type => {
        alerts[type] = {
            type: type,
            enabled: formData.get(`alerts[${type}][enabled]`) === 'on',
            threshold: parseFloat(formData.get(`alerts[${type}][threshold]`)),
            notification_channels: formData.getAll(`alerts[${type}][notification_channels][]`)
        };
    });

    $.ajax({
        url: '{{ route("admin.cron-jobs.configure-alerts") }}',
        method: 'POST',
        data: {
            alerts: Object.values(alerts),
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#alertsConfigModal').modal('hide');
                loadMonitoringData(); // Refresh to show updated alerts
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to save alerts configuration');
        }
    });
}

function testAlert() {
    const alertType = $('#testAlertType').val();
    const testValue = $('#testAlertValue').val();
    
    if (!testValue) {
        toastr.warning('Please enter a test value');
        return;
    }

    $.post('{{ route("admin.cron-jobs.test-alert") }}', {
        alert_type: alertType,
        test_data: { value: parseFloat(testValue) },
        _token: $('meta[name="csrf-token"]').attr('content')
    }, function(response) {
        if (response.success) {
            const alertClass = response.alert_triggered ? 'warning' : 'success';
            const message = response.alert_triggered ? 'Alert would be triggered!' : 'Alert would not be triggered';
            
            toastr[alertClass === 'warning' ? 'warning' : 'success'](
                `${message}\n${response.alert_message}`
            );
        } else {
            toastr.error(response.message);
        }
    });
}

function triggerHealthCheck() {
    toastr.info('Running comprehensive health check...');
    
    $.post('{{ route("admin.cron-jobs.trigger-health-check") }}', {
        _token: $('meta[name="csrf-token"]').attr('content')
    }, function(response) {
        if (response.success) {
            const healthCheck = response.health_check;
            const scoreClass = healthCheck.overall_score >= 80 ? 'success' : (healthCheck.overall_score >= 60 ? 'warning' : 'danger');
            
            let message = `Health Check Complete!\nOverall Score: ${healthCheck.overall_score}/100`;
            if (healthCheck.issues.length > 0) {
                message += `\nIssues Found: ${healthCheck.issues.length}`;
            }
            
            toastr[scoreClass === 'success' ? 'success' : 'warning'](message);
            loadMonitoringData(); // Refresh dashboard
        } else {
            toastr.error(response.message);
        }
    });
}

function showExportModal() {
    $('#exportModal').modal('show');
}

function exportMonitoringData() {
    const formData = new FormData($('#exportForm')[0]);
    const params = new URLSearchParams(formData);
    
    window.open(`{{ route('admin.cron-jobs.export-monitoring-data') }}?${params}`, '_blank');
    $('#exportModal').modal('hide');
}

function showJobDetails() {
    $('#jobDetailsModal').modal('show');
    
    // Load detailed job performance data
    $('#jobDetailsContent').html('<div class="text-center"><i class="bx bx-loader bx-spin"></i> Loading detailed job performance...</div>');
    
    // This would load more detailed job performance analytics
    setTimeout(() => {
        $('#jobDetailsContent').html(`
            <div class="alert alert-info">
                <i class="bx bx-info-circle"></i>
                Detailed job performance analytics will be implemented in the next phase.
                This will include:
                <ul class="mt-2">
                    <li>Individual job execution timelines</li>
                    <li>Resource usage patterns</li>
                    <li>Failure correlation analysis</li>
                    <li>Performance optimization recommendations</li>
                </ul>
            </div>
        `);
    }, 1000);
}

function viewJobHistory(jobName) {
    // This would open a detailed view of job execution history
    toastr.info(`Loading history for ${jobName}...`);
}

function analyzeJobPerformance(jobName) {
    // This would open performance analysis for specific job
    toastr.info(`Analyzing performance for ${jobName}...`);
}

// Cleanup on page unload
$(window).on('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>
@endpush