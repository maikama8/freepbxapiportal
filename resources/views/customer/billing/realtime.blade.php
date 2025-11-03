@extends('layouts.sneat-customer')

@section('title', 'Real-time Billing Monitor')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Billing Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Current Balance</h6>
                            <h4 class="mb-0 text-success" id="current-balance">${{ number_format(auth()->user()->balance, 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-wallet text-success"></i>
                            </span>
                        </div>
                    </div>
                    <small class="text-muted">Available for calls</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Active Call Cost</h6>
                            <h4 class="mb-0 text-warning" id="active-call-cost">$0.00</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-phone text-warning"></i>
                            </span>
                        </div>
                    </div>
                    <small class="text-muted">Current call charges</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Today's Spending</h6>
                            <h4 class="mb-0 text-info" id="today-spending">${{ number_format($todaySpending, 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-trending-up text-info"></i>
                            </span>
                        </div>
                    </div>
                    <small class="text-muted">Total spent today</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title mb-1">Estimated Monthly</h6>
                            <h4 class="mb-0 text-primary" id="monthly-estimate">${{ number_format($monthlyEstimate, 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-calendar text-primary"></i>
                            </span>
                        </div>
                    </div>
                    <small class="text-muted">Based on usage pattern</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Real-time Billing Information -->
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Real-time Call Billing</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshBilling()">
                            <i class="bx bx-refresh"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAutoUpdate()">
                            <i class="bx bx-play" id="auto-update-icon"></i> <span id="auto-update-text">Auto Update</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="active-calls-billing">
                        @if($activeCalls->count() > 0)
                            @foreach($activeCalls as $call)
                                <div class="call-billing-item border rounded p-3 mb-3" data-call-id="{{ $call->call_id }}">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <h6 class="mb-1">{{ $call->destination }}</h6>
                                            <small class="text-muted">
                                                Started: {{ $call->start_time->format('H:i:s') }}
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="call-duration fw-bold text-primary" data-start="{{ $call->start_time->timestamp }}">
                                                {{ $call->getFormattedDuration() }}
                                            </div>
                                            <small class="text-muted">Duration</small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="call-rate fw-bold text-info">
                                                ${{ number_format($call->rate_per_minute ?? 0, 4) }}/min
                                            </div>
                                            <small class="text-muted">Rate</small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="billing-increment text-warning">
                                                {{ $call->billing_increment ?? 60 }}s
                                            </div>
                                            <small class="text-muted">Increment</small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="call-cost fw-bold text-success">
                                                ${{ number_format($call->cost, 4) }}
                                            </div>
                                            <small class="text-muted">Current Cost</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Billing Breakdown -->
                                    <div class="mt-3">
                                        <div class="progress mb-2" style="height: 6px;">
                                            <div class="progress-bar bg-success call-progress" 
                                                 data-increment="{{ $call->billing_increment ?? 60 }}"
                                                 data-start="{{ $call->start_time->timestamp }}"
                                                 style="width: 0%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                Next increment: <span class="next-increment-time">--</span>
                                            </small>
                                            <small class="text-muted">
                                                Next charge: <span class="next-increment-cost">$0.00</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-5">
                                <div class="avatar avatar-xl mx-auto mb-3">
                                    <span class="avatar-initial rounded-circle bg-label-primary">
                                        <i class="bx bx-phone-off bx-lg"></i>
                                    </span>
                                </div>
                                <h5>No Active Calls</h5>
                                <p class="text-muted">Start a call to see real-time billing information</p>
                                <a href="{{ route('customer.calls.make') }}" class="btn btn-primary">
                                    <i class="bx bx-phone"></i> Make a Call
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Alerts & Predictions -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Billing Alerts & Predictions</h6>
                </div>
                <div class="card-body">
                    <!-- Low Balance Alert -->
                    @if(auth()->user()->balance < 10)
                        <div class="alert alert-warning">
                            <i class="bx bx-error-circle"></i>
                            <strong>Low Balance Warning</strong><br>
                            Your balance is running low. Consider adding funds to avoid call interruptions.
                        </div>
                    @endif

                    <!-- Cost Prediction -->
                    <div class="mb-4">
                        <h6 class="mb-3">Cost Prediction</h6>
                        <div id="cost-predictions">
                            @foreach($activeCalls as $call)
                                <div class="prediction-item mb-3" data-call-id="{{ $call->call_id }}">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-bold">{{ $call->destination }}</span>
                                        <span class="badge bg-info">Active</span>
                                    </div>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="prediction-5min">$0.00</div>
                                            <small class="text-muted">5 min</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="prediction-10min">$0.00</div>
                                            <small class="text-muted">10 min</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="prediction-30min">$0.00</div>
                                            <small class="text-muted">30 min</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Balance Alerts -->
                    <div class="mb-4">
                        <h6 class="mb-3">Balance Alerts</h6>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="alert-low-balance" 
                                   {{ auth()->user()->alert_low_balance ? 'checked' : '' }}>
                            <label class="form-check-label" for="alert-low-balance">
                                Low balance alerts
                            </label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="alert-call-cost" 
                                   {{ auth()->user()->alert_call_cost ? 'checked' : '' }}>
                            <label class="form-check-label" for="alert-call-cost">
                                High call cost alerts
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="alert-daily-limit" 
                                   {{ auth()->user()->alert_daily_limit ? 'checked' : '' }}>
                            <label class="form-check-label" for="alert-daily-limit">
                                Daily spending limit alerts
                            </label>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div>
                        <h6 class="mb-3">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="{{ route('customer.payments.add-funds') }}" class="btn btn-success btn-sm">
                                <i class="bx bx-plus"></i> Add Funds
                            </a>
                            <a href="{{ route('customer.call-history') }}" class="btn btn-outline-primary btn-sm">
                                <i class="bx bx-history"></i> View Call History
                            </a>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportBillingData()">
                                <i class="bx bx-download"></i> Export Billing Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Billing Increment Information -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Billing Increment Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3">How Billing Works</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="bx bx-check-circle text-success me-2"></i>
                                    Calls are billed in increments based on destination
                                </li>
                                <li class="mb-2">
                                    <i class="bx bx-check-circle text-success me-2"></i>
                                    Common increments: 1s, 6s, 30s, or 60s
                                </li>
                                <li class="mb-2">
                                    <i class="bx bx-check-circle text-success me-2"></i>
                                    Minimum duration charges may apply
                                </li>
                                <li class="mb-2">
                                    <i class="bx bx-check-circle text-success me-2"></i>
                                    Real-time balance deduction for prepaid accounts
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Current Rate Examples</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Destination</th>
                                            <th>Rate/min</th>
                                            <th>Increment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($sampleRates as $rate)
                                            <tr>
                                                <td>{{ $rate->destination_name }}</td>
                                                <td>${{ number_format($rate->rate_per_minute, 4) }}</td>
                                                <td>{{ $rate->billing_increment }}s</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cost Alert Modal -->
<div class="modal fade" id="costAlertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">High Cost Alert</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="avatar avatar-lg mx-auto mb-3">
                        <span class="avatar-initial rounded-circle bg-label-warning">
                            <i class="bx bx-error-circle bx-lg"></i>
                        </span>
                    </div>
                    <h6 id="alert-message">Call cost is getting high</h6>
                </div>
                <div id="alert-details"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Continue Call</button>
                <button type="button" class="btn btn-warning" id="hangup-high-cost">End Call Now</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let autoUpdateInterval = null;
let isAutoUpdateEnabled = false;
let costAlertThreshold = 5.00; // Alert when call cost exceeds $5

$(document).ready(function() {
    // Start real-time updates
    startRealTimeUpdates();
    
    // Initialize cost predictions
    updateCostPredictions();
    
    // Set up alert preferences
    setupAlertPreferences();
});

function startRealTimeUpdates() {
    // Update call durations and costs every second
    setInterval(updateCallDurations, 1000);
    
    // Update billing information every 5 seconds
    setInterval(updateBillingInfo, 5000);
    
    // Update balance every 30 seconds
    setInterval(updateBalance, 30000);
}

function updateCallDurations() {
    $('.call-duration[data-start]').each(function() {
        const startTime = parseInt($(this).data('start'));
        const elapsed = Math.floor(Date.now() / 1000) - startTime;
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        
        $(this).text(`${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`);
        
        // Update progress bar for billing increment
        const callItem = $(this).closest('.call-billing-item');
        const progressBar = callItem.find('.call-progress');
        const increment = parseInt(progressBar.data('increment'));
        
        if (increment) {
            const progressInIncrement = (elapsed % increment) / increment * 100;
            progressBar.css('width', progressInIncrement + '%');
            
            // Update next increment time
            const nextIncrementIn = increment - (elapsed % increment);
            callItem.find('.next-increment-time').text(nextIncrementIn + 's');
        }
    });
}

function updateBillingInfo() {
    $.ajax({
        url: '{{ route("customer.billing.realtime-data") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                updateActiveCalls(response.active_calls);
                updateTodaySpending(response.today_spending);
                updateCostPredictions();
                
                // Check for cost alerts
                checkCostAlerts(response.active_calls);
            }
        },
        error: function(xhr) {
            console.error('Failed to update billing info:', xhr);
        }
    });
}

function updateActiveCalls(calls) {
    calls.forEach(call => {
        const callItem = $(`.call-billing-item[data-call-id="${call.call_id}"]`);
        if (callItem.length) {
            callItem.find('.call-cost').text('$' + parseFloat(call.cost).toFixed(4));
            
            // Update next increment cost
            const rate = parseFloat(call.rate_per_minute || 0);
            const increment = parseInt(call.billing_increment || 60);
            const nextCost = (rate / 60) * increment;
            callItem.find('.next-increment-cost').text('$' + nextCost.toFixed(4));
        }
    });
    
    // Update total active call cost
    const totalCost = calls.reduce((sum, call) => sum + parseFloat(call.cost), 0);
    $('#active-call-cost').text('$' + totalCost.toFixed(2));
}

function updateBalance() {
    $.ajax({
        url: '{{ route("customer.balance.refresh") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                $('#current-balance').text('$' + response.balance);
            }
        }
    });
}

function updateTodaySpending(spending) {
    $('#today-spending').text('$' + parseFloat(spending).toFixed(2));
}

function updateCostPredictions() {
    $('.prediction-item').each(function() {
        const callId = $(this).data('call-id');
        const callItem = $(`.call-billing-item[data-call-id="${callId}"]`);
        
        if (callItem.length) {
            const rate = parseFloat(callItem.find('.call-rate').text().replace('$', '').replace('/min', ''));
            
            // Calculate predictions for 5, 10, and 30 minutes
            const pred5min = rate * 5;
            const pred10min = rate * 10;
            const pred30min = rate * 30;
            
            $(this).find('.prediction-5min').text('$' + pred5min.toFixed(2));
            $(this).find('.prediction-10min').text('$' + pred10min.toFixed(2));
            $(this).find('.prediction-30min').text('$' + pred30min.toFixed(2));
        }
    });
}

function checkCostAlerts(calls) {
    calls.forEach(call => {
        if (parseFloat(call.cost) > costAlertThreshold) {
            showCostAlert(call);
        }
    });
}

function showCostAlert(call) {
    $('#alert-message').text(`Call to ${call.destination} has exceeded $${costAlertThreshold.toFixed(2)}`);
    $('#alert-details').html(`
        <div class="row text-center">
            <div class="col-6">
                <h6>Current Cost</h6>
                <div class="text-warning">$${parseFloat(call.cost).toFixed(4)}</div>
            </div>
            <div class="col-6">
                <h6>Duration</h6>
                <div class="text-info">${call.formatted_duration}</div>
            </div>
        </div>
    `);
    
    $('#hangup-high-cost').off('click').on('click', function() {
        hangupCall(call.call_id);
        $('#costAlertModal').modal('hide');
    });
    
    $('#costAlertModal').modal('show');
}

function setupAlertPreferences() {
    $('.form-check-input').on('change', function() {
        const setting = $(this).attr('id').replace('alert-', '');
        const enabled = $(this).is(':checked');
        
        $.ajax({
            url: '{{ route("customer.billing.update-alerts") }}',
            method: 'POST',
            data: {
                setting: setting,
                enabled: enabled,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'Alert preference updated');
                }
            }
        });
    });
}

function toggleAutoUpdate() {
    const btn = $('button[onclick="toggleAutoUpdate()"]');
    const icon = $('#auto-update-icon');
    const text = $('#auto-update-text');
    
    if (isAutoUpdateEnabled) {
        clearInterval(autoUpdateInterval);
        isAutoUpdateEnabled = false;
        
        icon.removeClass('bx-pause').addClass('bx-play');
        text.text('Auto Update');
        btn.removeClass('btn-outline-warning').addClass('btn-outline-secondary');
    } else {
        autoUpdateInterval = setInterval(updateBillingInfo, 3000);
        isAutoUpdateEnabled = true;
        
        icon.removeClass('bx-play').addClass('bx-pause');
        text.text('Stop Auto');
        btn.removeClass('btn-outline-secondary').addClass('btn-outline-warning');
    }
}

function refreshBilling() {
    updateBillingInfo();
    showAlert('success', 'Billing information refreshed');
}

function exportBillingData() {
    window.location.href = '{{ route("customer.billing.export") }}';
}

function hangupCall(callId) {
    $.ajax({
        url: `/customer/calls/${callId}/hangup`,
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                showAlert('success', 'Call terminated successfully');
                location.reload();
            }
        }
    });
}

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