@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', $user->exists ? 'Edit User' : 'Add User')

@section('content')
    <div class="container-fluid">
        <div class="mb-4">
            <p class="text-primary fw-semibold mb-1">Tenant Administration</p>
            <h1 class="page-title mb-1">{{ $user->exists ? 'Edit user' : 'Add user' }}</h1>
            <p class="text-secondary mb-0">
                {{ $user->exists ? 'Update this tenant user account.' : 'Create a user for this tenant.' }}
            </p>
        </div>

        <div class="dashboard-card">
            <form id="tenant-user-form" data-tenant-user-form method="POST" novalidate
                action="{{ $user->exists ? route('tenant.users.update', $user) : route('tenant.users.store') }}">
                @csrf
                @if ($user->exists)
                    @method('PUT')
                @endif

                @include('tenant.partials.validation-errors')

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="first_name">First name</label>
                        <input class="form-control" id="first_name" name="first_name" type="text"
                            value="{{ old('first_name', $user->first_name) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="last_name">Last name</label>
                        <input class="form-control" id="last_name" name="last_name" type="text"
                            value="{{ old('last_name', $user->last_name) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email address</label>
                        <input class="form-control" id="email" name="email" type="email"
                            value="{{ old('email', $user->email) }}" @readonly($user->exists)>
                        @if ($user->exists)
                            <div class="form-text">Email cannot be changed.</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mobile_number">Mobile number</label>
                        <input class="form-control" id="mobile_number" name="mobile_number" type="tel"
                            value="{{ old('mobile_number', $user->mobile_number) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="role_id">Role</label>
                        <select class="form-select" id="role_id" name="role_id" data-placeholder="Select a role">
                            <option value="">Select a role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected($role->id == old('role_id', $selectedRoleId))>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            @unless ($user->exists)
                                <option value="" disabled @selected(!old('status', $user->status))>Select status</option>
                            @endunless
                            <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $user->status) === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-4 border-top">
                    <a class="btn btn-light" href="{{ route('tenant.users.index') }}">Cancel</a>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-check me-1"
                            aria-hidden="true"></i>{{ $user->exists ? 'Save changes' : 'Create user' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::formRequest(
        $user->exists
            ? \App\Http\Requests\TenantUserUpdateRequest::class
            : \App\Http\Requests\TenantUserStoreRequest::class,
        '#tenant-user-form',
    ) !!}
@endpush
