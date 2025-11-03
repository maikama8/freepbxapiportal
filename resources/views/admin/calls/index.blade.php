@extends('layouts.sneat-admin')

@section('title', 'Call Management')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bx bx-phone-call me-2"></i>Call Management
                </h4>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary">
                        <i class="bx bx-plus"></i> New Call
                    </button>
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="bx bx-refresh"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-phone bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\CallRecord') ? \App\Models\CallRecord::whereIn('status', ['initiated', 'ringing', 'answered', 'in_progress'])->count() : 0 }}</h4>
                                <p class="mb-0">Active Calls</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-check-circle bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\CallRecord') ? \App\Models\CallRecord::where('status', 'completed')->whereDate('created_at', today())->count() : 0 }}</h4>
                                <p class="mb-0">Completed Today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-time bx-lg mb-2"></i>
                                <h4>{{ class_exists('\App\Models\CallRecord') ? \App\Models\CallRecord::whereDate('created_at', today())->avg('duration') ?? 0 : 0 }}s</h4>
                                <p class="mb-0">Avg Duration</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <i class="bx bx-dollar bx-lg mb-2"></i>
                                <h4>${{ class_exists('\App\Models\CallRecord') ? number_format(\App\Models\CallRecord::whereDate('created_at', today())->sum('cost'), 2) : '0.00' }}</h4>
                                <p class="mb-0">Revenue Today</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Call ID</th>
                                <th>Customer</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Cost</th>
                                <th>Started</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(class_exists('\App\Models\CallRecord'))
                                @forelse(\App\Models\CallRecord::with('user')->latest()->limit(20)->get() as $call)
                                <tr>
                                    <td><code>{{ $call->id }}</code></td>
                                    <td>{{ $call->user->name ?? 'Unknown' }}</td>
                                    <td>{{ $call->from_number }}</td>
                                    <td>{{ $call->to_number }}</td>
                                    <td>
                                        <span class="badge bg-{{ $call->status === 'completed' ? 'success' : ($call->status === 'failed' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($call->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $call->duration ? $call->duration . 's' : '-' }}</td>
                                    <td>${{ number_format($call->cost, 4) }}</td>
                                    <td>{{ $call->created_at->format('M d, H:i') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#"><i class="bx bx-show me-1"></i> View Details</a>
                                                @if(in_array($call->status, ['initiated', 'ringing', 'answered', 'in_progress']))
                                                <a class="dropdown-item text-danger" href="#"><i class="bx bx-phone-off me-1"></i> Terminate</a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bx bx-phone bx-lg text-muted mb-2"></i>
                                            <p class="text-muted">No calls found</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            @else
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bx bx-info-circle bx-lg text-info mb-2"></i>
                                            <p class="text-muted">Call records table not available</p>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection