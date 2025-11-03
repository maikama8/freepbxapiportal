@extends('layouts.sneat-admin')

@section('title', 'Customer Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Customer Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCustomerModal">
                    <i class="bx bx-plus"></i> Add New Customer
                </button>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="roleFilter" class="form-label">Role</label>
                            <select id="roleFilter" class="form-select">
                                <option value="">All Roles</option>
                                <option value="customer">Customer</option>
                                <option value="operator">Operator</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="accountTypeFilter" class="form-label">Account Type</label>
                            <select id="accountTypeFilter" class="form-select">
                                <option value="">All Types</option>
                                <option value="prepaid">Prepaid</option>
                                <option value="postpaid">Postpaid</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">Status</label>
                            <select id="statusFilter" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="locked">Locked</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="button" id="clearFilters" class="btn btn-outline-secondary">
                                    Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="customersTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Account Type</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Extension</th>
                                    <th>Created</th>
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

<!-- Create Customer Modal -->
<div class="modal fade" id="createCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createCustomerForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="extension" class="form-label">Extension</label>
                                <input type="text" class="form-control" id="extension" name="extension">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="customer">Customer</option>
                                    <option value="operator">Operator</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="account_type" class="form-label">Account Type *</label>
                                <select class="form-select" id="account_type" name="account_type" required>
                                    <option value="">Select Account Type</option>
                                    <option value="prepaid">Prepaid</option>
                                    <option value="postpaid">Postpaid</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="balance" class="form-label">Initial Balance</label>
                                <input type="number" class="form-control" id="balance" name="balance" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="credit_limit" class="form-label">Credit Limit</label>
                                <input type="number" class="form-control" id="credit_limit" name="credit_limit" step="0.01" min="0" value="0">
                                <small class="form-text text-muted">For postpaid accounts only</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">Eastern Time</option>
                                    <option value="America/Chicago">Central Time</option>
                                    <option value="America/Denver">Mountain Time</option>
                                    <option value="America/Los_Angeles">Pacific Time</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="GBP">GBP</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Balance Adjustment Modal -->
<div class="modal fade" id="balanceAdjustmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Balance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="balanceAdjustmentForm">
                <div class="modal-body">
                    <input type="hidden" id="adjust_customer_id" name="customer_id">
                    <div class="mb-3">
                        <label for="adjustment_type" class="form-label">Adjustment Type *</label>
                        <select class="form-select" id="adjustment_type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="credit">Credit (Add Funds)</option>
                            <option value="debit">Debit (Deduct Funds)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="adjustment_amount" class="form-label">Amount *</label>
                        <input type="number" class="form-control" id="adjustment_amount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="adjustment_description" class="form-label">Description *</label>
                        <textarea class="form-control" id="adjustment_description" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Adjust Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="passwordResetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="passwordResetForm">
                <div class="modal-body">
                    <input type="hidden" id="reset_customer_id" name="customer_id">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="new_password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password_confirmation" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="new_password_confirmation" name="password_confirmation" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    console.log('Initializing DataTable...');
    console.log('CSRF Token:', $('meta[name="csrf-token"]').attr('content'));
    
    // Initialize DataTable
    const table = $('#customersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.customers.data") }}',
            type: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            xhrFields: {
                withCredentials: true
            },
            data: function(d) {
                d.role_filter = $('#roleFilter').val();
                d.account_type_filter = $('#accountTypeFilter').val();
                d.status_filter = $('#statusFilter').val();
                console.log('DataTable request data:', d);
            },
            error: function(xhr, error, code) {
                console.error('DataTable AJAX Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    code: code
                });
                
                let errorMessage = 'Error loading data: ';
                if (xhr.status === 401) {
                    errorMessage += 'Not authenticated. Please refresh the page and login again.';
                } else if (xhr.status === 403) {
                    errorMessage += 'Access denied. You may not have admin permissions.';
                } else if (xhr.responseJSON?.message) {
                    errorMessage += xhr.responseJSON.message;
                } else {
                    errorMessage += `HTTP ${xhr.status} - ${xhr.statusText}`;
                }
                
                alert(errorMessage);
                
                // Show error in table
                $('#customersTable tbody').html(`
                    <tr>
                        <td colspan="11" class="text-center text-danger">
                            <i class="bx bx-error"></i> ${errorMessage}
                            <br><small>Check browser console for more details</small>
                        </td>
                    </tr>
                `);
            },
            beforeSend: function(xhr) {
                console.log('DataTable AJAX request starting...');
                console.log('Request URL:', '{{ route("admin.customers.data") }}');
                console.log('CSRF Token:', $('meta[name="csrf-token"]').attr('content'));
            },
            complete: function(xhr, status) {
                console.log('DataTable AJAX request completed:', status);
                if (status === 'success') {
                    console.log('Response received successfully');
                }
            }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'email', name: 'email' },
            { data: 'phone', name: 'phone' },
            { 
                data: 'role', 
                name: 'role',
                render: function(data) {
                    const badgeClass = data === 'Customer' ? 'bg-primary' : 'bg-info';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            { 
                data: 'account_type', 
                name: 'account_type',
                render: function(data) {
                    const badgeClass = data === 'Prepaid' ? 'bg-success' : 'bg-warning';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            { 
                data: 'balance', 
                name: 'balance',
                render: function(data) {
                    return `$${data}`;
                }
            },
            { 
                data: 'status', 
                name: 'status',
                render: function(data) {
                    let badgeClass = 'bg-secondary';
                    if (data === 'Active') badgeClass = 'bg-success';
                    else if (data === 'Locked') badgeClass = 'bg-danger';
                    else if (data === 'Inactive') badgeClass = 'bg-warning';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            { data: 'extension', name: 'extension' },
            { data: 'created_at', name: 'created_at' },
            { 
                data: 'actions', 
                name: 'actions', 
                orderable: false, 
                searchable: false,
                render: function(data, type, row) {
                    return `
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewCustomer(${data})" title="View Details">
                                <i class="bx bx-show"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="adjustBalance(${data})" title="Adjust Balance">
                                <i class="bx bx-dollar"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="resetPassword(${data})" title="Reset Password">
                                <i class="bx bx-key"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCustomer(${data})" title="Delete">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true
    });

    // Filter change handlers
    $('#roleFilter, #accountTypeFilter, #statusFilter').change(function() {
        table.draw();
    });

    // Clear filters
    $('#clearFilters').click(function() {
        $('#roleFilter, #accountTypeFilter, #statusFilter').val('');
        table.draw();
    });

    // Create customer form submission
    $('#createCustomerForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: '{{ route("admin.customers.store") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#createCustomerModal').modal('hide');
                    $('#createCustomerForm')[0].reset();
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
                    showAlert('danger', 'An error occurred while creating the customer');
                }
            }
        });
    });

    // Balance adjustment form submission
    $('#balanceAdjustmentForm').submit(function(e) {
        e.preventDefault();
        
        const customerId = $('#adjust_customer_id').val();
        const formData = new FormData(this);
        
        $.ajax({
            url: `/admin/customers/${customerId}/adjust-balance`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#balanceAdjustmentModal').modal('hide');
                    $('#balanceAdjustmentForm')[0].reset();
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

    // Password reset form submission
    $('#passwordResetForm').submit(function(e) {
        e.preventDefault();
        
        const customerId = $('#reset_customer_id').val();
        const formData = new FormData(this);
        
        $.ajax({
            url: `/admin/customers/${customerId}/reset-password`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#passwordResetModal').modal('hide');
                    $('#passwordResetForm')[0].reset();
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
});

// View customer details
function viewCustomer(customerId) {
    $.ajax({
        url: `/admin/customers/${customerId}`,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const customer = response.customer;
                const recentCalls = response.recent_calls;
                const recentTransactions = response.recent_transactions;
                
                let content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Customer Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Name:</strong></td><td>${customer.name}</td></tr>
                                <tr><td><strong>Email:</strong></td><td>${customer.email}</td></tr>
                                <tr><td><strong>Phone:</strong></td><td>${customer.phone || 'N/A'}</td></tr>
                                <tr><td><strong>Role:</strong></td><td>${customer.role}</td></tr>
                                <tr><td><strong>Account Type:</strong></td><td>${customer.account_type}</td></tr>
                                <tr><td><strong>Status:</strong></td><td>${customer.status}</td></tr>
                                <tr><td><strong>Extension:</strong></td><td>${customer.extension || 'N/A'}</td></tr>
                                <tr><td><strong>Balance:</strong></td><td>$${customer.balance}</td></tr>
                                <tr><td><strong>Credit Limit:</strong></td><td>$${customer.credit_limit}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Recent Call History</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Destination</th>
                                            <th>Duration</th>
                                            <th>Cost</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                if (recentCalls.length > 0) {
                    recentCalls.forEach(call => {
                        content += `
                            <tr>
                                <td>${call.destination}</td>
                                <td>${call.duration}s</td>
                                <td>$${call.cost}</td>
                                <td>${call.status}</td>
                            </tr>
                        `;
                    });
                } else {
                    content += '<tr><td colspan="4">No recent calls</td></tr>';
                }
                
                content += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Recent Transactions</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Description</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                if (recentTransactions.length > 0) {
                    recentTransactions.forEach(transaction => {
                        content += `
                            <tr>
                                <td>${transaction.type}</td>
                                <td>$${transaction.amount}</td>
                                <td>${transaction.description}</td>
                                <td>${new Date(transaction.created_at).toLocaleDateString()}</td>
                            </tr>
                        `;
                    });
                } else {
                    content += '<tr><td colspan="4">No recent transactions</td></tr>';
                }
                
                content += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#customerDetailsContent').html(content);
                $('#customerDetailsModal').modal('show');
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load customer details');
        }
    });
}

// Adjust balance
function adjustBalance(customerId) {
    $('#adjust_customer_id').val(customerId);
    $('#balanceAdjustmentModal').modal('show');
}

// Reset password
function resetPassword(customerId) {
    $('#reset_customer_id').val(customerId);
    $('#passwordResetModal').modal('show');
}

// Delete customer
function deleteCustomer(customerId) {
    if (confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        $.ajax({
            url: `/admin/customers/${customerId}`,
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#customersTable').DataTable().draw();
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

// Show alert function
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    $('.alert').remove();
    
    // Add new alert at the top of the container
    $('.container-fluid').prepend(alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}
</script>
@endpush