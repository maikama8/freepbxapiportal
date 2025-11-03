@extends('layouts.sneat-customer')

@section('title', 'Make Call')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Call Initiation Form -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-phone"></i> Make a Call</h5>
                </div>
                <div class="card-body">
                    <form id="callForm">
                        @csrf
                        <div class="mb-3">
                            <label for="destination" class="form-label">Destination Number</label>
                            <input type="tel" class="form-control form-control-lg" id="destination" 
                                   name="destination" placeholder="+1234567890" required>
                            <div class="form-text">Enter the phone number you want to call (with country code)</div>
                            <div id="destinationError" class="invalid-feedback"></div>
                        </div>

                        <div class="mb-3">
                            <label for="caller_id" class="form-label">Caller ID (Optional)</label>
                            <input type="tel" class="form-control" id="caller_id" name="caller_id" 
                                   value="{{ auth()->user()->phone }}" placeholder="Your caller ID">
                            <div class="form-text">The number that will be displayed to the recipient</div>
                        </div>

                        <!-- Rate Information -->
                        <div id="rateInfo" class="alert alert-info d-none">
                            <h6><i class="fas fa-info-circle"></i> Rate Information</h6>
                            <div id="rateDetails"></div>
                        </div>

                        <!-- Balance Check -->
                        <div class="alert alert-warning">
                            <strong>Current Balance:</strong> ${{ number_format(auth()->user()->balance, 2) }}
                            <br><strong>Account Type:</strong> {{ ucfirst(auth()->user()->account_type) }}
                            @if(auth()->user()->isPostpaid())
                                <br><strong>Credit Limit:</strong> ${{ number_format(auth()->user()->credit_limit, 2) }}
                            @endif
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg" id="callButton">
                                <i class="fas fa-phone"></i> <span id="callButtonText">Call Now</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                                <i class="fas fa-eraser"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Dial -->
            @if($recentDestinations->count() > 0)
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-history"></i> Recent Destinations</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($recentDestinations as $destination)
                        <div class="col-md-6 mb-2">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100" 
                                    onclick="quickDial('{{ $destination }}')">
                                <i class="fas fa-phone"></i> {{ $destination }}
                            </button>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Active Calls & Monitoring -->
        <div class="col-md-6">
            <!-- Active Calls -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-phone-volume"></i> Active Calls</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshActiveCalls()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div id="activeCallsList">
                        @if($activeCalls->count() > 0)
                            @foreach($activeCalls as $call)
                            <div class="call-item border rounded p-3 mb-2" data-call-id="{{ $call->call_id }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $call->destination }}</h6>
                                        <small class="text-muted">
                                            Started: {{ $call->start_time->format('H:i:s') }}
                                        </small>
                                        <br>
                                        <span class="badge bg-{{ $call->status === 'in_progress' ? 'success' : 'warning' }}">
                                            {{ ucfirst(str_replace('_', ' ', $call->status)) }}
                                        </span>
                                    </div>
                                    <div class="text-end">
                                        <div class="call-duration fw-bold" data-start="{{ $call->start_time->timestamp }}">
                                            {{ $call->getFormattedDuration() }}
                                        </div>
                                        <div class="call-cost text-muted">
                                            ${{ number_format($call->cost, 4) }}
                                        </div>
                                        <button type="button" class="btn btn-sm btn-danger mt-1" 
                                                onclick="hangupCall('{{ $call->call_id }}')">
                                            <i class="fas fa-phone-slash"></i> Hang Up
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="text-center text-muted py-4" id="noActiveCalls">
                                <i class="fas fa-phone-slash fa-3x mb-3"></i>
                                <p>No active calls</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Call Statistics -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> Today's Statistics</h6>
                </div>
                <div class="card-body">
                    @php
                        $todayCalls = auth()->user()->callRecords()->whereDate('created_at', today());
                        $todayStats = [
                            'total_calls' => $todayCalls->count(),
                            'completed_calls' => $todayCalls->where('status', 'completed')->count(),
                            'total_duration' => $todayCalls->sum('duration'),
                            'total_cost' => $todayCalls->sum('cost')
                        ];
                    @endphp
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-primary">{{ $todayStats['total_calls'] }}</h4>
                                <small class="text-muted">Total Calls</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success">${{ number_format($todayStats['total_cost'], 2) }}</h4>
                            <small class="text-muted">Total Spent</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h5 class="text-info">{{ $todayStats['completed_calls'] }}</h5>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h5 class="text-warning">{{ gmdate('H:i:s', $todayStats['total_duration']) }}</h5>
                            <small class="text-muted">Duration</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Call Progress Modal -->
<div class="modal fade" id="callProgressModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Call in Progress</h5>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <i class="fas fa-phone-volume fa-3x text-success"></i>
                </div>
                <h5 id="modalDestination"></h5>
                <div class="mb-3">
                    <span class="badge bg-success" id="modalStatus">Connecting...</span>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Duration:</strong>
                        <div id="modalDuration" class="h5 text-primary">00:00</div>
                    </div>
                    <div class="col-6">
                        <strong>Cost:</strong>
                        <div id="modalCost" class="h5 text-success">$0.0000</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="modalHangupBtn">
                    <i class="fas fa-phone-slash"></i> Hang Up
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentCallId = null;
let callTimer = null;
let callStartTime = null;

document.getElementById('callForm').addEventListener('submit', function(e) {
    e.preventDefault();
    initiateCall();
});

// Check rate when destination changes
document.getElementById('destination').addEventListener('input', function() {
    const destination = this.value.trim();
    if (destination.length >= 3) {
        checkCallRate(destination);
    } else {
        document.getElementById('rateInfo').classList.add('d-none');
    }
});

function initiateCall() {
    const form = document.getElementById('callForm');
    const formData = new FormData(form);
    const button = document.getElementById('callButton');
    const buttonText = document.getElementById('callButtonText');
    
    // Disable button and show loading
    button.disabled = true;
    buttonText.textContent = 'Connecting...';
    
    fetch('{{ route("customer.calls.initiate") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentCallId = data.call_id;
            showCallProgress(formData.get('destination'), data.call_id);
            refreshActiveCalls();
            showToast('Call initiated successfully', 'success');
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to initiate call', 'error');
    })
    .finally(() => {
        // Re-enable button
        button.disabled = false;
        buttonText.textContent = 'Call Now';
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
                refreshActiveCalls();
                
                // Close modal if it's the current call
                if (currentCallId === callId) {
                    hideCallProgress();
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to hang up call', 'error');
        });
    }
}

function checkCallRate(destination) {
    fetch('{{ route("customer.calls.rate") }}?' + new URLSearchParams({destination: destination}))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const rateInfo = document.getElementById('rateInfo');
                const rateDetails = document.getElementById('rateDetails');
                
                rateDetails.innerHTML = `
                    <strong>Destination:</strong> ${data.rate.destination_name}<br>
                    <strong>Rate:</strong> ${data.rate.formatted_rate}<br>
                    <strong>Minimum Duration:</strong> ${data.rate.minimum_duration} seconds<br>
                    <strong>Billing Increment:</strong> ${data.rate.billing_increment} seconds
                `;
                
                rateInfo.classList.remove('d-none');
            } else {
                document.getElementById('rateInfo').classList.add('d-none');
            }
        })
        .catch(error => {
            console.error('Error checking rate:', error);
        });
}

function refreshActiveCalls() {
    fetch('{{ route("customer.calls.active") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateActiveCallsList(data.active_calls);
            }
        })
        .catch(error => {
            console.error('Error refreshing active calls:', error);
        });
}

function updateActiveCallsList(calls) {
    const container = document.getElementById('activeCallsList');
    
    if (calls.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4" id="noActiveCalls">
                <i class="fas fa-phone-slash fa-3x mb-3"></i>
                <p>No active calls</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = calls.map(call => `
        <div class="call-item border rounded p-3 mb-2" data-call-id="${call.call_id}">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">${call.destination}</h6>
                    <small class="text-muted">
                        Started: ${new Date(call.start_time).toLocaleTimeString()}
                    </small>
                    <br>
                    <span class="badge bg-${call.status === 'in_progress' ? 'success' : 'warning'}">
                        ${call.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                    </span>
                </div>
                <div class="text-end">
                    <div class="call-duration fw-bold" data-start="${new Date(call.start_time).getTime() / 1000}">
                        ${call.formatted_duration}
                    </div>
                    <div class="call-cost text-muted">
                        $${parseFloat(call.cost).toFixed(4)}
                    </div>
                    <button type="button" class="btn btn-sm btn-danger mt-1" 
                            onclick="hangupCall('${call.call_id}')">
                        <i class="fas fa-phone-slash"></i> Hang Up
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function showCallProgress(destination, callId) {
    document.getElementById('modalDestination').textContent = destination;
    document.getElementById('modalHangupBtn').onclick = () => hangupCall(callId);
    
    const modal = new bootstrap.Modal(document.getElementById('callProgressModal'));
    modal.show();
    
    // Start call timer
    callStartTime = Date.now();
    startCallTimer();
}

function hideCallProgress() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('callProgressModal'));
    if (modal) {
        modal.hide();
    }
    
    if (callTimer) {
        clearInterval(callTimer);
        callTimer = null;
    }
    
    currentCallId = null;
}

function startCallTimer() {
    callTimer = setInterval(() => {
        if (callStartTime) {
            const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            
            document.getElementById('modalDuration').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }, 1000);
}

function quickDial(destination) {
    document.getElementById('destination').value = destination;
    checkCallRate(destination);
}

function clearForm() {
    document.getElementById('callForm').reset();
    document.getElementById('rateInfo').classList.add('d-none');
}

// Auto-refresh active calls every 30 seconds
setInterval(refreshActiveCalls, 30000);

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
</script>
@endpush