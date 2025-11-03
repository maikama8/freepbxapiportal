@extends('layouts.sneat-customer')

@section('title', 'Purchase DID Numbers')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Purchase DID Numbers</h5>
                    <p class="text-muted mb-0">Choose from available DID numbers worldwide</p>
                </div>

                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Country</label>
                            <select class="form-select" id="country-filter">
                                <option value="">All Countries</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->country_code }}" {{ request('country_code') == $country->country_code ? 'selected' : '' }}>
                                        {{ $country->country_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Area Code</label>
                            <input type="text" class="form-control" id="area-code-filter" placeholder="e.g., 212" value="{{ request('area_code') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Features</label>
                            <select class="form-select" id="features-filter">
                                <option value="">All Features</option>
                                <option value="voice">Voice Only</option>
                                <option value="sms">SMS Capable</option>
                                <option value="fax">Fax Capable</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price Range</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="min-price" placeholder="Min" step="0.01">
                                <span class="input-group-text">-</span>
                                <input type="number" class="form-control" id="max-price" placeholder="Max" step="0.01">
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" id="apply-filters">
                                <i class="bx bx-search"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="clear-filters">
                                <i class="bx bx-x"></i> Clear Filters
                            </button>
                        </div>
                    </div>

                    <!-- Available DIDs -->
                    @if($availableDids->count() > 0)
                        <div class="row">
                            @foreach($availableDids as $did)
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 did-card" data-did-id="{{ $did->id }}">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="card-title mb-1">{{ $did->formatted_number }}</h6>
                                                    <small class="text-muted">
                                                        <span class="fi fi-{{ strtolower($did->country_code) }}"></span>
                                                        {{ $did->countryRate->country_name ?? $did->country_code }}
                                                        @if($did->area_code)
                                                            • Area: {{ $did->area_code }}
                                                        @endif
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <div class="badge bg-success">Available</div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <div class="border-end">
                                                            <h6 class="mb-0">${{ number_format($did->setup_cost, 2) }}</h6>
                                                            <small class="text-muted">Setup Fee</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <h6 class="mb-0">${{ number_format($did->monthly_cost, 2) }}</h6>
                                                        <small class="text-muted">Monthly</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <small class="text-muted">Features:</small><br>
                                                @if($did->features)
                                                    @foreach($did->features as $feature)
                                                        <span class="badge bg-info me-1">{{ ucfirst($feature) }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="badge bg-info">Voice</span>
                                                @endif
                                            </div>

                                            <div class="d-grid">
                                                <button type="button" class="btn btn-primary purchase-btn" 
                                                        data-did-id="{{ $did->id }}"
                                                        data-did-number="{{ $did->formatted_number }}"
                                                        data-setup-cost="{{ $did->setup_cost }}"
                                                        data-monthly-cost="{{ $did->monthly_cost }}"
                                                        data-total-cost="{{ $did->setup_cost + $did->monthly_cost }}">
                                                    Purchase for ${{ number_format($did->setup_cost + $did->monthly_cost, 2) }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center">
                            {{ $availableDids->appends(request()->query())->links() }}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="avatar avatar-xl mx-auto mb-3">
                                <span class="avatar-initial rounded-circle bg-label-primary">
                                    <i class="bx bx-search bx-lg"></i>
                                </span>
                            </div>
                            <h5>No DID Numbers Found</h5>
                            <p class="text-muted">No DID numbers match your current filters. Try adjusting your search criteria.</p>
                            <button type="button" class="btn btn-outline-primary" id="clear-filters-empty">
                                <i class="bx bx-x"></i> Clear All Filters
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Confirmation Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm DID Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="avatar avatar-lg mx-auto mb-3">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            <i class="bx bx-phone bx-lg"></i>
                        </span>
                    </div>
                    <h6 id="purchase-did-number"></h6>
                </div>

                <div class="row mb-3">
                    <div class="col-6 text-center">
                        <div class="border-end">
                            <h6 class="mb-0" id="purchase-setup-cost"></h6>
                            <small class="text-muted">Setup Fee</small>
                        </div>
                    </div>
                    <div class="col-6 text-center">
                        <h6 class="mb-0" id="purchase-monthly-cost"></h6>
                        <small class="text-muted">Monthly Fee</small>
                    </div>
                </div>

                <div class="alert alert-info">
                    <div class="d-flex justify-content-between">
                        <strong>Total Cost:</strong>
                        <strong id="purchase-total-cost"></strong>
                    </div>
                    <small class="text-muted">Includes setup fee + first month</small>
                </div>

                <div class="alert alert-warning">
                    <div class="d-flex justify-content-between">
                        <span>Your Current Balance:</span>
                        <strong>${{ number_format(auth()->user()->balance, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Balance After Purchase:</span>
                        <strong id="balance-after-purchase"></strong>
                    </div>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirm-purchase">
                    <label class="form-check-label" for="confirm-purchase">
                        I understand that this DID number will be charged monthly and I agree to the terms.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-purchase-btn" disabled>
                    <i class="bx bx-credit-card"></i> Purchase DID
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let selectedDidId = null;
    const userBalance = {{ auth()->user()->balance }};

    // Filter handlers
    $('#apply-filters, #clear-filters, #clear-filters-empty').on('click', function() {
        if ($(this).attr('id').includes('clear')) {
            // Clear all filters
            $('#country-filter').val('');
            $('#area-code-filter').val('');
            $('#features-filter').val('');
            $('#min-price').val('');
            $('#max-price').val('');
        }
        
        // Build query string
        const params = new URLSearchParams();
        
        const country = $('#country-filter').val();
        const areaCode = $('#area-code-filter').val();
        const features = $('#features-filter').val();
        const minPrice = $('#min-price').val();
        const maxPrice = $('#max-price').val();
        
        if (country) params.append('country_code', country);
        if (areaCode) params.append('area_code', areaCode);
        if (features) params.append('features', features);
        if (minPrice) params.append('min_monthly_cost', minPrice);
        if (maxPrice) params.append('max_monthly_cost', maxPrice);
        
        // Redirect with filters
        window.location.href = '{{ route("customer.dids.browse") }}' + (params.toString() ? '?' + params.toString() : '');
    });

    // Purchase button click
    $('.purchase-btn').on('click', function() {
        const didId = $(this).data('did-id');
        const didNumber = $(this).data('did-number');
        const setupCost = parseFloat($(this).data('setup-cost'));
        const monthlyCost = parseFloat($(this).data('monthly-cost'));
        const totalCost = parseFloat($(this).data('total-cost'));
        
        selectedDidId = didId;
        
        // Populate modal
        $('#purchase-did-number').text(didNumber);
        $('#purchase-setup-cost').text('$' + setupCost.toFixed(2));
        $('#purchase-monthly-cost').text('$' + monthlyCost.toFixed(2));
        $('#purchase-total-cost').text('$' + totalCost.toFixed(2));
        
        const balanceAfter = userBalance - totalCost;
        $('#balance-after-purchase').text('$' + balanceAfter.toFixed(2));
        
        // Check if user has sufficient balance
        if (balanceAfter < 0) {
            $('#balance-after-purchase').addClass('text-danger');
            $('#confirm-purchase-btn').prop('disabled', true).text('Insufficient Balance');
        } else {
            $('#balance-after-purchase').removeClass('text-danger');
            $('#confirm-purchase-btn').prop('disabled', !$('#confirm-purchase').is(':checked')).text('Purchase DID');
        }
        
        $('#purchaseModal').modal('show');
    });

    // Confirm purchase checkbox
    $('#confirm-purchase').on('change', function() {
        const balanceAfter = userBalance - parseFloat($('#purchase-total-cost').text().replace('$', ''));
        if (balanceAfter >= 0) {
            $('#confirm-purchase-btn').prop('disabled', !$(this).is(':checked'));
        }
    });

    // Confirm purchase button
    $('#confirm-purchase-btn').on('click', function() {
        if (!selectedDidId || !$('#confirm-purchase').is(':checked')) {
            return;
        }
        
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');
        
        $.ajax({
            url: `/customer/dids/${selectedDidId}/purchase`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $('#purchaseModal').modal('hide');
                    showAlert('success', response.message);
                    
                    // Remove the purchased DID card
                    $(`.did-card[data-did-id="${selectedDidId}"]`).fadeOut();
                    
                    // Redirect to DID management after 3 seconds
                    setTimeout(() => {
                        window.location.href = '{{ route("customer.dids.index") }}';
                    }, 3000);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                let message = 'Failed to purchase DID number';
                
                if (response && response.required_amount) {
                    message = `${response.message}<br><br>Required: $${response.required_amount.toFixed(2)}<br>Your Balance: $${response.current_balance.toFixed(2)}<br><br><a href="{{ route('customer.payments.add-funds') }}" class="btn btn-sm btn-primary">Add Funds</a>`;
                } else if (response && response.message) {
                    message = response.message;
                }
                
                showAlert('error', message);
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Reset modal when closed
    $('#purchaseModal').on('hidden.bs.modal', function() {
        $('#confirm-purchase').prop('checked', false);
        $('#confirm-purchase-btn').prop('disabled', true);
        selectedDidId = null;
    });
});

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alert = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.container-xxl').prepend(alert);
    
    // Auto dismiss after 8 seconds for success, keep error alerts
    if (type === 'success') {
        setTimeout(() => {
            $('.alert-success').fadeOut();
        }, 8000);
    }
}
</script>
@endpush