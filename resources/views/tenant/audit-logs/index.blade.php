@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Audit Logs')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <h1 class="page-title mb-0">Audit Logs</h1>
                <p class="text-secondary mb-0">Business actions and changes in this tenant.</p>
            </div>
            <x-filter-button :filters="['search', 'user_id', 'module', 'action', 'from', 'to']" />
        </div>
        <x-filter-offcanvas :show-errors="false" :action="route('tenant.audit-logs.index')" :filters="['search', 'user_id', 'module', 'action', 'from', 'to']">
            <x-filter-field name="search" label="Search" type="search" maxlength="200" />
            <x-filter-field name="user_id" label="User" :options="$choices['users']->mapWithKeys(
                fn($user) => [$user->id => trim($user->first_name . ' ' . $user->last_name)],
            )" />
            <x-filter-field name="module" label="Module" :options="$choices['modules']->mapWithKeys(
                fn($value) => [$value => \Illuminate\Support\Str::headline($value)],
            )" />
            <x-filter-field name="action" label="Action" :options="$choices['actions']->mapWithKeys(
                fn($value) => [$value => \Illuminate\Support\Str::headline($value)],
            )" />
            <x-filter-field name="from" label="Date from" type="date" />
            <x-filter-field name="to" label="Date to" type="date" />
        </x-filter-offcanvas>

        @include('tenant.partials.validation-errors')

        <section class="dashboard-card listing-table-card">
            <x-listing-search :action="route('tenant.audit-logs.index')" label="Search audit logs..." :maxlength="200" :count="$auditLogs->total()" />
            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Date/Time</th>
                            <th scope="col">User</th>
                            <th scope="col">Module</th>
                            <th scope="col">Action</th>
                            <th scope="col">Record</th>
                            <th scope="col">Description</th>
                            <th scope="col">Details</th>
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
                                <td colspan="8" class="listing-empty">No audit logs match your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($auditLogs->hasPages())
                <div class="listing-table-footer">{{ $auditLogs->links() }}</div>
            @endif
        </section>
    </div>
@endsection
