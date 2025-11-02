@extends('layouts.customer')

@section('title', 'Balance History')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-receipt"></i> Balance History</h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="{{ request('date_to') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="credit" {{ request('type') === 'credit' ? 'selected' : '' }}>Credit</option>
                                <option value="debit" {{ request('type') === 'debit' ? 'selected' : '' }}>Debit</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <a href="{{ route('customer.balance-history') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>

                    <!-- Current Balance Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Current Balance</h5>
                                    <h3>${{ number_format(auth()->user()->balance, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Credit Limit</h5>
                                    <h3>${{ number_format(auth()->user()->credit_limit, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5>Available Credit</h5>
                                    <h3>${{ number_format(auth()->user()->balance + auth()->user()->credit_limit, 2) }}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h5>Account Type</h5>
                                    <h3>{{ ucfirst(auth()->user()->account_type) }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    @if($transactions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Balance Before</th>
                                        <th>Balance After</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transactions as $transaction)
                                    <tr>
                                        <td>
                                            <div>{{ $transaction->created_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $transaction->created_at->format('H:i:s') }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $transaction->description }}</strong>
                                            @if($transaction->createdBy && $transaction->createdBy->id !== auth()->id())
                                                <br><small class="text-muted">By: {{ $transaction->createdBy->name }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $transaction->isCredit() ? 'success' : 'danger' }}">
                                                {{ ucfirst($transaction->type) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-{{ $transaction->isCredit() ? 'success' : 'danger' }} fw-bold">
                                                {{ $transaction->getFormattedAmount() }}
                                            </span>
                                        </td>
                                        <td>${{ number_format($transaction->balance_before, 4) }}</td>
                                        <td>
                                            <strong>${{ number_format($transaction->balance_after, 4) }}</strong>
                                        </td>
                                        <td>
                                            @if($transaction->reference_type && $transaction->reference_id)
                                                <span class="badge bg-info">
                                                    {{ ucfirst(str_replace('_', ' ', $transaction->reference_type)) }}
                                                </span>
                                                <br><small class="text-muted">#{{ $transaction->reference_id }}</small>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                Showing {{ $transactions->firstItem() }} to {{ $transactions->lastItem() }} of {{ $transactions->total() }} results
                            </div>
                            <div>
                                {{ $transactions->appends(request()->query())->links() }}
                            </div>
                        </div>

                        <!-- Summary Statistics -->
                        @php
                            $totalCredits = $transactions->where('type', 'credit')->sum('amount');
                            $totalDebits = $transactions->where('type', 'debit')->sum('amount');
                            $netChange = $totalCredits - $totalDebits;
                        @endphp
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Summary for Current View:</h6>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <strong>Total Credits:</strong> 
                                                <span class="text-success">${{ number_format($totalCredits, 4) }}</span>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Total Debits:</strong> 
                                                <span class="text-danger">${{ number_format($totalDebits, 4) }}</span>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Net Change:</strong> 
                                                <span class="text-{{ $netChange >= 0 ? 'success' : 'danger' }}">
                                                    ${{ number_format($netChange, 4) }}
                                                </span>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Transactions:</strong> {{ $transactions->total() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-receipt fa-4x mb-3"></i>
                            <h5>No transactions found</h5>
                            <p>
                                @if(request()->hasAny(['date_from', 'date_to', 'type']))
                                    Try adjusting your filters or <a href="{{ route('customer.balance-history') }}">clear all filters</a>.
                                @else
                                    You don't have any balance transactions yet. <a href="{{ route('customer.payments.add-funds') }}">Add funds</a> to get started.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection