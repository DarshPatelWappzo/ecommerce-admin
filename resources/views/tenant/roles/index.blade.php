@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', 'Roles')

@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <h1 class="page-title mb-0">Roles</h1>
                <p class="text-secondary mb-0">Manage roles and their permissions.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a class="btn btn-primary" href="{{ route('tenant.roles.create') }}">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add role
                </a>
                <x-filter-button :filters="['search', 'status']" />
            </div>
        </div>
        <x-filter-offcanvas :action="route('tenant.roles.index')" :filters="['search', 'status']">
            <x-filter-field name="search" label="Search" type="search" maxlength="200" />
            <x-filter-field name="status" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" />
        </x-filter-offcanvas>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        <section class="dashboard-card listing-table-card">
            <x-listing-search :action="route('tenant.roles.index')" label="Search roles..." :maxlength="200" />
            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Role name</th>
                            <th scope="col">Description</th>
                            <th scope="col">Status</th>
                            <th scope="col">Permissions</th>
                            <th scope="col">Users</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $role->name }} @if ($role->slug === 'admin')
                                        <span class="badge rounded-pill text-bg-info">Protected</span>
                                    @endif
                                </td>
                                <td>{{ $role->description ?: '—' }}</td>
                                <td><span
                                        class="badge rounded-pill text-bg-{{ $role->status ? 'success' : 'danger' }}">{{ $role->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>{{ $role->permissions_count }}</td>
                                <td>{{ $role->users_count }}</td>
                                <td>
                                    @if ($role->slug === 'admin')
                                        <span class="text-secondary small">System role</span>
                                    @else
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('tenant.roles.edit', $role) }}"><i
                                                class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i></a>
                                        {{-- <form class="d-inline" method="POST"
                                            action="{{ route('tenant.roles.destroy', $role) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                                onclick="return confirm('Delete this role?')"><i
                                                    class="fa-solid fa-trash me-1" aria-hidden="true"></i></button>
                                        </form> --}}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="listing-empty">No roles found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
