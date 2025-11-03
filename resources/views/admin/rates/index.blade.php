@extends('layouts.sneat-admin')

@section('title', 'Rate Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Rate Management</h2>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                        <i class="bx bx-upload"></i> Bulk Import
                    </button>
                    <button type="button" class="btn btn-info" onclick="exportRates()">
                        <i class="bx bx-download"></i> Export CSV
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRateModal">
                        <i class="bx bx-plus"></i> Add New Rate
                    </button>
                </div>
            </div>

            <!-- Filters and Tools -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">Status</label>
                            <select id="statusFilter" class="form-select">
                                <option value="">All Rates</option>
                                <option value="1">Active Only</option>
                                <option value="0">Inactive Only</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="dateFromFilter" class="form-label">Effective Date From</label>
                            <input type="date" id="dateFromFilter" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="dateToFilter" class="form-label">Effective Date To</label>
                            <input type="date" id="dateToFilter" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="button" id="clearFilters" class="btn btn-outline-secondary">
                                    Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">Rate Calculator</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <input type="text" id="testDestination" class="form-control" placeholder="Enter destination number">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" id="testDuration" class="form-control" placeholder="Duration (seconds)" min="1">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" id="testRateBtn" class="btn btn-outline-primary">
                                                <i class="bx bx-calculator"></i> Calculate Cost
                                            </button>
                                        </div>
                                        <div class="col-md-2">
                                            <div id="testResult" class="fw-bold text-success"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rates Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="ratesTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Prefix</th>
                                    <th>Destination</th>
                                    <th>Rate/Min</th>
                                    <th>Min Duration</th>
                                    <th>Billing Inc.</th>
                                    <th>Effective Date</th>
                                    <th>Status</th>
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

<!-- Create Rate Modal -->
<div class="modal fade" id="createRateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createRateForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="destination_prefix" class="form-label">Destination Prefix *</label>
                                <input type="text" class="form-control" id="destination_prefix" name="destination_prefix" required>
                                <small class="form-text text-muted">e.g., 1, 44, 49, etc.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="destination_name" class="form-label">Destination Name *</label>
                                <input type="text" class="form-control" id="destination_name" name="destination_name" required>
                                <small class="form-text text-muted">e.g., USA, UK, Germany</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="rate_per_minute" class="form-label">Rate per Minute *</label>
                                <input type="number" class="form-control" id="rate_per_minute" name="rate_per_minute" step="0.000001" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="minimum_duration" class="form-label">Minimum Duration (sec) *</label>
                                <input type="number" class="form-control" id="minimum_duration" name="minimum_duration" min="1" value="60" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="billing_increment" class="form-label">Billing Increment (sec) *</label>
                                <input type="number" class="form-control" id="billing_increment" name="billing_increment" min="1" value="60" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="effective_date" class="form-label">Effective Date *</label>
                                <input type="datetime-local" class="form-control" id="effective_date" name="effective_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Active Rate
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Rate Modal -->
<div class="modal fade" id="editRateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRateForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_rate_id" name="rate_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_destination_prefix" class="form-label">Destination Prefix *</label>
                                <input type="text" class="form-control" id="edit_destination_prefix" name="destination_prefix" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_destination_name" class="form-label">Destination Name *</label>
                                <input type="text" class="form-control" id="edit_destination_name" name="destination_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_rate_per_minute" class="form-label">Rate per Minute *</label>
                                <input type="number" class="form-control" id="edit_rate_per_minute" name="rate_per_minute" step="0.000001" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_minimum_duration" class="form-label">Minimum Duration (sec) *</label>
                                <input type="number" class="form-control" id="edit_minimum_duration" name="minimum_duration" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_billing_increment" class="form-label">Billing Increment (sec) *</label>
                                <input type="number" class="form-control" id="edit_billing_increment" name="billing_increment" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_effective_date" class="form-label">Effective Date *</label>
                                <input type="datetime-local" class="form-control" id="edit_effective_date" name="effective_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                    <label class="form-check-label" for="edit_is_active">
                                        Active Rate
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Import Rates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkImportForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6>CSV Format Requirements:</h6>
                        <p>Your CSV file must contain the following columns in this order:</p>
                        <code>destination_prefix, destination_name, rate_per_minute, minimum_duration, billing_increment</code>
                        <br><br>
                        <strong>Example:</strong><br>
                        <code>1,USA,0.015000,60,60</code><br>
                        <code>44,United Kingdom,0.025000,60,60</code>
                    </div>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">CSV File *</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,.txt" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="import_effective_date" class="form-label">Effective Date *</label>
                                <input type="datetime-local" class="form-control" id="import_effective_date" name="effective_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="replace_existing" name="replace_existing">
                                    <label class="form-check-label" for="replace_existing">
                                        Replace existing rates
                                    </label>
                                    <small class="form-text text-muted">If unchecked, existing rates will be skipped</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Import Rates</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rate History Modal -->
<div class="modal fade" id="rateHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rate History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="rateHistoryContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // Set default effective date to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $('#effective_date, #import_effective_date').val(now.toISOString().slice(0, 16));

    // Initialize DataTable
    const table = $('#ratesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.rates.data") }}',
            data: function(d) {
                d.status_filter = $('#statusFilter').val();
                d.date_from = $('#dateFromFilter').val();
                d.date_to = $('#dateToFilter').val();
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'destination_prefix', name: 'destination_prefix' },
            { data: 'destination_name', name: 'destination_name' },
            { 
                data: 'rate_per_minute', 
                name: 'rate_per_minute',
                render: function(data) {
                    return `$${data}`;
                }
            },
            { data: 'minimum_duration', name: 'minimum_duration' },
            { data: 'billing_increment', name: 'billing_increment' },
            { data: 'effective_date', name: 'effective_date' },
            { 
                data: 'is_active', 
                name: 'is_active',
                render: function(data) {
                    const badgeClass = data ? 'bg-success' : 'bg-secondary';
                    const text = data ? 'Active' : 'Inactive';
                    return `<span class="badge ${badgeClass}">${text}</span>`;
                }
            },
            { 
                data: 'actions', 
                name: 'actions', 
                orderable: false, 
                searchable: false,
                render: function(data, type, row) {
                    return `
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editRate(${data})" title="Edit">
                                <i class="bx bx-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="viewHistory('${row.destination_prefix}')" title="History">
                                <i class="bx bx-history"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRate(${data})" title="Delete">
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

    // Filter change handlers
    $('#statusFilter, #dateFromFilter, #dateToFilter').change(function() {
        table.draw();
    });

    // Clear filters
    $('#clearFilters').click(function() {
        $('#statusFilter, #dateFromFilter, #dateToFilter').val('');
        table.draw();
    });

    // Rate calculator
    $('#testRateBtn').click(function() {
        const destination = $('#testDestination').val();
        const duration = $('#testDuration').val();
        
        if (!destination || !duration) {
            showAlert('warning', 'Please enter both destination and duration');
            return;
        }

        $.ajax({
            url: '{{ route("admin.rates.test") }}',
            method: 'POST',
            data: {
                destination: destination,
                duration: duration
            },
            success: function(response) {
                if (response.success) {
                    $('#testResult').html(`Cost: $${response.calculated_cost}`);
                    showAlert('success', `Rate found: ${response.matched_rate.destination_name} (${response.matched_rate.destination_prefix}) - $${response.calculated_cost}`);
                } else {
                    $('#testResult').html('No rate found');
                    showAlert('warning', response.message);
                }
            },
            error: function() {
                showAlert('danger', 'Failed to calculate rate');
            }
        });
    });

    // Create rate form submission
    $('#createRateForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: '{{ route("admin.rates.store") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#createRateModal').modal('hide');
                    $('#createRateForm')[0].reset();
                    table.draw();
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    let errorMessage = 'Validation errors:\n';
                    Object.keys(errors).forEach(key => {
                        errorMessage += `- ${errors[key][0]}\n`;
                    });
                    showAlert('danger', errorMessage);
                } else {
                    showAlert('danger', 'An error occurred while creating the rate');
                }
            }
        });
    });

    // Edit rate form submission
    $('#editRateForm').submit(function(e) {
        e.preventDefault();
        
        const rateId = $('#edit_rate_id').val();
        const formData = new FormData(this);
        
        $.ajax({
            url: `/admin/rates/${rateId}`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-HTTP-Method-Override': 'PUT'
            },
            success: function(response) {
                if (response.success) {
                    $('#editRateModal').modal('hide');
                    $('#editRateForm')[0].reset();
                    table.draw();
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr) {
                showAlert('danger', xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Bulk import form submission
    $('#bulkImportForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Importing...');
        
        $.ajax({
            url: '{{ route("admin.rates.bulk-import") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#bulkImportModal').modal('hide');
                    $('#bulkImportForm')[0].reset();
                    table.draw();
                    
                    let message = response.message;
                    if (response.data.errors && response.data.errors.length > 0) {
                        message += '\n\nErrors:\n' + response.data.errors.slice(0, 5).join('\n');
                        if (response.data.errors.length > 5) {
                            message += `\n... and ${response.data.errors.length - 5} more errors`;
                        }
                    }
                    
                    showAlert('success', message);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr) {
                showAlert('danger', xhr.responseJSON?.message || 'An error occurred during import');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
});

// Edit rate
function editRate(rateId) {
    $.ajax({
        url: `/admin/rates/${rateId}`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const rate = response.rate;
                $('#edit_rate_id').val(rate.id);
                $('#edit_destination_prefix').val(rate.destination_prefix);
                $('#edit_destination_name').val(rate.destination_name);
                $('#edit_rate_per_minute').val(rate.rate_per_minute);
                $('#edit_minimum_duration').val(rate.minimum_duration);
                $('#edit_billing_increment').val(rate.billing_increment);
                $('#edit_effective_date').val(rate.effective_date);
                $('#edit_is_active').prop('checked', rate.is_active);
                $('#editRateModal').modal('show');
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load rate details');
        }
    });
}

// View rate history
function viewHistory(prefix) {
    $.ajax({
        url: `/admin/rates/history/${encodeURIComponent(prefix)}`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                let content = `
                    <h6>Rate History for Prefix: ${response.prefix}</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Rate/Min</th>
                                    <th>Min Duration</th>
                                    <th>Billing Inc.</th>
                                    <th>Effective Date</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                if (response.history.length > 0) {
                    response.history.forEach(rate => {
                        const statusBadge = rate.is_active ? 
                            '<span class="badge bg-success">Active</span>' : 
                            '<span class="badge bg-secondary">Inactive</span>';
                        
                        content += `
                            <tr>
                                <td>$${rate.rate_per_minute}</td>
                                <td>${rate.minimum_duration}s</td>
                                <td>${rate.billing_increment}s</td>
                                <td>${rate.effective_date}</td>
                                <td>${statusBadge}</td>
                                <td>${rate.created_at}</td>
                            </tr>
                        `;
                    });
                } else {
                    content += '<tr><td colspan="6">No rate history found</td></tr>';
                }
                
                content += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                $('#rateHistoryContent').html(content);
                $('#rateHistoryModal').modal('show');
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load rate history');
        }
    });
}

// Delete rate
function deleteRate(rateId) {
    if (confirm('Are you sure you want to delete this rate? This action cannot be undone.')) {
        $.ajax({
            url: `/admin/rates/${rateId}`,
            method: 'DELETE',
            success: function(response) {
                if (response.success) {
                    $('#ratesTable').DataTable().draw();
                    showAlert('success', response.message);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr) {
                showAlert('danger', xhr.responseJSON?.message || 'An error occurred');
            }
        });
    }
}

// Export rates
function exportRates() {
    const activeOnly = confirm('Export active rates only? Click Cancel to export all rates.');
    
    const params = new URLSearchParams({
        active_only: activeOnly ? '1' : '0',
        effective_date_from: $('#dateFromFilter').val() || '',
        effective_date_to: $('#dateToFilter').val() || ''
    });
    
    window.location.href = `{{ route('admin.rates.export') }}?${params.toString()}`;
}

// Show alert function
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message.replace(/\n/g, '<br>')}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top of the container
    $('.container-fluid').prepend(alertHtml);
    
    // Auto-dismiss after 8 seconds for success/info, 12 seconds for errors
    const timeout = type === 'success' || type === 'info' ? 8000 : 12000;
    setTimeout(() => {
        $('.alert').fadeOut();
    }, timeout);
}
</script>
@endpush