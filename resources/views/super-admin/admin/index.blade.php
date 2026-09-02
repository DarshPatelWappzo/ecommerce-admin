@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                {{-- <p class="text-primary fw-semibold mb-1">Super Admin</p> --}}
                <h1 class="page-title mb-1">Users</h1>
                <p class="text-secondary mb-0">Manage users registered in your application.</p>
            </div>
            <a class="btn btn-primary" href="{{ route('super-admin.admin.create') }}">
                <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>
                Add user
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <div class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <div class="p-3 border-bottom">
                <label class="visually-hidden" for="user-search">Search users</label>
                <div class="input-group">
                    {{-- <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span> --}}
                    <input class="form-control" id="user-search" type="search" value="{{ request('search') }}" placeholder="Search users..." data-ajax-search>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table user-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">S. No.</th>
                            <th scope="col">User</th>
                            <th scope="col">Email</th>
                            <th scope="col">Mobile number</th>
                            <th scope="col">package</th>
                            <th scope="col">Status</th>
                            <th scope="col">Joined</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name;
                            @endphp
                            <tr>
                                <td>{{ $users->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="user-table-avatar">{{ strtoupper(substr($fullName, 0, 1)) }}</span>
                                        <div>
                                            <div class="fw-semibold">{{ $fullName }}</div>
                                            @if ($user->is_super_admin)
                                                <small class="text-muted">Super admin</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->mobile_number ?: 'NA' }}</td>
                                <td>Premium</td>
                                <td>
                                    <span class="badge rounded-pill text-bg-{{ $user->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($user->status) }}
                                    </span>
                                </td>
                                <td>{{ $user->created_at?->format('d M Y') }}</td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('super-admin.admin.edit', $user) }}">
                                        <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-2 text-center text-secondary" colspan="8">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-top p-3">{{ $users->links() }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
