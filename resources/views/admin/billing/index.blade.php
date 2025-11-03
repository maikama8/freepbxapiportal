@extends('layouts.sneat-admin')

@section('title', 'Advanced Billing Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Admin /</span> Advanced Billing Management
    </h4>

    <!-- Billing Overview Cards -->
    <div class="row">
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-dollar-circle bx-sm text-primary"></i>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">Today's Revenue</span>
                    <h3 class="card-title mb-2">${{ number_format($stats['total_revenue_today'] ?? 0, 2) }}</h3>
                    <small class="text-success fw-semibold">Real-time billing</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-phone-call bx-sm text-info"></i>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">Total Calls Today</span>
                    <h3 class="card-title mb-2">{{ $stats['total_calls_today'] ?? 0 }}</h3>
                    <small class="text-info fw-semibold">ASTPP billing</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-time bx-sm text-warning"></i>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">Avg Call Duration</span>
                    <h3 class="card-title mb-2">{{ gmdate('i:s', $stats['average_call_duration'] ?? 0) }}</h3>
                    <small class="text-warning fw-semibold">Minutes:Seconds</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="card-title d-flex align-items-start justify-content-between">
                        <div class="avatar flex-shrink-0">
                            <i class="bx bx-cog bx-sm text-success"></i>
                        </div>
                    </div>
                    <span class="fw-semibold d-block mb-1">Billing Increment</span>
                    <h3 class="card-title mb-2">{{ $config['default_increment'] }}</h3>
                    <small class="text-success fw-semibold">Default setting</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Billing Configuration Panel -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">ASTPP-Style Billing Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.billing.increments') }}" class="btn btn-primary w-100">
                                <i class="bx bx-cog me-2"></i>
                                Billing Increments
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.billing.reports') }}" class="btn btn-outline-info w-100">
                                <i class="bx bx-bar-chart me-2"></i>
                                Billing Reports
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <button class="btn btn-outline-warning w-100" onclick="processPendingBilling()">
                                <i class="bx bx-refresh me-2"></i>
                                Process Pending
                            </button>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.rates.index') }}" class="btn btn-outline-success w-100">
                                <i class="bx bx-list-ul me-2"></i>
                                Manage Rates
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Billing Status Overview -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Billing Status Distribution</h5>
                    <small class="text-muted">Current status breakdown</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($stats['calls_by_billing_status'] ?? [] as $status => $count)
                        <div class="col-md-4 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="avatar flex-shrink-0 me-3">
                                    @switch($status)
                                        @case('paid')
                                            <span class="badge bg-success">{{ $count }}</span>
                                            @break
                                        @case('unpaid')
                                            <span class="badge bg-warning">{{ $count }}</span>
                                            @break
                                        @case('pending')
                                            <span class="badge bg-info">{{ $count }}</span>
                                            @break
                                        @case('error')
                                            <span class="badge bg-danger">{{ $count }}</span>
                                            @break
                                        @case('terminated')
                                            <span class="badge bg-secondary">{{ $count }}</span>
                                            @break
                                        @default
                                            <span class="badge bg-light text-dark">{{ $count }}</span>
                                    @endswitch
                                </div>
                                <div>
                                    <h6 class="mb-0">{{ ucfirst($status) }}</h6>
                                    <small class="text-muted">{{ $count }} calls</small>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">System Status</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Real-time Billing</span>
                        <span class="badge bg-success">{{ ($config['billing_settings']['billing.enable_real_time'] ?? true) ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Auto Termination</span>
                        <span class="badge bg-success">{{ ($config['billing_settings']['billing.auto_terminate_on_zero_balance'] ?? true) ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span>Grace Period</span>
                        <span class="badge bg-info">{{ $config['billing_settings']['billing.grace_period_seconds'] ?? 30 }}s</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Active Rates</span>
                        <span class="badge bg-primary">{{ ($config['country_specific_rates'] + $config['call_specific_rates']) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Destinations -->
    @if(isset($stats['top_destinations']) && $stats['top_destinations']->count() > 0)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Top Destinations (by call volume)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Destination</th>
                                    <th>Call Count</th>
                                    <th>Total Revenue</th>
                                    <th>Avg Cost/Call</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats['top_destinations'] as $destination)
                                <tr>
                                    <td><strong>{{ $destination->destination }}</strong></td>
                                    <td>{{ $destination->call_count }}</td>
                                    <td>${{ number_format($destination->total_cost, 2) }}</td>
                                    <td>${{ number_format($destination->total_cost / $destination->call_count, 4) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Processing Modal -->
<div class="modal fade" id="processingModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Processing pending billing...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function processPendingBilling() {
    const modal = new bootstrap.Modal(document.getElementById('processingModal'));
    modal.show();
    
    fetch('{{ route("admin.billing.process-pending") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        modal.hide();
        if (data.success) {
            alert(`Successfully processed billing for ${data.processed_count} calls`);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        modal.hide();
        console.error('Error:', error);
        alert('An error occurred while processing billing');
    });
}

// Auto-refresh stats every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>
@endpush