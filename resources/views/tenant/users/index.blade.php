@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', 'Users')

@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <h1 class="page-title mb-0">Users</h1>
                <p class="text-secondary mb-0">Manage users registered for this tenant.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a class="btn btn-primary" href="{{ route('tenant.users.create') }}">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add user
                </a>
                <x-filter-button :filters="['search', 'status']" />
            </div>
        </div>
        <x-filter-offcanvas :action="route('tenant.users.index')" :filters="['search', 'status']">
            <x-filter-field name="search" label="Search" type="search" maxlength="200" />
            <x-filter-field name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
        </x-filter-offcanvas>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <section class="dashboard-card listing-table-card" data-ajax-pagination-container>
            <x-listing-search :action="route('tenant.users.index')" label="Search users..." :maxlength="200" :count="$users->total()" />

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">S. No.</th>
                            <th scope="col">User</th>
                            <th scope="col">Email</th>
                            <th scope="col">Mobile number</th>
                            <th scope="col">Roles</th>
                            <th scope="col">Status</th>
                            <th scope="col">Joined</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php($fullName = trim($user->first_name . ' ' . $user->last_name))
                            <tr>
                                <td>{{ $users->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="user-table-avatar">{{ strtoupper(substr($fullName, 0, 1)) }}</span>
                                        <span class="fw-semibold">{{ $fullName }}</span>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->mobile_number ?: 'NA' }}</td>
                                <td>{{ $user->roles->pluck('name')->join(', ') ?: 'No role assigned' }}</td>
                                <td>
                                    <span
                                        class="badge rounded-pill text-bg-{{ $user->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($user->status) }}
                                    </span>
                                </td>
                                <td>{{ $user->created_at?->format('d M Y') }}</td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('tenant.users.edit', $user) }}">
                                        <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>
                                    </a>
                                    @if ($canDeleteUsers)
                                        <form class="d-inline" method="POST"
                                            action="{{ route('tenant.users.destroy', $user) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                                onclick="return confirm('Delete this user?')">
                                                <i class="fa-solid fa-trash me-1" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="listing-empty">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="listing-table-footer">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
