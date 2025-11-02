@extends('layouts.customer')

@section('title', 'Call History')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Call History</h5>
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
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Statuses</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="destination" class="form-label">Destination</label>
                            <input type="text" class="form-control" id="destination" name="destination" 
                                   placeholder="Search destination" value="{{ request('destination') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    @if(request()->hasAny(['date_from', 'date_to', 'status', 'destination']))
                        <div class="mb-3">
                            <a href="{{ route('customer.call-history') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    @endif

                    <!-- Call Records Table -->
                    @if($calls->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Caller ID</th>
                                        <th>Destination</th>
                                        <th>Duration</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($calls as $call)
                                    <tr>
                                        <td>
                                            <div>{{ $call->created_at->format('M d, Y') }}</div>
                                            <small class="text-muted">{{ $call->created_at->format('H:i:s') }}</small>
                                        </td>
                                        <td>{{ $call->caller_id }}</td>
                                        <td>
                                            <strong>{{ $call->destination }}</strong>
                                            @if($call->start_time)
                                                <br><small class="text-muted">Started: {{ $call->start_time->format('H:i:s') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $call->getFormattedDuration() }}</span>
                                            @if($call->duration)
                                                <br><small class="text-muted">{{ $call->duration }}s</small>
                                            @endif
                                        </td>
                                        <td>
                                            <strong>${{ number_format($call->cost, 4) }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ 
                                                $call->status === 'completed' ? 'success' : 
                                                ($call->isActive() ? 'warning' : 
                                                ($call->status === 'failed' ? 'danger' : 'secondary')) 
                                            }}">
                                                {{ ucfirst(str_replace('_', ' ', $call->status)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#callDetailsModal{{ $call->id }}">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                @if($call->isActive())
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="hangupCall('{{ $call->call_id }}')">
                                                        <i class="fas fa-phone-slash"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Call Details Modal -->
                                    <div class="modal fade" id="callDetailsModal{{ $call->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Call Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <table class="table table-sm">
                                                        <tr>
                                                            <td><strong>Call ID:</strong></td>
                                                            <td>{{ $call->call_id }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Caller ID:</strong></td>
                                                            <td>{{ $call->caller_id }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Destination:</strong></td>
                                                            <td>{{ $call->destination }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Start Time:</strong></td>
                                                            <td>{{ $call->start_time ? $call->start_time->format('M d, Y H:i:s') : 'N/A' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>End Time:</strong></td>
                                                            <td>{{ $call->end_time ? $call->end_time->format('M d, Y H:i:s') : 'N/A' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Duration:</strong></td>
                                                            <td>{{ $call->getFormattedDuration() }} ({{ $call->getDurationInSeconds() }} seconds)</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Cost:</strong></td>
                                                            <td>${{ number_format($call->cost, 4) }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Status:</strong></td>
                                                            <td>
                                                                <span class="badge bg-{{ 
                                                                    $call->status === 'completed' ? 'success' : 
                                                                    ($call->isActive() ? 'warning' : 
                                                                    ($call->status === 'failed' ? 'danger' : 'secondary')) 
                                                                }}">
                                                                    {{ ucfirst(str_replace('_', ' ', $call->status)) }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Created:</strong></td>
                                                            <td>{{ $call->created_at->format('M d, Y H:i:s') }}</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                Showing {{ $calls->firstItem() }} to {{ $calls->lastItem() }} of {{ $calls->total() }} results
                            </div>
                            <div>
                                {{ $calls->appends(request()->query())->links() }}
                            </div>
                        </div>
                    @else
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-phone-slash fa-4x mb-3"></i>
                            <h5>No calls found</h5>
                            <p>
                                @if(request()->hasAny(['date_from', 'date_to', 'status', 'destination']))
                                    Try adjusting your filters or <a href="{{ route('customer.call-history') }}">clear all filters</a>.
                                @else
                                    You haven't made any calls yet. <a href="{{ route('customer.calls.make') }}">Make your first call</a>
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

@push('scripts')
<script>
function hangupCall(callId) {
    if (confirm('Are you sure you want to hang up this call?')) {
        fetch(`/customer/calls/${callId}/hangup`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to hang up call: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while hanging up the call.');
        });
    }
}
</script>
@endpush