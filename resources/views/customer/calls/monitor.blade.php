@extends('layouts.sneat-customer')

@section('title', 'Call Monitor')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-phone-volume"></i> Active Call Monitor</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary" onclick="refreshCalls()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleAutoRefresh()">
                            <i class="fas fa-play" id="autoRefreshIcon"></i> <span id="autoRefreshText">Start Auto-Refresh</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Status Indicators -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4 id="activeCallsCount">{{ $activeCalls->count() }}</h4>
                                    <small>Active Calls</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4 id="totalDuration">{{ $activeCalls->sum('duration') }}s</h4>
                                    <small>Total Duration</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4 id="totalCost">${{ number_format($activeCalls->sum('cost'), 4) }}</h4>
                                    <small>Total Cost</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h4 id="lastUpdate">{{ now()->format('H:i:s') }}</h4>
                                    <small>Last Update</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Calls List -->
                    <div id="activeCallsContainer">
                        @if($activeCalls->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-striped" id="activeCallsTable">
                                    <thead>
                                        <tr>
                                            <th>Call ID</th>
                                            <th>Destination</th>
                                            <th>Caller ID</th>
                                            <th>Start Time</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                            <th>Current Cost</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($activeCalls as $call)
                                        <tr data-call-id="{{ $call->call_id }}" class="call-row">
                                            <td>
                                                <code>{{ $call->call_id }}</code>
                                            </td>
                                            <td>
                                                <strong>{{ $call->destination }}</strong>
                                            </td>
                                            <td>{{ $call->caller_id }}</td>
                                            <td>
                                                <div>{{ $call->start_time->format('H:i:s') }}</div>
                                                <small class="text-muted">{{ $call->start_time->format('M d') }}</small>
                                            </td>
                                            <td>
                                                <span class="call-duration fw-bold" data-start="{{ $call->start_time->timestamp }}">
                                                    {{ $call->getFormattedDuration() }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge call-status bg-{{ 
                                                    $call->status === 'in_progress' ? 'success' : 
                                                    ($call->status === 'ringing' ? 'warning' : 'info') 
                                                }}">
                                                    {{ ucfirst(str_replace('_', ' ', $call->status)) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="call-cost text-success fw-bold">
                                                    ${{ number_format($call->cost, 4) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info" 
                                                            onclick="getCallDetails('{{ $call->call_id }}')"
                                                            title="Get Details">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="hangupCall('{{ $call->call_id }}')"
                                                            title="Hang Up">
                                                        <i class="fas fa-phone-slash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted py-5" id="noActiveCallsMessage">
                                <i class="fas fa-phone-slash fa-4x mb-3"></i>
                                <h5>No Active Calls</h5>
                                <p>All calls have been completed or terminated.</p>
                                <a href="{{ route('customer.calls.make') }}" class="btn btn-primary">
                                    <i class="fas fa-phone"></i> Make a Call
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Call Details Modal -->
<div class="modal fade" id="callDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Call Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="callDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="modalHangupBtn" style="display: none;">
                    <i class="fas fa-phone-slash"></i> Hang Up Call
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let autoRefreshInterval = null;
let isAutoRefreshEnabled = false;

function refreshCalls() {
    const refreshBtn = document.querySelector('button[onclick="refreshCalls()"]');
    const icon = refreshBtn.querySelector('i');
    
    // Add spinning animation
    icon.classList.add('fa-spin');
    
    fetch('{{ route("customer.calls.active") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCallsDisplay(data.active_calls);
                updateStatistics(data.active_calls);
                updateLastUpdateTime();
                showToast('Calls refreshed successfully', 'success');
            } else {
                showToast('Failed to refresh calls: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error refreshing calls', 'error');
        })
        .finally(() => {
            // Remove spinning animation
            icon.classList.remove('fa-spin');
        });
}

function updateCallsDisplay(calls) {
    const container = document.getElementById('activeCallsContainer');
    
    if (calls.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5" id="noActiveCallsMessage">
                <i class="fas fa-phone-slash fa-4x mb-3"></i>
                <h5>No Active Calls</h5>
                <p>All calls have been completed or terminated.</p>
                <a href="{{ route('customer.calls.make') }}" class="btn btn-primary">
                    <i class="fas fa-phone"></i> Make a Call
                </a>
            </div>
        `;
        return;
    }
    
    const tableHtml = `
        <div class="table-responsive">
            <table class="table table-striped" id="activeCallsTable">
                <thead>
                    <tr>
                        <th>Call ID</th>
                        <th>Destination</th>
                        <th>Caller ID</th>
                        <th>Start Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Current Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${calls.map(call => `
                        <tr data-call-id="${call.call_id}" class="call-row">
                            <td><code>${call.call_id}</code></td>
                            <td><strong>${call.destination}</strong></td>
                            <td>${call.caller_id}</td>
                            <td>
                                <div>${new Date(call.start_time).toLocaleTimeString()}</div>
                                <small class="text-muted">${new Date(call.start_time).toLocaleDateString()}</small>
                            </td>
                            <td>
                                <span class="call-duration fw-bold" data-start="${new Date(call.start_time).getTime() / 1000}">
                                    ${call.formatted_duration}
                                </span>
                            </td>
                            <td>
                                <span class="badge call-status bg-${
                                    call.status === 'in_progress' ? 'success' : 
                                    (call.status === 'ringing' ? 'warning' : 'info')
                                }">
                                    ${call.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                </span>
                            </td>
                            <td>
                                <span class="call-cost text-success fw-bold">
                                    $${parseFloat(call.cost).toFixed(4)}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-info" 
                                            onclick="getCallDetails('${call.call_id}')"
                                            title="Get Details">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="hangupCall('${call.call_id}')"
                                            title="Hang Up">
                                        <i class="fas fa-phone-slash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

function updateStatistics(calls) {
    document.getElementById('activeCallsCount').textContent = calls.length;
    
    const totalDuration = calls.reduce((sum, call) => sum + call.duration, 0);
    document.getElementById('totalDuration').textContent = totalDuration + 's';
    
    const totalCost = calls.reduce((sum, call) => sum + parseFloat(call.cost), 0);
    document.getElementById('totalCost').textContent = '$' + totalCost.toFixed(4);
}

function updateLastUpdateTime() {
    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
}

function toggleAutoRefresh() {
    const btn = document.querySelector('button[onclick="toggleAutoRefresh()"]');
    const icon = document.getElementById('autoRefreshIcon');
    const text = document.getElementById('autoRefreshText');
    
    if (isAutoRefreshEnabled) {
        // Stop auto-refresh
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        isAutoRefreshEnabled = false;
        
        icon.className = 'fas fa-play';
        text.textContent = 'Start Auto-Refresh';
        btn.className = 'btn btn-outline-secondary';
        
        showToast('Auto-refresh stopped', 'info');
    } else {
        // Start auto-refresh
        autoRefreshInterval = setInterval(refreshCalls, 10000); // Every 10 seconds
        isAutoRefreshEnabled = true;
        
        icon.className = 'fas fa-pause';
        text.textContent = 'Stop Auto-Refresh';
        btn.className = 'btn btn-outline-warning';
        
        showToast('Auto-refresh started (10s interval)', 'success');
    }
}

function getCallDetails(callId) {
    const modal = new bootstrap.Modal(document.getElementById('callDetailsModal'));
    const content = document.getElementById('callDetailsContent');
    const hangupBtn = document.getElementById('modalHangupBtn');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    fetch(`{{ route("customer.calls.status", ":callId") }}`.replace(':callId', callId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const call = data.call_record;
                const status = data.status;
                
                content.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Call Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Call ID:</strong></td>
                                    <td><code>${call.id}</code></td>
                                </tr>
                                <tr>
                                    <td><strong>Destination:</strong></td>
                                    <td>${call.destination}</td>
                                </tr>
                                <tr>
                                    <td><strong>Start Time:</strong></td>
                                    <td>${new Date(call.start_time).toLocaleString()}</td>
                                </tr>
                                <tr>
                                    <td><strong>Duration:</strong></td>
                                    <td>${call.formatted_duration}</td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-${call.status === 'in_progress' ? 'success' : 'warning'}">
                                            ${call.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Current Cost:</strong></td>
                                    <td class="text-success fw-bold">$${parseFloat(call.cost).toFixed(4)}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>FreePBX Status</h6>
                            <pre class="bg-light p-2 rounded small">${JSON.stringify(status, null, 2)}</pre>
                        </div>
                    </div>
                `;
                
                // Show hangup button if call is active
                if (['initiated', 'ringing', 'answered', 'in_progress'].includes(call.status)) {
                    hangupBtn.style.display = 'inline-block';
                    hangupBtn.onclick = () => {
                        modal.hide();
                        hangupCall(callId);
                    };
                } else {
                    hangupBtn.style.display = 'none';
                }
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Failed to load call details: ${data.message}
                    </div>
                `;
                hangupBtn.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Error loading call details.
                </div>
            `;
            hangupBtn.style.display = 'none';
        });
}

function hangupCall(callId) {
    if (confirm('Are you sure you want to hang up this call?')) {
        fetch(`{{ route("customer.calls.hangup", ":callId") }}`.replace(':callId', callId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Call terminated successfully', 'success');
                refreshCalls();
            } else {
                showToast('Failed to hang up call: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error hanging up call', 'error');
        });
    }
}

// Update call durations in real-time
setInterval(() => {
    document.querySelectorAll('.call-duration[data-start]').forEach(element => {
        const startTime = parseInt(element.dataset.start);
        const elapsed = Math.floor(Date.now() / 1000) - startTime;
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        
        element.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    });
}, 1000);

function showToast(message, type = 'info') {
    // Use the toast function from the layout
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
});
</script>
@endpush