@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Audit Logs')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-2">Audit Logs</h1>
        <p class="text-muted mb-4">Business actions and changes in this tenant.</p>
        @include('tenant.partials.validation-errors')
        <form method="GET" action="{{ route('tenant.audit-logs.index') }}" class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="audit-search">Search</label>
                <input id="audit-search" class="form-control" name="search" maxlength="200" value="{{ request('search') }}"
                    placeholder="Description, module, action or record ID">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="audit-user">User</label>
                <select class="form-select" id="audit-user" name="user_id">
                    <option value="">All users</option>
                    @foreach ($choices['users'] as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->first_name }}
                            {{ $user->last_name }}</option>
                    @endforeach
                </select>
            </div>
            @foreach (['module' => 'Module', 'action' => 'Action'] as $field => $label)
                <div class="col-md-2">
                    <label class="form-label" for="audit-{{ $field }}">{{ $label }}</label>
                    <select class="form-select" id="audit-{{ $field }}" name="{{ $field }}">
                        <option value="">All {{ strtolower($label) }}s</option>
                        @foreach ($choices[$field . 's'] as $value)
                            <option value="{{ $value }}" @selected(request($field) === $value)>
                                {{ \Illuminate\Support\Str::headline($value) }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-md-3">
                <label class="form-label" for="audit-from">Date From</label>
                <input type="date" class="form-control" id="audit-from" name="from" value="{{ request('from') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="audit-to">Date To</label>
                <input type="date" class="form-control" id="audit-to" name="to" value="{{ request('to') }}">
            </div>
            <div class="col-md-3 align-self-end">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-light" href="{{ route('tenant.audit-logs.index') }}">Reset</a>
            </div>
        </form>
        <section class="dashboard-card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Record</th>
                            <th>Description</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $log)
                            <tr>
                                <td>#{{ $log->id }}</td>
                                <td class="text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $log->user ? trim($log->user->first_name . ' ' . $log->user->last_name) : 'System / unavailable actor' }}
                                </td>
                                <td>{{ \Illuminate\Support\Str::headline($log->module) }}</td>
                                <td>
                                    <span class="badge text-bg-secondary">
                                        {{ \Illuminate\Support\Str::headline($log->action) }}
                                    </span>
                                </td>
                                <td>{{ class_basename($log->auditable_type ?? '') }} #{{ $log->auditable_id }}</td>
                                <td>{{ $log->description }}</td>
                                <td>
                                    <a class="btn btn-sm btn-light"
                                        href="{{ route('tenant.audit-logs.show', $log->id) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">No audit logs match your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $auditLogs->links() }}
        </section>
    </div>
@endsection
