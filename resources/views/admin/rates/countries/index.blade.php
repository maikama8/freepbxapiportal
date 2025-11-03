@extends('layouts.sneat-admin')

@section('title', 'Country Rate Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Country Rate Management</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCountryRateModal">
                            <i class="bx bx-plus"></i> Add Country Rate
                        </button>
                        <button type="button" class="btn btn-info" onclick="showComparison()">
                            <i class="bx bx-bar-chart"></i> Compare Rates
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportRates()">
                            <i class="bx bx-download"></i> Export
                        </button>
                        <button type="button" class="btn btn-warning" onclick="showAnalytics()">
                            <i class="bx bx-line-chart"></i> Analytics
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Status Filter</label>
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Feature Filter</label>
                            <select class="form-select" id="featureFilter">
                                <option value="">All Features</option>
                                <option value="voice">Voice</option>
                                <option value="sms">SMS</option>
                                <option value="fax">Fax</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min Call Rate</label>
                            <input type="number" class="form-control" id="minCallRate" step="0.0001" placeholder="0.0000">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Call Rate</label>
                            <input type="number" class="form-control" id="maxCallRate" step="0.0001" placeholder="1.0000">
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="row mb-3" id="bulkActions" style="display: none;">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <span id="selectedCount">0</span> countries selected
                                <div class="btn-group ms-3">
                                    <button type="button" class="btn btn-sm btn-warning" onclick="showBulkUpdate()">
                                        <i class="bx bx-edit"></i> Bulk Update
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()">
                                        <i class="bx bx-trash"></i> Delete Selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table class="table table-striped" id="countryRatesTable">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Country</th>
                                    <th>Code</th>
                                    <th>Prefix</th>
                                    <th>Call Rate</th>
                                    <th>DID Setup</th>
                                    <th>DID Monthly</th>
                                    <th>Billing</th>
                                    <th>Features</th>
                                    <th>DIDs</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Country Rate Modal -->
<div class="modal fade" id="addCountryRateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Country Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCountryRateForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country Code *</label>
                                <input type="text" class="form-control" name="country_code" maxlength="2" required>
                                <div class="form-text">2-letter ISO country code (e.g., US, GB)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country Name *</label>
                                <input type="text" class="form-control" name="country_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country Prefix *</label>
                                <input type="text" class="form-control" name="country_prefix" required>
                                <div class="form-text">International dialing prefix (e.g., 1, 44)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Call Rate per Minute *</label>
                                <input type="number" class="form-control" name="call_rate_per_minute" step="0.0001" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">DID Setup Cost *</label>
                                <input type="number" class="form-control" name="did_setup_cost" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">DID Monthly Cost *</label>
                                <input type="number" class="form-control" name="did_monthly_cost" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SMS Rate per Message</label>
                                <input type="number" class="form-control" name="sms_rate_per_message" step="0.0001" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Billing Increment *</label>
                                <select class="form-select" name="billing_increment" required>
                                    <option value="1">1 second</option>
                                    <option value="6">6 seconds</option>
                                    <option value="30">30 seconds</option>
                                    <option value="60" selected>60 seconds</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Duration (seconds) *</label>
                                <input type="number" class="form-control" name="minimum_duration" min="1" value="60" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Features</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="voice" checked>
                                    <label class="form-check-label">Voice</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="sms">
                                    <label class="form-check-label">SMS</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="fax">
                                    <label class="form-check-label">Fax</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="video">
                                    <label class="form-check-label">Video</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Area Codes (optional)</label>
                        <input type="text" class="form-control" name="area_codes_input" placeholder="Enter area codes separated by commas">
                        <div class="form-text">Leave empty if all area codes are supported</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" checked>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Country Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Country Rate Modal -->
<div class="modal fade" id="editCountryRateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Country Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCountryRateForm">
                <input type="hidden" name="country_rate_id">
                <div class="modal-body">
                    <!-- Same form fields as add modal -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country Code *</label>
                                <input type="text" class="form-control" name="country_code" maxlength="2" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country Name *</label>
                                <input type="text" class="form-control" name="country_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country Prefix *</label>
                                <input type="text" class="form-control" name="country_prefix" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Call Rate per Minute *</label>
                                <input type="number" class="form-control" name="call_rate_per_minute" step="0.0001" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">DID Setup Cost *</label>
                                <input type="number" class="form-control" name="did_setup_cost" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">DID Monthly Cost *</label>
                                <input type="number" class="form-control" name="did_monthly_cost" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SMS Rate per Message</label>
                                <input type="number" class="form-control" name="sms_rate_per_message" step="0.0001" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Billing Increment *</label>
                                <select class="form-select" name="billing_increment" required>
                                    <option value="1">1 second</option>
                                    <option value="6">6 seconds</option>
                                    <option value="30">30 seconds</option>
                                    <option value="60">60 seconds</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Duration (seconds) *</label>
                                <input type="number" class="form-control" name="minimum_duration" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Features</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="voice">
                                    <label class="form-check-label">Voice</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="sms">
                                    <label class="form-check-label">SMS</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="fax">
                                    <label class="form-check-label">Fax</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="video">
                                    <label class="form-check-label">Video</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Area Codes (optional)</label>
                        <input type="text" class="form-control" name="area_codes_input" placeholder="Enter area codes separated by commas">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Country Rate</button>
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
                <h5 class="modal-title">Bulk Update Country Rates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkUpdateForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Update Type *</label>
                        <select class="form-select" name="update_type" required onchange="toggleBulkUpdateFields(this.value)">
                            <option value="">Select update type</option>
                            <option value="call_rate">Call Rates</option>
                            <option value="did_costs">DID Costs</option>
                            <option value="billing_increment">Billing Increment</option>
                            <option value="status">Status</option>
                        </select>
                    </div>

                    <!-- Call Rate Fields -->
                    <div id="callRateFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <select class="form-select" name="call_rate_type">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adjustment Value</label>
                            <input type="number" class="form-control" name="call_rate_adjustment" step="0.0001">
                            <div class="form-text">For percentage: 10 = +10%, -5 = -5%. For fixed: 0.01 = +$0.01</div>
                        </div>
                    </div>

                    <!-- DID Cost Fields -->
                    <div id="didCostFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <select class="form-select" name="did_cost_type">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Setup Cost Adjustment</label>
                            <input type="number" class="form-control" name="did_setup_adjustment" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monthly Cost Adjustment</label>
                            <input type="number" class="form-control" name="did_monthly_adjustment" step="0.01">
                        </div>
                    </div>

                    <!-- Billing Increment Fields -->
                    <div id="billingIncrementFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">New Billing Increment</label>
                            <select class="form-select" name="billing_increment">
                                <option value="1">1 second</option>
                                <option value="6">6 seconds</option>
                                <option value="30">30 seconds</option>
                                <option value="60">60 seconds</option>
                            </select>
                        </div>
                    </div>

                    <!-- Status Fields -->
                    <div id="statusFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" name="is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rate Comparison Modal -->
<div class="modal fade" id="comparisonModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Comparison</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="comparisonContent">
                    <p>Select 2-10 countries to compare their rates.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pricing Analytics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="analyticsContent">
                    Loading analytics...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate Change History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="historyContent">
                    Loading history...
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let countryRatesTable;
let selectedCountries = [];

$(document).ready(function() {
    initializeDataTable();
    initializeEventHandlers();
});

function initializeDataTable() {
    countryRatesTable = $('#countryRatesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.country-rates.data") }}',
            data: function(d) {
                d.status_filter = $('#statusFilter').val();
                d.feature_filter = $('#featureFilter').val();
                d.min_call_rate = $('#minCallRate').val();
                d.max_call_rate = $('#maxCallRate').val();
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    return `<input type="checkbox" class="country-checkbox" value="${row.id}">`;
                }
            },
            { data: 'country_name' },
            { data: 'country_code' },
            { data: 'country_prefix' },
            { 
                data: 'call_rate_per_minute',
                render: function(data) {
                    return '$' + data;
                }
            },
            { 
                data: 'did_setup_cost',
                render: function(data) {
                    return '$' + data;
                }
            },
            { 
                data: 'did_monthly_cost',
                render: function(data) {
                    return '$' + data;
                }
            },
            { data: 'billing_increment' },
            { 
                data: 'features',
                render: function(data) {
                    return data.map(f => `<span class="badge bg-info">${f}</span>`).join(' ');
                }
            },
            { data: 'did_count' },
            { 
                data: 'is_active',
                render: function(data) {
                    return data ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                }
            },
            {
                data: 'actions',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    return `
                        <div class="btn-group">
                            <button class="btn btn-sm btn-info" onclick="viewCountryRate(${row.id})">
                                <i class="bx bx-show"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="editCountryRate(${row.id})">
                                <i class="bx bx-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="viewHistory(${row.id})">
                                <i class="bx bx-history"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteCountryRate(${row.id})">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        responsive: true
    });
}

function initializeEventHandlers() {
    // Filter change handlers
    $('#statusFilter, #featureFilter, #minCallRate, #maxCallRate').on('change keyup', function() {
        countryRatesTable.ajax.reload();
    });

    // Select all checkbox
    $('#selectAll').on('change', function() {
        $('.country-checkbox').prop('checked', this.checked);
        updateSelectedCountries();
    });

    // Individual checkbox change
    $(document).on('change', '.country-checkbox', function() {
        updateSelectedCountries();
    });

    // Form submissions
    $('#addCountryRateForm').on('submit', function(e) {
        e.preventDefault();
        submitCountryRateForm(this, 'POST', '{{ route("admin.country-rates.store") }}');
    });

    $('#editCountryRateForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('input[name="country_rate_id"]').val();
        submitCountryRateForm(this, 'PUT', `/admin/country-rates/${id}`);
    });

    $('#bulkUpdateForm').on('submit', function(e) {
        e.preventDefault();
        submitBulkUpdate();
    });
}

function updateSelectedCountries() {
    selectedCountries = [];
    $('.country-checkbox:checked').each(function() {
        selectedCountries.push($(this).val());
    });

    if (selectedCountries.length > 0) {
        $('#bulkActions').show();
        $('#selectedCount').text(selectedCountries.length);
    } else {
        $('#bulkActions').hide();
    }
}

function submitCountryRateForm(form, method, url) {
    const formData = new FormData(form);
    
    // Handle area codes
    const areaCodes = formData.get('area_codes_input');
    if (areaCodes) {
        const areaCodesArray = areaCodes.split(',').map(code => code.trim()).filter(code => code);
        formData.delete('area_codes_input');
        areaCodesArray.forEach(code => formData.append('area_codes[]', code));
    }

    // Handle features
    const features = [];
    $('input[name="features[]"]:checked').each(function() {
        features.push($(this).val());
    });
    formData.delete('features[]');
    features.forEach(feature => formData.append('features[]', feature));

    $.ajax({
        url: url,
        method: method,
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                countryRatesTable.ajax.reload();
                $(form).closest('.modal').modal('hide');
                $(form)[0].reset();
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
                toastr.error('An error occurred while processing your request');
            }
        }
    });
}

function viewCountryRate(id) {
    $.get(`/admin/country-rates/${id}`, function(response) {
        if (response.success) {
            const rate = response.country_rate;
            const stats = rate.statistics;
            
            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <p><strong>Country:</strong> ${rate.country_name} (${rate.country_code})</p>
                        <p><strong>Prefix:</strong> +${rate.country_prefix}</p>
                        <p><strong>Status:</strong> ${rate.is_active ? 'Active' : 'Inactive'}</p>
                        <p><strong>Features:</strong> ${rate.features.join(', ')}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Pricing</h6>
                        <p><strong>Call Rate:</strong> $${rate.call_rate_per_minute}/min</p>
                        <p><strong>DID Setup:</strong> $${rate.did_setup_cost}</p>
                        <p><strong>DID Monthly:</strong> $${rate.did_monthly_cost}</p>
                        <p><strong>SMS Rate:</strong> $${rate.sms_rate_per_message}/msg</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Billing Configuration</h6>
                        <p><strong>Billing Increment:</strong> ${rate.billing_increment} seconds</p>
                        <p><strong>Minimum Duration:</strong> ${rate.minimum_duration} seconds</p>
                    </div>
                    <div class="col-md-6">
                        <h6>DID Statistics</h6>
                        <p><strong>Total DIDs:</strong> ${stats.total_dids}</p>
                        <p><strong>Assigned DIDs:</strong> ${stats.assigned_dids}</p>
                        <p><strong>Available DIDs:</strong> ${stats.available_dids}</p>
                    </div>
                </div>
            `;
            
            Swal.fire({
                title: `${rate.country_name} Rate Details`,
                html: content,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false
            });
        }
    });
}

function editCountryRate(id) {
    $.get(`/admin/country-rates/${id}`, function(response) {
        if (response.success) {
            const rate = response.country_rate;
            const form = $('#editCountryRateForm');
            
            form.find('input[name="country_rate_id"]').val(rate.id);
            form.find('input[name="country_code"]').val(rate.country_code);
            form.find('input[name="country_name"]').val(rate.country_name);
            form.find('input[name="country_prefix"]').val(rate.country_prefix);
            form.find('input[name="call_rate_per_minute"]').val(rate.call_rate_per_minute);
            form.find('input[name="did_setup_cost"]').val(rate.did_setup_cost);
            form.find('input[name="did_monthly_cost"]').val(rate.did_monthly_cost);
            form.find('input[name="sms_rate_per_message"]').val(rate.sms_rate_per_message);
            form.find('select[name="billing_increment"]').val(rate.billing_increment);
            form.find('input[name="minimum_duration"]').val(rate.minimum_duration);
            form.find('input[name="is_active"]').prop('checked', rate.is_active);
            
            // Set features
            form.find('input[name="features[]"]').prop('checked', false);
            if (rate.features) {
                rate.features.forEach(feature => {
                    form.find(`input[name="features[]"][value="${feature}"]`).prop('checked', true);
                });
            }
            
            // Set area codes
            if (rate.area_codes && rate.area_codes.length > 0) {
                form.find('input[name="area_codes_input"]').val(rate.area_codes.join(', '));
            }
            
            $('#editCountryRateModal').modal('show');
        }
    });
}

function deleteCountryRate(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will delete the country rate and all associated DID numbers.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `/admin/country-rates/${id}`,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        countryRatesTable.ajax.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete country rate');
                }
            });
        }
    });
}

function showBulkUpdate() {
    if (selectedCountries.length === 0) {
        toastr.warning('Please select countries to update');
        return;
    }
    $('#bulkUpdateModal').modal('show');
}

function toggleBulkUpdateFields(updateType) {
    $('.modal-body > div[id$="Fields"]').hide();
    if (updateType) {
        $(`#${updateType.replace('_', '')}Fields`).show();
    }
}

function submitBulkUpdate() {
    const formData = new FormData($('#bulkUpdateForm')[0]);
    selectedCountries.forEach(id => formData.append('country_ids[]', id));

    $.ajax({
        url: '{{ route("admin.country-rates.bulk-update") }}',
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
                countryRatesTable.ajax.reload();
                $('#bulkUpdateModal').modal('hide');
                $('#bulkUpdateForm')[0].reset();
                selectedCountries = [];
                updateSelectedCountries();
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to perform bulk update');
        }
    });
}

function showComparison() {
    if (selectedCountries.length < 2 || selectedCountries.length > 10) {
        toastr.warning('Please select 2-10 countries to compare');
        return;
    }

    $.post('{{ route("admin.country-rates.comparison") }}', {
        country_ids: selectedCountries,
        _token: $('meta[name="csrf-token"]').attr('content')
    }, function(response) {
        if (response.success) {
            displayComparison(response.comparison, response.metrics);
            $('#comparisonModal').modal('show');
        }
    });
}

function displayComparison(comparison, metrics) {
    let html = `
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6>Call Rate Range</h6>
                        <p class="mb-0">$${metrics.call_rate.min} - $${metrics.call_rate.max}</p>
                        <small class="text-muted">Avg: $${metrics.call_rate.avg}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6>DID Setup Range</h6>
                        <p class="mb-0">$${metrics.did_setup_cost.min} - $${metrics.did_setup_cost.max}</p>
                        <small class="text-muted">Avg: $${metrics.did_setup_cost.avg}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6>DID Monthly Range</h6>
                        <p class="mb-0">$${metrics.did_monthly_cost.min} - $${metrics.did_monthly_cost.max}</p>
                        <small class="text-muted">Avg: $${metrics.did_monthly_cost.avg}</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Call Rate</th>
                        <th>DID Setup</th>
                        <th>DID Monthly</th>
                        <th>Billing</th>
                        <th>Utilization</th>
                    </tr>
                </thead>
                <tbody>
    `;

    comparison.forEach(country => {
        html += `
            <tr>
                <td>${country.country_name} (${country.country_code})</td>
                <td>$${country.call_rate_per_minute}</td>
                <td>$${country.did_setup_cost}</td>
                <td>$${country.did_monthly_cost}</td>
                <td>${country.billing_increment_description}</td>
                <td>${country.statistics.utilization_rate}%</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    $('#comparisonContent').html(html);
}

function showAnalytics() {
    $('#analyticsModal').modal('show');
    
    $.get('{{ route("admin.country-rates.analytics") }}', function(response) {
        if (response.success) {
            displayAnalytics(response.analytics);
        }
    });
}

function displayAnalytics(analytics) {
    const html = `
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>${analytics.total_countries}</h5>
                        <p class="mb-0">Total Countries</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>$${analytics.call_rate_statistics.avg}</h5>
                        <p class="mb-0">Avg Call Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>$${analytics.did_cost_statistics.setup_cost.avg}</h5>
                        <p class="mb-0">Avg Setup Cost</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5>$${analytics.did_cost_statistics.monthly_cost.avg}</h5>
                        <p class="mb-0">Avg Monthly Cost</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6">
                <h6>Billing Increment Distribution</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Increment</th><th>Count</th><th>Percentage</th></tr>
                        </thead>
                        <tbody>
                            ${analytics.billing_increment_distribution.map(item => 
                                `<tr><td>${item.increment}s</td><td>${item.count}</td><td>${item.percentage}%</td></tr>`
                            ).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <h6>Feature Distribution</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Feature</th><th>Count</th><th>Percentage</th></tr>
                        </thead>
                        <tbody>
                            ${Object.entries(analytics.feature_distribution).map(([feature, data]) => 
                                `<tr><td>${feature}</td><td>${data.count}</td><td>${data.percentage}%</td></tr>`
                            ).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    $('#analyticsContent').html(html);
}

function viewHistory(id) {
    $('#historyModal').modal('show');
    
    $.get(`/admin/country-rates/${id}/history`, function(response) {
        if (response.success) {
            displayHistory(response.country_rate, response.history);
        }
    });
}

function displayHistory(countryRate, history) {
    let html = `
        <h6>${countryRate.country_name} (${countryRate.country_code}) - Change History</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>User</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
    `;

    history.forEach(log => {
        const changes = log.changes ? Object.keys(log.changes).join(', ') : 'N/A';
        html += `
            <tr>
                <td>${log.created_at}</td>
                <td>${log.action.replace('_', ' ').toUpperCase()}</td>
                <td>${log.user ? log.user.name : 'System'}</td>
                <td>${changes}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    $('#historyContent').html(html);
}

function exportRates() {
    const params = new URLSearchParams({
        active_only: $('#statusFilter').val() === '1',
        include_statistics: true
    });
    
    window.open(`{{ route('admin.country-rates.export') }}?${params}`, '_blank');
}
</script>
@endpush