@extends('layouts.sneat-admin')

@section('title', 'Cron Job Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">System /</span> Cron Job Management
    </h4>

    <!-- System Health Alert -->
    <div id="health-alert" class="alert alert-info d-none" role="alert">
        <h6 class="alert-heading">System Health Status</h6>
        <div id="health-content"></div>
    </div>

    <!-- Real-time Status Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-play-circle text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <div class="dropdown">
                            <button class="btn p-0" type="button" id="runningJobsCard" data-bs-toggle="dropdown">
                                <i class="bx bx-dots-vertical-rounded"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="javascript:void(0);" onclick="refreshStatus()">
                                    <i class="bx bx-refresh me-1"></i> Refresh
                                </a>
                            </div>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">Running Jobs</span>
                    <h3 class="card-title mb-2" id="running-jobs-count">{{ count($runningJobs) }}</h3>
                    <small class="text-muted" id="running-jobs-status">
                        @if(count($runningJobs) > 0)
                            {{ count($runningJobs) }} job{{ count($runningJobs) > 1 ? 's' : '' }} currently executing
                        @else
                            No jobs currently running
                        @endif
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">Success Rate (24h)</span>
                    <h3 class="card-title mb-2" id="success-rate">
                        @php
                            $totalExecs = collect($recentStats)->sum('total_executions');
                            $successExecs = collect($recentStats)->sum('successful_executions');
                            $rate = $totalExecs > 0 ? round(($successExecs / $totalExecs) * 100, 1) : 0;
                        @endphp
                        {{ $rate }}%
                    </h3>
                    <small class="text-muted">{{ $successExecs }}/{{ $totalExecs }} successful</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-health text-{{ $systemHealth['overall_status'] === 'healthy' ? 'success' : ($systemHealth['overall_status'] === 'warning' ? 'warning' : 'danger') }}" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">System Health</span>
                    <h3 class="card-title mb-2 text-{{ $systemHealth['overall_status'] === 'healthy' ? 'success' : ($systemHealth['overall_status'] === 'warning' ? 'warning' : 'danger') }}" id="system-health">
                        {{ ucfirst($systemHealth['overall_status']) }}
                    </h3>
                    <small class="text-muted" id="health-summary">
                        @if(count($systemHealth['alerts']) > 0)
                            {{ count($systemHealth['alerts']) }} alert{{ count($systemHealth['alerts']) > 1 ? 's' : '' }}
                        @else
                            All systems operational
                        @endif
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-time text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">Last Update</span>
                    <h3 class="card-title mb-2" id="last-update">Just now</h3>
                    <small class="text-muted">Auto-refresh every 30s</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="nav-align-top mb-4">
        <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#running-jobs" aria-controls="running-jobs" aria-selected="true">
                    <i class="tf-icons bx bx-play-circle"></i> Running Jobs
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#job-history" aria-controls="job-history" aria-selected="false">
                    <i class="tf-icons bx bx-history"></i> Job History
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#statistics" aria-controls="statistics" aria-selected="false">
                    <i class="tf-icons bx bx-bar-chart"></i> Statistics
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#maintenance" aria-controls="maintenance" aria-selected="false">
                    <i class="tf-icons bx bx-cog"></i> Maintenance
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Running Jobs Tab -->
            <div class="tab-pane fade show active" id="running-jobs" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Currently Running Jobs</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshStatus()">
                            <i class="bx bx-refresh"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="running-jobs-table">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job History Tab -->
            <div class="tab-pane fade" id="job-history" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Job Execution History</h5>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <select class="form-select" id="history-job-filter">
                                    <option value="">All Jobs</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="history-limit">
                                    <option value="50">50 records</option>
                                    <option value="100">100 records</option>
                                    <option value="200">200 records</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary" onclick="loadJobHistory()">
                                    <i class="bx bx-search"></i> Load History
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="job-history-table">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Tab -->
            <div class="tab-pane fade" id="statistics" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Job Statistics</h5>
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <select class="form-select" id="stats-job-filter">
                                    <option value="">All Jobs</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="stats-days">
                                    <option value="1">Last 24 hours</option>
                                    <option value="7" selected>Last 7 days</option>
                                    <option value="30">Last 30 days</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary" onclick="loadStatistics()">
                                    <i class="bx bx-bar-chart"></i> Load Statistics
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="statistics-table">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance Tab -->
            <div class="tab-pane fade" id="maintenance" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">System Maintenance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="card-title">Clean Up Old Records</h6>
                                        <p class="card-text">Remove old job execution records to free up database space.</p>
                                        <div class="mb-3">
                                            <label class="form-label">Keep records for (days):</label>
                                            <input type="number" class="form-control" id="cleanup-days" value="30" min="7" max="90">
                                        </div>
                                        <button type="button" class="btn btn-warning" onclick="cleanupRecords()">
                                            <i class="bx bx-trash"></i> Clean Up Records
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="card-title">Kill Stuck Jobs</h6>
                                        <p class="card-text">Terminate jobs that have been running for too long.</p>
                                        <div class="mb-3">
                                            <label class="form-label">Maximum runtime (minutes):</label>
                                            <input type="number" class="form-control" id="max-runtime" value="60" min="5" max="1440">
                                        </div>
                                        <button type="button" class="btn btn-danger" onclick="killStuckJobs()">
                                            <i class="bx bx-stop-circle"></i> Kill Stuck Jobs
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Job Details Modal -->
<div class="modal fade" id="jobDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Job Execution Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="job-details-content">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let autoRefreshInterval;
let lastUpdateTime = new Date();

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadJobNames();
    refreshStatus();
    startAutoRefresh();
});

// Auto-refresh every 30 seconds
function startAutoRefresh() {
    autoRefreshInterval = setInterval(refreshStatus, 30000);
}

// Load available job names for filters
async function loadJobNames() {
    try {
        const response = await fetch('/admin/cron-jobs/job-names');
        const data = await response.json();
        
        const historyFilter = document.getElementById('history-job-filter');
        const statsFilter = document.getElementById('stats-job-filter');
        
        // Clear existing options (except "All Jobs")
        historyFilter.innerHTML = '<option value="">All Jobs</option>';
        statsFilter.innerHTML = '<option value="">All Jobs</option>';
        
        data.job_names.forEach(jobName => {
            historyFilter.innerHTML += `<option value="${jobName}">${jobName}</option>`;
            statsFilter.innerHTML += `<option value="${jobName}">${jobName}</option>`;
        });
    } catch (error) {
        console.error('Failed to load job names:', error);
    }
}

// Refresh status and running jobs
async function refreshStatus() {
    try {
        const response = await fetch('/admin/cron-jobs/status');
        const data = await response.json();
        
        updateStatusCards(data);
        updateRunningJobsTable(data.running_jobs);
        updateSystemHealth(data.system_health);
        
        lastUpdateTime = new Date();
        document.getElementById('last-update').textContent = 'Just now';
        
    } catch (error) {
        console.error('Failed to refresh status:', error);
        showAlert('Failed to refresh status', 'danger');
    }
}

// Update status cards
function updateStatusCards(data) {
    document.getElementById('running-jobs-count').textContent = data.running_jobs.length;
    
    const statusText = data.running_jobs.length > 0 
        ? `${data.running_jobs.length} job${data.running_jobs.length > 1 ? 's' : ''} currently executing`
        : 'No jobs currently running';
    document.getElementById('running-jobs-status').textContent = statusText;
}

// Update running jobs table
function updateRunningJobsTable(runningJobs) {
    const container = document.getElementById('running-jobs-table');
    
    if (runningJobs.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No jobs currently running</div>';
        return;
    }
    
    let tableHtml = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Job Name</th>
                        <th>Started At</th>
                        <th>Runtime</th>
                        <th>Memory (MB)</th>
                        <th>PID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    runningJobs.forEach(job => {
        const runtimeClass = job.runtime_seconds > 3600 ? 'text-warning' : '';
        const memoryText = job.memory_mb ? job.memory_mb.toFixed(2) : 'N/A';
        
        tableHtml += `
            <tr>
                <td><strong>${job.job_name}</strong></td>
                <td>${job.started_at}</td>
                <td class="${runtimeClass}">${job.runtime}</td>
                <td>${memoryText}</td>
                <td>${job.pid || 'N/A'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="showJobDetails('${job.execution_id}')">
                        <i class="bx bx-info-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="killJob('${job.execution_id}')">
                        <i class="bx bx-stop-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableHtml += '</tbody></table></div>';
    container.innerHTML = tableHtml;
}

// Update system health display
function updateSystemHealth(health) {
    const healthElement = document.getElementById('system-health');
    const summaryElement = document.getElementById('health-summary');
    const alertElement = document.getElementById('health-alert');
    const alertContent = document.getElementById('health-content');
    
    // Update health status
    healthElement.className = `card-title mb-2 text-${health.overall_status === 'healthy' ? 'success' : (health.overall_status === 'warning' ? 'warning' : 'danger')}`;
    healthElement.textContent = health.overall_status.charAt(0).toUpperCase() + health.overall_status.slice(1);
    
    // Update summary
    if (health.alerts.length > 0) {
        summaryElement.textContent = `${health.alerts.length} alert${health.alerts.length > 1 ? 's' : ''}`;
        
        // Show alert box
        alertElement.className = `alert alert-${health.overall_status === 'warning' ? 'warning' : 'danger'}`;
        alertContent.innerHTML = health.alerts.map(alert => `<div>• ${alert}</div>`).join('');
        alertElement.classList.remove('d-none');
    } else {
        summaryElement.textContent = 'All systems operational';
        alertElement.classList.add('d-none');
    }
}

// Load job history
async function loadJobHistory() {
    const jobName = document.getElementById('history-job-filter').value;
    const limit = document.getElementById('history-limit').value;
    
    try {
        const params = new URLSearchParams();
        if (jobName) params.append('job_name', jobName);
        params.append('limit', limit);
        
        const response = await fetch(`/admin/cron-jobs/history?${params}`);
        const data = await response.json();
        
        displayJobHistory(data.history);
    } catch (error) {
        console.error('Failed to load job history:', error);
        showAlert('Failed to load job history', 'danger');
    }
}

// Display job history table
function displayJobHistory(history) {
    const container = document.getElementById('job-history-table');
    
    if (history.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No job history found</div>';
        return;
    }
    
    let tableHtml = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Job Name</th>
                        <th>Status</th>
                        <th>Started At</th>
                        <th>Duration</th>
                        <th>Memory Peak (MB)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    history.forEach(job => {
        const statusBadge = getStatusBadge(job.status);
        const duration = job.duration_seconds ? `${job.duration_seconds}s` : 'N/A';
        const memoryPeak = job.memory_peak_mb ? job.memory_peak_mb.toFixed(2) : 'N/A';
        
        tableHtml += `
            <tr>
                <td><strong>${job.job_name}</strong></td>
                <td>${statusBadge}</td>
                <td>${job.started_at}</td>
                <td>${duration}</td>
                <td>${memoryPeak}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="showJobDetails('${job.execution_id}')">
                        <i class="bx bx-info-circle"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tableHtml += '</tbody></table></div>';
    container.innerHTML = tableHtml;
}

// Load statistics
async function loadStatistics() {
    const jobName = document.getElementById('stats-job-filter').value;
    const days = document.getElementById('stats-days').value;
    
    try {
        const params = new URLSearchParams();
        if (jobName) params.append('job_name', jobName);
        params.append('days', days);
        
        const response = await fetch(`/admin/cron-jobs/statistics?${params}`);
        const data = await response.json();
        
        displayStatistics(data.statistics);
    } catch (error) {
        console.error('Failed to load statistics:', error);
        showAlert('Failed to load statistics', 'danger');
    }
}

// Display statistics table
function displayStatistics(statistics) {
    const container = document.getElementById('statistics-table');
    
    if (statistics.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No statistics available</div>';
        return;
    }
    
    let tableHtml = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Job Name</th>
                        <th>Total Executions</th>
                        <th>Success Rate</th>
                        <th>Avg Duration</th>
                        <th>Max Duration</th>
                        <th>Avg Memory (MB)</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    statistics.forEach(stat => {
        const successRateClass = stat.success_rate >= 95 ? 'text-success' : (stat.success_rate >= 80 ? 'text-warning' : 'text-danger');
        const avgDuration = stat.avg_duration ? `${stat.avg_duration}s` : 'N/A';
        const maxDuration = stat.max_duration ? `${stat.max_duration}s` : 'N/A';
        const avgMemory = stat.avg_memory_mb ? stat.avg_memory_mb.toFixed(2) : 'N/A';
        
        tableHtml += `
            <tr>
                <td><strong>${stat.job_name}</strong></td>
                <td>${stat.total_executions}</td>
                <td class="${successRateClass}">${stat.success_rate}%</td>
                <td>${avgDuration}</td>
                <td>${maxDuration}</td>
                <td>${avgMemory}</td>
            </tr>
        `;
    });
    
    tableHtml += '</tbody></table></div>';
    container.innerHTML = tableHtml;
}

// Show job details modal
async function showJobDetails(executionId) {
    try {
        const response = await fetch(`/admin/cron-jobs/job-details/${executionId}`);
        const data = await response.json();
        
        if (data.error) {
            showAlert(data.error, 'danger');
            return;
        }
        
        const job = data.job;
        const modalContent = document.getElementById('job-details-content');
        
        modalContent.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Basic Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Execution ID:</strong></td><td>${job.execution_id}</td></tr>
                        <tr><td><strong>Job Name:</strong></td><td>${job.job_name}</td></tr>
                        <tr><td><strong>Status:</strong></td><td>${getStatusBadge(job.status)}</td></tr>
                        <tr><td><strong>Started At:</strong></td><td>${job.started_at}</td></tr>
                        <tr><td><strong>Completed At:</strong></td><td>${job.completed_at || 'N/A'}</td></tr>
                        <tr><td><strong>Duration:</strong></td><td>${job.duration_seconds ? job.duration_seconds + 's' : 'N/A'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Resource Usage</h6>
                    <table class="table table-sm">
                        <tr><td><strong>PID:</strong></td><td>${job.pid || 'N/A'}</td></tr>
                        <tr><td><strong>Memory Start:</strong></td><td>${job.memory_start ? (job.memory_start / 1024 / 1024).toFixed(2) + ' MB' : 'N/A'}</td></tr>
                        <tr><td><strong>Memory Peak:</strong></td><td>${job.memory_peak ? (job.memory_peak / 1024 / 1024).toFixed(2) + ' MB' : 'N/A'}</td></tr>
                        <tr><td><strong>Memory Usage:</strong></td><td>${job.memory_usage_mb ? job.memory_usage_mb + ' MB' : 'N/A'}</td></tr>
                    </table>
                </div>
            </div>
            
            ${job.metadata ? `
                <div class="mt-3">
                    <h6>Metadata</h6>
                    <pre class="bg-light p-2 rounded"><code>${JSON.stringify(job.metadata, null, 2)}</code></pre>
                </div>
            ` : ''}
            
            ${job.result ? `
                <div class="mt-3">
                    <h6>Result</h6>
                    <pre class="bg-light p-2 rounded"><code>${JSON.stringify(job.result, null, 2)}</code></pre>
                </div>
            ` : ''}
        `;
        
        new bootstrap.Modal(document.getElementById('jobDetailsModal')).show();
    } catch (error) {
        console.error('Failed to load job details:', error);
        showAlert('Failed to load job details', 'danger');
    }
}

// Kill a job
async function killJob(executionId) {
    if (!confirm('Are you sure you want to kill this job?')) {
        return;
    }
    
    try {
        const response = await fetch('/admin/cron-jobs/kill-job', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ execution_id: executionId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
            refreshStatus();
        } else {
            showAlert(data.message || 'Failed to kill job', 'warning');
        }
    } catch (error) {
        console.error('Failed to kill job:', error);
        showAlert('Failed to kill job', 'danger');
    }
}

// Clean up old records
async function cleanupRecords() {
    const days = document.getElementById('cleanup-days').value;
    
    if (!confirm(`Are you sure you want to delete job records older than ${days} days?`)) {
        return;
    }
    
    try {
        const response = await fetch('/admin/cron-jobs/cleanup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ days: parseInt(days) })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(data.message, 'success');
        } else {
            showAlert(data.error || 'Failed to cleanup records', 'danger');
        }
    } catch (error) {
        console.error('Failed to cleanup records:', error);
        showAlert('Failed to cleanup records', 'danger');
    }
}

// Kill stuck jobs
async function killStuckJobs() {
    const maxRuntime = document.getElementById('max-runtime').value;
    
    if (!confirm(`Are you sure you want to kill jobs running longer than ${maxRuntime} minutes?`)) {
        return;
    }
    
    try {
        // This would need to be implemented as a separate endpoint
        showAlert('Feature not yet implemented', 'info');
    } catch (error) {
        console.error('Failed to kill stuck jobs:', error);
        showAlert('Failed to kill stuck jobs', 'danger');
    }
}

// Utility functions
function getStatusBadge(status) {
    const badges = {
        'completed': '<span class="badge bg-success">Completed</span>',
        'failed': '<span class="badge bg-danger">Failed</span>',
        'running': '<span class="badge bg-primary">Running</span>'
    };
    return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
}

function showAlert(message, type) {
    // Create and show a temporary alert
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container-xxl');
    container.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = container.querySelector('.alert');
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Load initial data when tabs are activated
document.addEventListener('shown.bs.tab', function (event) {
    const targetId = event.target.getAttribute('data-bs-target');
    
    if (targetId === '#job-history') {
        loadJobHistory();
    } else if (targetId === '#statistics') {
        loadStatistics();
    }
});
</script>
@endsection