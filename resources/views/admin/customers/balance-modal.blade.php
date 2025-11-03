<!-- Balance Adjustment Modal -->
<div class="modal fade" id="balanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Customer Balance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="balanceForm">
                <div class="modal-body">
                    <input type="hidden" id="balance_customer_id" name="customer_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" id="balance_customer_name" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" id="balance_current" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="balance_type" class="form-label">Transaction Type *</label>
                        <select id="balance_type" name="type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="credit">Add Credit (+)</option>
                            <option value="debit">Deduct Amount (-)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="balance_amount" class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="balance_amount" name="amount" class="form-control" 
                                   step="0.01" min="0.01" max="10000" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="balance_description" class="form-label">Description *</label>
                        <textarea id="balance_description" name="description" class="form-control" 
                                  rows="3" placeholder="Reason for balance adjustment..." required></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle"></i>
                        <strong>Note:</strong> This action will be logged in the audit trail.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-check"></i> Adjust Balance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function adjustBalance(customerId, customerName, currentBalance) {
    $('#balance_customer_id').val(customerId);
    $('#balance_customer_name').val(customerName);
    $('#balance_current').val('$' + parseFloat(currentBalance).toFixed(2));
    $('#balanceForm')[0].reset();
    $('#balance_customer_id').val(customerId); // Reset clears this, so set again
    $('#balanceModal').modal('show');
}

$('#balanceForm').on('submit', function(e) {
    e.preventDefault();
    
    const customerId = $('#balance_customer_id').val();
    const formData = {
        type: $('#balance_type').val(),
        amount: $('#balance_amount').val(),
        description: $('#balance_description').val(),
        _token: $('meta[name="csrf-token"]').attr('content')
    };
    
    $.ajax({
        url: `/admin/customers/${customerId}/adjust-balance`,
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#balanceModal').modal('hide');
                showToast('Balance adjusted successfully', 'success');
                
                // Refresh the customers table if it exists
                if (typeof customersTable !== 'undefined') {
                    customersTable.ajax.reload();
                }
            } else {
                showToast(response.message || 'Failed to adjust balance', 'error');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            showToast(response?.message || 'Failed to adjust balance', 'error');
        }
    });
});

function showToast(message, type = 'info') {
    const bgClass = type === 'success' ? 'bg-success' : 
                   type === 'error' ? 'bg-danger' : 'bg-info';
    
    const toast = $(`
        <div class="toast align-items-center text-white ${bgClass} border-0" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('body').append(toast);
    toast.toast('show');
    
    setTimeout(() => toast.remove(), 5000);
}
</script>