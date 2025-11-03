@extends('layouts.sneat-admin')

@section('title', 'Billing Increments Configuration')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Admin /</span> Billing Increments Configuration
    </h4>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Global Billing Configuration -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Global Billing Configuration</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.billing.increments.update') }}">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="default_increment" class="form-label">Default Billing Increment</label>
                            <select class="form-select" id="default_increment" name="default_increment" required>
                                @foreach($config['available_increments'] as $key => $increment)
                                    <option value="{{ $key }}" {{ $config['default_increment'] === $key ? 'selected' : '' }}>
                                        {{ $increment['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">This will be used as default for new rates</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="grace_period" class="form-label">Grace Period (seconds)</label>
                            <input type="number" class="form-control" id="grace_period" 
                                   name="billing.grace_period_seconds" 
                                   value="{{ $config['billing_settings']['billing.grace_period_seconds'] ?? 30 }}"
                                   min="0" max="300">
                            <div class="form-text">Grace period before call termination on insufficient balance</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="enable_real_time" 
                                   name="billing.enable_real_time" value="1"
                                   {{ ($config['billing_settings']['billing.enable_real_time'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="enable_real_time">
                                Enable Real-time Billing
                            </label>
                            <div class="form-text">Calculate costs during active calls</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="auto_terminate" 
                                   name="billing.auto_terminate_on_zero_balance" value="1"
                                   {{ ($config['billing_settings']['billing.auto_terminate_on_zero_balance'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="auto_terminate">
                                Auto-terminate on Zero Balance
                            </label>
                            <div class="form-text">Automatically terminate calls when balance reaches zero</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Update Configuration</button>
            </form>
        </div>
    </div>

    <!-- Billing Test Tool -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Billing Calculator Test</h5>
        </div>
        <div class="card-body">
            <form id="testBillingForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="test_destination" class="form-label">Destination</label>
                            <input type="text" class="form-control" id="test_destination" 
                                   placeholder="e.g., +1234567890" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="test_duration" class="form-label">Duration (seconds)</label>
                            <input type="number" class="form-control" id="test_duration" 
                                   value="60" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-info d-block">Test Calculation</button>
                        </div>
                    </div>
                </div>
            </form>
            <div id="testResult" class="mt-3" style="display: none;"></div>
        </div>
    </div>

    <!-- Call Rates Configuration -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Call Rates Billing Configuration</h5>
            <button class="btn btn-sm btn-outline-primary" onclick="bulkUpdateModal('call_rates')">
                Bulk Update
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCallRates"></th>
                            <th>Destination</th>
                            <th>Rate/Min</th>
                            <th>Min Duration</th>
                            <th>Billing Increment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($callRates as $rate)
                        <tr>
                            <td><input type="checkbox" class="call-rate-checkbox" value="{{ $rate->id }}"></td>
                            <td>
                                <strong>{{ $rate->destination_prefix }}</strong><br>
                                <small class="text-muted">{{ $rate->destination_name }}</small>
                            </td>
                            <td>${{ number_format($rate->rate_per_minute, 6) }}</td>
                            <td>{{ $rate->minimum_duration }}s</td>
                            <td>
                                <span class="badge bg-info">{{ $rate->billing_increment_config ?? '6/6' }}</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editRateIncrement('call_rate', {{ $rate->id }}, '{{ $rate->billing_increment_config ?? '6/6' }}', {{ $rate->minimum_duration }})">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $callRates->links() }}
        </div>
    </div>

    <!-- Country Rates Configuration -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Country Rates Billing Configuration</h5>
            <button class="btn btn-sm btn-outline-primary" onclick="bulkUpdateModal('country_rates')">
                Bulk Update
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCountryRates"></th>
                            <th>Country</th>
                            <th>Prefix</th>
                            <th>Call Rate/Min</th>
                            <th>Min Duration</th>
                            <th>Billing Increment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($countryRates as $rate)
                        <tr>
                            <td><input type="checkbox" class="country-rate-checkbox" value="{{ $rate->id }}"></td>
                            <td>
                                <strong>{{ $rate->country_name }}</strong><br>
                                <small class="text-muted">{{ $rate->country_code }}</small>
                            </td>
                            <td>+{{ $rate->country_prefix }}</td>
                            <td>${{ number_format($rate->call_rate_per_minute, 4) }}</td>
                            <td>{{ $rate->minimum_duration }}s</td>
                            <td>
                                <span class="badge bg-info">{{ $rate->billing_increment_config ?? '6/6' }}</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editRateIncrement('country_rate', {{ $rate->id }}, '{{ $rate->billing_increment_config ?? '6/6' }}', {{ $rate->minimum_duration }})">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $countryRates->links() }}
        </div>
    </div>
</div>

<!-- Edit Rate Modal -->
<div class="modal fade" id="editRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Billing Increment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRateForm">
                <div class="modal-body">
                    <input type="hidden" id="editRateType">
                    <input type="hidden" id="editRateId">
                    
                    <div class="mb-3">
                        <label for="editBillingIncrement" class="form-label">Billing Increment</label>
                        <select class="form-select" id="editBillingIncrement" required>
                            @foreach($config['available_increments'] as $key => $increment)
                                <option value="{{ $key }}">{{ $increment['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editMinimumDuration" class="form-label">Minimum Duration (seconds)</label>
                        <input type="number" class="form-control" id="editMinimumDuration" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Update Billing Increments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkUpdateForm">
                <div class="modal-body">
                    <input type="hidden" id="bulkUpdateType">
                    
                    <div class="mb-3">
                        <label for="bulkBillingIncrement" class="form-label">Billing Increment</label>
                        <select class="form-select" id="bulkBillingIncrement" required>
                            @foreach($config['available_increments'] as $key => $increment)
                                <option value="{{ $key }}">{{ $increment['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bulkMinimumDuration" class="form-label">Minimum Duration (seconds)</label>
                        <input type="number" class="form-control" id="bulkMinimumDuration" min="0" value="0" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Selected items:</strong> <span id="selectedCount">0</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Test billing calculation
document.getElementById('testBillingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const destination = document.getElementById('test_destination').value;
    const duration = document.getElementById('test_duration').value;
    
    fetch('{{ route("admin.billing.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            destination: destination,
            duration: parseInt(duration)
        })
    })
    .then(response => response.json())
    .then(data => {
        const resultDiv = document.getElementById('testResult');
        if (data.success) {
            const result = data.result;
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h6>Billing Calculation Result:</h6>
                    <ul class="mb-0">
                        <li><strong>Destination:</strong> ${result.destination_name}</li>
                        <li><strong>Rate per minute:</strong> $${result.rate_per_minute}</li>
                        <li><strong>Actual duration:</strong> ${result.actual_duration} seconds</li>
                        <li><strong>Billable duration:</strong> ${result.billable_duration} seconds</li>
                        <li><strong>Billing config:</strong> ${result.billing_config.label}</li>
                        <li><strong>Total cost:</strong> $${result.cost}</li>
                        <li><strong>Rate source:</strong> ${result.rate_source}</li>
                    </ul>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('testResult').innerHTML = '<div class="alert alert-danger">An error occurred</div>';
        document.getElementById('testResult').style.display = 'block';
    });
});

// Edit rate increment
function editRateIncrement(type, id, currentIncrement, minimumDuration) {
    document.getElementById('editRateType').value = type;
    document.getElementById('editRateId').value = id;
    document.getElementById('editBillingIncrement').value = currentIncrement;
    document.getElementById('editMinimumDuration').value = minimumDuration;
    
    new bootstrap.Modal(document.getElementById('editRateModal')).show();
}

// Handle edit form submission
document.getElementById('editRateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const type = document.getElementById('editRateType').value;
    const id = document.getElementById('editRateId').value;
    const increment = document.getElementById('editBillingIncrement').value;
    const minimumDuration = document.getElementById('editMinimumDuration').value;
    
    const url = type === 'call_rate' 
        ? `/admin/billing/call-rates/${id}/increment`
        : `/admin/billing/country-rates/${id}/increment`;
    
    fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            billing_increment_config: increment,
            minimum_duration: parseInt(minimumDuration)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});

// Bulk update functionality
function bulkUpdateModal(type) {
    document.getElementById('bulkUpdateType').value = type;
    
    const checkboxes = document.querySelectorAll(`.${type.replace('_', '-')}-checkbox:checked`);
    document.getElementById('selectedCount').textContent = checkboxes.length;
    
    if (checkboxes.length === 0) {
        alert('Please select at least one item');
        return;
    }
    
    new bootstrap.Modal(document.getElementById('bulkUpdateModal')).show();
}

// Handle bulk update form submission
document.getElementById('bulkUpdateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const type = document.getElementById('bulkUpdateType').value;
    const increment = document.getElementById('bulkBillingIncrement').value;
    const minimumDuration = document.getElementById('bulkMinimumDuration').value;
    
    const checkboxes = document.querySelectorAll(`.${type.replace('_', '-')}-checkbox:checked`);
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    fetch('{{ route("admin.billing.bulk-update-increments") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            type: type,
            billing_increment_config: increment,
            minimum_duration: parseInt(minimumDuration),
            ids: ids
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});

// Select all functionality
document.getElementById('selectAllCallRates').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.call-rate-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

document.getElementById('selectAllCountryRates').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.country-rate-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>
@endpush