@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Audit Log #' . $auditLog->id)
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Audit Log #{{ $auditLog->id }}</h1>
            <a href="{{ route('tenant.audit-logs.index') }}" class="btn btn-light">Back to Audit Logs</a>
        </div>
        <section class="dashboard-card p-4 mb-4">
            <p>{{ $auditLog->description }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-3">User</dt>
                <dd class="col-sm-9">
                    {{ $auditLog->user ? trim($auditLog->user->first_name . ' ' . $auditLog->user->last_name) : $metadata['actor_name'] ?? \Illuminate\Support\Str::headline($metadata['actor_type'] ?? 'system / unavailable actor') }}
                </dd>
                <dt class="col-sm-3">Module / Action</dt>
                <dd class="col-sm-9">{{ \Illuminate\Support\Str::headline($auditLog->module) }} /
                    {{ \Illuminate\Support\Str::headline($auditLog->action) }}</dd>
                <dt class="col-sm-3">Date/Time</dt>
                <dd class="col-sm-9">{{ $auditLog->created_at->format('d M Y H:i:s') }}</dd>
                <dt class="col-sm-3">Affected model</dt>
                <dd class="col-sm-9 text-break">{{ $auditLog->auditable_type ?? '-' }}</dd>
                <dt class="col-sm-3">Record</dt>
                <dd class="col-sm-9">#{{ $auditLog->auditable_id }} {{ $metadata['reference'] ?? '' }}</dd>
                <dt class="col-sm-3">IP address</dt>
                <dd class="col-sm-9">{{ $auditLog->ip_address ?? '-' }}</dd>
                <dt class="col-sm-3">User agent</dt>
                <dd class="col-sm-9 text-break">{{ $auditLog->user_agent ?? '-' }}</dd>
            </dl>
        </section>
        <section class="dashboard-card p-4 mb-4">
            <h2 class="h5">Changed values</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Old</th>
                            <th>New</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fields as $field)
                            <tr>
                                <th>{{ \Illuminate\Support\Str::headline($field) }}</th>
                                @foreach ([$oldValues, $newValues] as $values)
                                    <td>
                                        <pre class="mb-0 text-break" style="white-space: pre-wrap">{{ array_key_exists($field, $values) ? json_encode($values[$field], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : '-' }}</pre>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-muted">No value changes recorded for this event.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        @if ($metadata)
            <section class="dashboard-card p-4">
                <h2 class="h5">Metadata</h2>
                <pre class="mb-0 text-break" style="white-space: pre-wrap">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
            </section>
        @endif
    </div>
@endsection
