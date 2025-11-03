@extends('layouts.sneat-admin')

@section('title', 'DID Number Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">DID Number Management</h5>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDidModal">
                            <i class="bx bx-plus"></i> Add DID Number
                        </button>
                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                            <i class="bx bx-upload"></i> Bulk Upload
                        </button>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                            <i class="bx bx-edit"></i> Bulk Update Prices
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title mb-1">Total DIDs</h6>
                                            <h4 class="mb-0" id="total-dids">-</h4>
                                        </div>
                                        <div class="avatar">
                                            <span class="avatar-initial rounded bg-label-white">
                                                <i class="bx bx-phone text-primary"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title mb-1">Available</h6>
                                            <h4 class="mb-0" id="available-dids">-</h4>
                                        </div>
                                        <div class="avatar">
                                            <span class="avatar-initial rounded bg-label-white">
                                                <i class="bx bx-check-circle text-success"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title mb-1">Assigned</h6>
                                            <h4 class="mb-0" id="assigned-dids">-</h4>
                                        </div>
                                        <div class="avatar">
                                            <span class="avatar-initial rounded bg-label-white">
                                                <i class="bx bx-user text-warning"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title mb-1">Monthly Revenue</h6>
                                            <h4 class="mb-0" id="monthly-revenue">-</h4>
                                        </div>
                                        <div class="avatar">
                                            <span class="avatar-initial rounded bg-label-white">
                                                <i class="bx bx-dollar text-info"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select class="form-select" id="status-filter">
                                <option value="">All Statuses</option>
                                <option value="available">Available</option>
                                <option value="assigned">Assigned</option>
                                <option value="suspended">Suspended</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="country-filter">
                                <option value="">All Countries</option>
                                @foreach($countries ?? [] as $country)
                                    <option value="{{ $country->country_code }}">{{ $country->country_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="search-input" placeholder="Search DID numbers...">
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-secondary" id="clear-filters">
                                <i class="bx bx-x"></i> Clear Filters
                            </button>
                        </div>
                    </div>

                    <!-- DID Numbers Table -->
                    <div class="table-responsive">
                        <table class="table table-striped" id="dids-table">
                            <thead>
                                <tr>
                                    <th>DID Number</th>
                                    <th>Country</th>
                                    <th>Area Code</th>
                                    <th>Monthly Cost</th>
                                    <th>Setup Cost</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Assigned Date</th>
                                    <th>Features</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded via DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add DID Modal -->
<div class="modal fade" id="addDidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add DID Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="add-did-form">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">DID Number</label>
                        <input type="text" class="form-control" name="did_number" required>
                        <div class="form-text">Enter the complete DID number (e.g., 15551234567)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Country</label>
                        <select class="form-select" name="country_code" required>
                            <option value="">Select Country</option>
                            @foreach($countries ?? [] as $country)
                                <option value="{{ $country->country_code }}">{{ $country->country_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Area Code</label>
                        <input type="text" class="form-control" name="area_code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Provider</label>
                        <input type="text" class="form-control" name="provider">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Monthly Cost ($)</label>
                                <input type="number" class="form-control" name="monthly_cost" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Setup Cost ($)</label>
                                <input type="number" class="form-control" name="setup_cost" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
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
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expires At</label>
                        <input type="date" class="form-control" name="expires_at">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add DID Number</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload DID Numbers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h6>CSV Format Requirements:</h6>
                    <p>Your CSV file should contain the following columns:</p>
                    <code>did_number, country_code, area_code, provider, monthly_cost, setup_cost, features, expires_at</code>
                    <br><br>
                    <div class="mt-2">
                        <strong>Features:</strong> Comma-separated values (voice, sms, fax)<br>
                        <strong>Expires At:</strong> Date format YYYY-MM-DD (optional)<br>
                        <strong>Costs:</strong> Decimal values (e.g., 5.99)
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Default Country</label>
                        <select class="form-select" id="template-country" required>
                            <option value="">Select Country</option>
                            @foreach($countries ?? [] as $country)
                                <option value="{{ $country->country_code }}">{{ $country->country_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary" id="download-template">
                            <i class="bx bx-download"></i> Download Template
                        </button>
                    </div>
                </div>
                
                <form id="bulk-upload-form" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv,.txt" required>
                        <div class="form-text">Maximum file size: 10MB</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Country (for rows without country_code)</label>
                        <select class="form-select" name="country_code" required>
                            <option value="">Select Country</option>
                            @foreach($countries ?? [] as $country)
                                <option value="{{ $country->country_code }}">{{ $country->country_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
                
                <!-- Upload Progress -->
                <div id="upload-progress" class="d-none">
                    <div class="progress mb-3">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="upload-status">Processing...</div>
                </div>
                
                <!-- Upload Results -->
                <div id="upload-results" class="d-none">
                    <div class="alert alert-success">
                        <h6>Upload Results:</h6>
                        <div id="results-summary"></div>
                        <div id="error-details" class="mt-2 d-none">
                            <strong>Errors:</strong>
                            <ul id="error-list"></ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="upload-csv-btn">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    Upload CSV
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Prices Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Update DID Prices</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bx bx-info-circle"></i>
                    <strong>Warning:</strong> This will update prices for all DIDs matching the selected filters. Please review your filters carefully.
                </div>
                
                <form id="bulk-update-form">
                    <!-- Filters Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Filter DIDs to Update</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Country</label>
                                        <select class="form-select" name="filter_country">
                                            <option value="">All Countries</option>
                                            @foreach($countries ?? [] as $country)
                                                <option value="{{ $country->country_code }}">{{ $country->country_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="filter_status">
                                            <option value="">All Statuses</option>
                                            <option value="available">Available</option>
                                            <option value="assigned">Assigned</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="expired">Expired</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Area Code</label>
                                        <input type="text" class="form-control" name="filter_area_code" placeholder="e.g., 555">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-info btn-sm" id="preview-update">
                                <i class="bx bx-search"></i> Preview Affected DIDs
                            </button>
                            <div id="preview-results" class="mt-3 d-none">
                                <div class="alert alert-info">
                                    <strong>Preview:</strong> <span id="preview-count">0</span> DIDs will be updated
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Update Options Section -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Price Update Options</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Update Type</label>
                                        <select class="form-select" name="update_type" required>
                                            <option value="set">Set to specific value</option>
                                            <option value="increase">Increase by amount</option>
                                            <option value="decrease">Decrease by amount</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Amount ($)</label>
                                        <input type="number" class="form-control" name="update_amount" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="update_monthly_cost" id="update-monthly" checked>
                                        <label class="form-check-label" for="update-monthly">
                                            Update Monthly Cost
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="update_setup_cost" id="update-setup">
                                        <label class="form-check-label" for="update-setup">
                                            Update Setup Cost
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="bulk-update-btn">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    Update Prices
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Assign DID Modal -->
<div class="modal fade" id="assignDidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign DID Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assign-did-form">
                <div class="modal-body">
                    <input type="hidden" name="did_id" id="assign-did-id">
                    <div class="mb-3">
                        <label class="form-label">DID Number</label>
                        <input type="text" class="form-control" id="assign-did-number" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <select class="form-select" name="user_id" required>
                            <option value="">Select Customer</option>
                            <!-- Will be populated via AJAX -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Extension (Optional)</label>
                        <input type="text" class="form-control" name="extension" placeholder="e.g., 1001">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign DID</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#dids-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.dids.data") }}',
            data: function(d) {
                d.status_filter = $('#status-filter').val();
                d.country_filter = $('#country-filter').val();
            }
        },
        columns: [
            { data: 'did_number', name: 'did_number' },
            { data: 'country_code', name: 'country_code' },
            { data: 'area_code', name: 'area_code' },
            { data: 'monthly_cost', name: 'monthly_cost' },
            { data: 'setup_cost', name: 'setup_cost' },
            { 
                data: 'status', 
                name: 'status',
                render: function(data, type, row) {
                    const statusClasses = {
                        'Available': 'success',
                        'Assigned': 'warning', 
                        'Suspended': 'danger',
                        'Expired': 'secondary'
                    };
                    return `<span class="badge bg-${statusClasses[data] || 'secondary'}">${data}</span>`;
                }
            },
            { data: 'user_name', name: 'user_name' },
            { data: 'assigned_at', name: 'assigned_at' },
            { data: 'features', name: 'features' },
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let actions = `
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                Actions
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" onclick="editDid(${row.id})">
                                    <i class="bx bx-edit"></i> Edit
                                </a>`;
                    
                    if (row.status === 'Available') {
                        actions += `
                                <a class="dropdown-item" href="#" onclick="assignDid(${row.id}, '${row.did_number}')">
                                    <i class="bx bx-user-plus"></i> Assign
                                </a>`;
                    } else if (row.status === 'Assigned') {
                        actions += `
                                <a class="dropdown-item" href="#" onclick="releaseDid(${row.id})">
                                    <i class="bx bx-user-minus"></i> Release
                                </a>`;
                    }
                    
                    actions += `
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="#" onclick="deleteDid(${row.id})">
                                    <i class="bx bx-trash"></i> Delete
                                </a>
                            </div>
                        </div>`;
                    
                    return actions;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true
    });

    // Load statistics
    loadStatistics();

    // Filter handlers
    $('#status-filter, #country-filter').on('change', function() {
        table.draw();
    });

    $('#search-input').on('keyup', function() {
        table.search(this.value).draw();
    });

    $('#clear-filters').on('click', function() {
        $('#status-filter, #country-filter').val('');
        $('#search-input').val('');
        table.search('').draw();
    });

    // Add DID form submission
    $('#add-did-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: '{{ route("admin.dids.store") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#addDidModal').modal('hide');
                    $('#add-did-form')[0].reset();
                    table.draw();
                    loadStatistics();
                    showAlert('success', response.message);
                }
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors || {};
                let errorMessage = 'Failed to add DID number';
                if (Object.keys(errors).length > 0) {
                    errorMessage = Object.values(errors).flat().join('<br>');
                }
                showAlert('error', errorMessage);
            }
        });
    });

    // Assign DID form submission
    $('#assign-did-form').on('submit', function(e) {
        e.preventDefault();
        
        const didId = $('#assign-did-id').val();
        const formData = new FormData(this);
        
        $.ajax({
            url: `/admin/dids/${didId}/assign`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#assignDidModal').modal('hide');
                    $('#assign-did-form')[0].reset();
                    table.draw();
                    loadStatistics();
                    showAlert('success', response.message);
                }
            },
            error: function(xhr) {
                showAlert('error', xhr.responseJSON?.message || 'Failed to assign DID');
            }
        });
    });

    // Download template functionality
    $('#download-template').on('click', function(e) {
        e.preventDefault();
        const country = $('#template-country').val();
        if (!country) {
            showAlert('error', 'Please select a country first');
            return;
        }
        
        window.location.href = `{{ route('admin.dids.template') }}?country=${country}`;
    });

    // Bulk upload functionality
    $('#upload-csv-btn').on('click', function() {
        const form = $('#bulk-upload-form')[0];
        const formData = new FormData(form);
        
        if (!formData.get('csv_file') || !formData.get('country_code')) {
            showAlert('error', 'Please select a CSV file and default country');
            return;
        }
        
        const btn = $(this);
        const spinner = btn.find('.spinner-border');
        
        btn.prop('disabled', true);
        spinner.removeClass('d-none');
        $('#upload-progress').removeClass('d-none');
        $('#upload-results').addClass('d-none');
        
        $.ajax({
            url: '{{ route("admin.dids.bulk-upload") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = evt.loaded / evt.total * 100;
                        $('.progress-bar').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                $('#upload-progress').addClass('d-none');
                
                if (response.success) {
                    $('#upload-results').removeClass('d-none');
                    $('#results-summary').html(`
                        <strong>Success:</strong> ${response.results.success} DIDs uploaded successfully<br>
                        <strong>Total Rows:</strong> ${response.results.total}<br>
                        <strong>Errors:</strong> ${response.results.errors}
                    `);
                    
                    if (response.results.error_details && response.results.error_details.length > 0) {
                        $('#error-details').removeClass('d-none');
                        const errorList = $('#error-list');
                        errorList.empty();
                        response.results.error_details.forEach(error => {
                            errorList.append(`<li>${error}</li>`);
                        });
                    }
                    
                    table.draw();
                    loadStatistics();
                    
                    if (response.results.errors === 0) {
                        setTimeout(() => {
                            $('#bulkUploadModal').modal('hide');
                        }, 3000);
                    }
                }
            },
            error: function(xhr) {
                $('#upload-progress').addClass('d-none');
                showAlert('error', xhr.responseJSON?.message || 'Upload failed');
            },
            complete: function() {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
            }
        });
    });

    // Preview bulk update
    $('#preview-update').on('click', function() {
        const formData = new FormData($('#bulk-update-form')[0]);
        
        $.ajax({
            url: '{{ route("admin.dids.data") }}',
            method: 'GET',
            data: {
                status_filter: formData.get('filter_status'),
                country_filter: formData.get('filter_country'),
                area_code_filter: formData.get('filter_area_code'),
                length: -1 // Get all records for count
            },
            success: function(response) {
                $('#preview-results').removeClass('d-none');
                $('#preview-count').text(response.recordsFiltered || 0);
            },
            error: function() {
                showAlert('error', 'Failed to preview update');
            }
        });
    });

    // Bulk update prices
    $('#bulk-update-btn').on('click', function() {
        if (!confirm('Are you sure you want to update prices for the selected DIDs? This action cannot be undone.')) {
            return;
        }
        
        const form = $('#bulk-update-form')[0];
        const formData = new FormData(form);
        
        // Validate that at least one cost type is selected
        if (!formData.get('update_monthly_cost') && !formData.get('update_setup_cost')) {
            showAlert('error', 'Please select at least one cost type to update');
            return;
        }
        
        const btn = $(this);
        const spinner = btn.find('.spinner-border');
        
        btn.prop('disabled', true);
        spinner.removeClass('d-none');
        
        $.ajax({
            url: '{{ route("admin.dids.bulk-update-prices") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#bulkUpdateModal').modal('hide');
                    $('#bulk-update-form')[0].reset();
                    $('#preview-results').addClass('d-none');
                    table.draw();
                    loadStatistics();
                    showAlert('success', response.message);
                }
            },
            error: function(xhr) {
                showAlert('error', xhr.responseJSON?.message || 'Bulk update failed');
            },
            complete: function() {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
            }
        });
    });

    // Reset modals when closed
    $('#bulkUploadModal').on('hidden.bs.modal', function() {
        $('#bulk-upload-form')[0].reset();
        $('#upload-progress').addClass('d-none');
        $('#upload-results').addClass('d-none');
        $('.progress-bar').css('width', '0%');
    });

    $('#bulkUpdateModal').on('hidden.bs.modal', function() {
        $('#bulk-update-form')[0].reset();
        $('#preview-results').addClass('d-none');
    });
});

function loadStatistics() {
    $.get('/admin/dids/statistics', function(data) {
        $('#total-dids').text(data.total);
        $('#available-dids').text(data.available);
        $('#assigned-dids').text(data.assigned);
        $('#monthly-revenue').text('$' + parseFloat(data.monthly_revenue).toFixed(2));
    });
}

function assignDid(didId, didNumber) {
    $('#assign-did-id').val(didId);
    $('#assign-did-number').val(didNumber);
    
    // Load customers
    $.get('/admin/customers/list', function(customers) {
        const select = $('#assign-did-form select[name="user_id"]');
        select.empty().append('<option value="">Select Customer</option>');
        customers.forEach(customer => {
            select.append(`<option value="${customer.id}">${customer.name} (${customer.email})</option>`);
        });
    });
    
    $('#assignDidModal').modal('show');
}

function releaseDid(didId) {
    if (confirm('Are you sure you want to release this DID number?')) {
        $.ajax({
            url: `/admin/dids/${didId}/release`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#dids-table').DataTable().draw();
                    loadStatistics();
                    showAlert('success', response.message);
                }
            },
            error: function(xhr) {
                showAlert('error', xhr.responseJSON?.message || 'Failed to release DID');
            }
        });
    }
}

function deleteDid(didId) {
    if (confirm('Are you sure you want to delete this DID number? This action cannot be undone.')) {
        $.ajax({
            url: `/admin/dids/${didId}`,
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#dids-table').DataTable().draw();
                    loadStatistics();
                    showAlert('success', response.message);
                }
            },
            error: function(xhr) {
                showAlert('error', xhr.responseJSON?.message || 'Failed to delete DID');
            }
        });
    }
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
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>
@endpush