@extends('layouts.sneat-admin')

@section('title', 'Audit Logs')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bx bx-history me-2"></i>Audit Logs
                </h4>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="bx bx-filter"></i> Filter
                    </button>
                    <button type="button" class="btn btn-primary">
                        <i class="bx bx-download"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info" role="alert">
                    <i class="bx bx-info-circle me-2"></i>
                    Audit log features are coming soon. This will include user activity tracking, system changes, and security events.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Resource</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(class_exists('\App\Models\AuditLog'))
                                @forelse(\App\Models\AuditLog::latest()->limit(10)->get() as $log)
                                <tr>
                                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $log->user->name ?? 'System' }}</td>
                                    <td>{{ $log->action }}</td>
                                    <td>{{ $log->resource_type }}</td>
                                    <td>{{ $log->ip_address }}</td>
                                    <td>
                                        <span class="badge bg-success">Success</span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No audit logs available</td>
                                </tr>
                                @endforelse
                            @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Audit logging system not yet implemented</td>
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