@extends('layouts.sneat-customer')

@section('title', 'Transfer DID - ' . $didNumber->formatted_number)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('customer.dids.index') }}">My DIDs</a></li>
                    <li class="breadcrumb-item active">Transfer {{ $didNumber->formatted_number }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Transfer DID Number</h5>
                    <p class="text-muted mb-0">Transfer ownership of {{ $didNumber->formatted_number }} to another account</p>
                </div>
                <div class="card-body">
                    <!-- DID Information -->
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm me-3">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="bx bx-phone"></i>
                                </span>
                            </div>
                            <div>
                                <h6 class="mb-1">{{ $didNumber->formatted_number }}</h6>
                                <small class="text-muted">
                                    <span class="fi fi-{{ strtolower($didNumber->country_code) }}"></span>
                                    {{ $didNumber->countryRate->country_name ?? $didNumber->country_code }}
                                    • Monthly Cost: ${{ number_format($didNumber->monthly_cost, 2) }}
                                </small>
                            </div>
                        </div>
                    </div>

                    <form id="transfer-form">
                        @csrf
                        
                        <!-- Transfer Method -->
                        <div class="mb-4">
                            <label class="form-label">Transfer Method</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="transfer_method" id="transfer_email" value="email" checked>
                                        <label class="form-check-label" for="transfer_email">
                                            <strong>By Email Address</strong><br>
                                            <small class="text-muted">Transfer to an existing customer account</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="transfer_method" id="transfer_code" value="code">
                                        <label class="form-check-label" for="transfer_code">
                                            <strong>By Transfer Code</strong><br>
                                            <small class="text-muted">Generate a code for the recipient</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Transfer -->
                        <div id="email-transfer" class="mb-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Recipient Email Address</label>
                                    <input type="email" class="form-control" name="recipient_email" id="recipient_email" 
                                           placeholder="Enter the email address of the recipient">
                                    <small class="text-muted">The recipient must have an existing account on our platform</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-outline-primary d-block" id="verify-email">
                                        <i class="bx bx-search"></i> Verify Account
                                    </button>
                                </div>
                            </div>
                            <div id="recipient-info" class="mt-3" style="display: none;">
                                <div class="alert alert-success">
                                    <div class="d-flex align-items-center">
                                        <i class="bx bx-check-circle me-2"></i>
                                        <div>
                                            <strong>Account Found:</strong> <span id="recipient-name"></span><br>
                                            <small>Account Type: <span id="recipient-type"></span></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Code Transfer -->
                        <div id="code-transfer" class="mb-4" style="display: none;">
                            <div class="alert alert-warning">
                                <i class="bx bx-info-circle me-2"></i>
                                A transfer code will be generated that you can share with the recipient. 
                                The code will be valid for 24 hours.
                            </div>
                        </div>

                        <!-- Transfer Options -->
                        <div class="mb-4">
                            <h6 class="mb-3">Transfer Options</h6>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="transfer_balance" id="transfer_balance">
                                <label class="form-check-label" for="transfer_balance">
                                    Transfer remaining balance for this DID
                                </label>
                                <small class="text-muted d-block">
                                    Prorated amount: $<span id="prorated-amount">{{ number_format($proratedAmount ?? 0, 2) }}</span>
                                </small>
                            </div>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="transfer_settings" id="transfer_settings" checked>
                                <label class="form-check-label" for="transfer_settings">
                                    Transfer DID configuration settings
                                </label>
                                <small class="text-muted d-block">Include call routing, voicemail, and other settings</small>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_recipient" id="notify_recipient" checked>
                                <label class="form-check-label" for="notify_recipient">
                                    Send notification email to recipient
                                </label>
                            </div>
                        </div>

                        <!-- Transfer Fee -->
                        <div class="mb-4">
                            <div class="alert alert-info">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Transfer Fee:</strong>
                                        <small class="text-muted d-block">One-time fee for DID transfer</small>
                                    </div>
                                    <div class="text-end">
                                        <h6 class="mb-0">$5.00</h6>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Transfer Message -->
                        <div class="mb-4">
                            <label class="form-label">Message to Recipient (Optional)</label>
                            <textarea class="form-control" name="transfer_message" rows="3" 
                                      placeholder="Add a message for the recipient..."></textarea>
                        </div>

                        <!-- Confirmation -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirm_transfer" id="confirm_transfer" required>
                                <label class="form-check-label" for="confirm_transfer">
                                    I understand that this transfer is permanent and cannot be undone. 
                                    I will lose access to this DID number immediately upon transfer.
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('customer.dids.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-warning" id="transfer-btn" disabled>
                                <i class="bx bx-transfer"></i> Transfer DID
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Code Modal -->
<div class="modal fade" id="transferCodeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Code Generated</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="avatar avatar-xl mx-auto mb-3">
                    <span class="avatar-initial rounded-circle bg-label-success">
                        <i class="bx bx-check bx-lg"></i>
                    </span>
                </div>
                <h6>Transfer Code:</h6>
                <div class="alert alert-info">
                    <h4 class="mb-0" id="generated-code"></h4>
                </div>
                <p class="text-muted">
                    Share this code with the recipient. They can use it to claim the DID number.
                    This code expires in 24 hours.
                </p>
                <button type="button" class="btn btn-outline-primary" onclick="copyTransferCode()">
                    <i class="bx bx-copy"></i> Copy Code
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let recipientVerified = false;

    // Transfer method change
    $('input[name="transfer_method"]').on('change', function() {
        if ($(this).val() === 'email') {
            $('#email-transfer').show();
            $('#code-transfer').hide();
        } else {
            $('#email-transfer').hide();
            $('#code-transfer').show();
            recipientVerified = true; // Code method doesn't need verification
        }
        updateTransferButton();
    });

    // Verify email button
    $('#verify-email').on('click', function() {
        const email = $('#recipient_email').val();
        if (!email) {
            showAlert('error', 'Please enter an email address');
            return;
        }

        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Verifying...');

        $.ajax({
            url: '{{ route("customer.dids.verify-recipient") }}',
            method: 'POST',
            data: {
                email: email,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#recipient-name').text(response.user.name);
                    $('#recipient-type').text(response.user.account_type);
                    $('#recipient-info').show();
                    recipientVerified = true;
                    showAlert('success', 'Recipient account verified');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('error', response?.message || 'Account not found');
                $('#recipient-info').hide();
                recipientVerified = false;
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
                updateTransferButton();
            }
        });
    });

    // Confirmation checkbox
    $('#confirm_transfer').on('change', function() {
        updateTransferButton();
    });

    function updateTransferButton() {
        const method = $('input[name="transfer_method"]:checked').val();
        const confirmed = $('#confirm_transfer').is(':checked');
        const verified = method === 'code' || recipientVerified;
        
        $('#transfer-btn').prop('disabled', !(confirmed && verified));
    }

    // Form submission
    $('#transfer-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const btn = $('#transfer-btn');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing Transfer...');
        
        $.ajax({
            url: '{{ route("customer.dids.transfer", $didNumber->id) }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    if (response.transfer_code) {
                        // Show transfer code modal
                        $('#generated-code').text(response.transfer_code);
                        $('#transferCodeModal').modal('show');
                    } else {
                        showAlert('success', 'DID transfer initiated successfully!');
                        setTimeout(() => {
                            window.location.href = '{{ route("customer.dids.index") }}';
                        }, 3000);
                    }
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showAlert('error', response?.message || 'Failed to initiate transfer');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
});

function copyTransferCode() {
    const code = $('#generated-code').text();
    navigator.clipboard.writeText(code).then(function() {
        showAlert('success', 'Transfer code copied to clipboard');
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