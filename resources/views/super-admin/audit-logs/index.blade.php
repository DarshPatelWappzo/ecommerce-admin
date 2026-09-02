@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="page-title mb-1">Audit Logs</h1>
                <p class="text-secondary mb-0">Review changes made by administrators.</p>
            </div>
        </div>

        <div class="dashboard-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table user-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Sr. No.</th>
                            <th scope="col">Module</th>
                            <th scope="col">Operation</th>
                            <th scope="col">Date &amp; Time</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $auditLog)
                            <tr>
                                <td>{{ $auditLogs->firstItem() + $loop->index }}</td>
                                <td>{{ Illuminate\Support\Str::headline($auditLog->module) }}</td>
                                <td><span class="badge rounded-pill text-bg-{{ $auditLog->action === 'created' ? 'success' : ($auditLog->action === 'deleted' ? 'danger' : 'primary') }}">{{ $auditLog->action === 'created' ? 'Add' : ucfirst($auditLog->action) }}</span></td>
                                <td>{{ $auditLog->created_at?->format('d M Y, h:i A') }}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#audit-log-{{ $auditLog->id }}" type="button" aria-label="View audit changes">
                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-5 text-center text-secondary" colspan="5">No audit logs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($auditLogs->hasPages())
                <div class="border-top p-3">{{ $auditLogs->links() }}</div>
            @endif

            @foreach ($auditLogs as $auditLog)
                @include('super-admin.audit-logs.partials.changes-modal', ['auditLog' => $auditLog])
            @endforeach
        </div>
    </div>
@endsection
