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
            <form data-tenant-user-form method="POST" novalidate
                action="{{ $user->exists ? route('tenant.users.update', $user) : route('tenant.users.store') }}">
                @csrf
                @if ($user->exists)
                    @method('PUT')
                @endif

                <div class="alert alert-danger d-none" data-form-error role="alert"></div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="first_name">First name</label>
                        <input class="form-control" id="first_name" name="first_name" type="text"
                            value="{{ old('first_name', $user->first_name) }}">
                        <small class="field-error" data-error-for="first_name">
                            @error('first_name')
                                {{ $message }}
                            @enderror
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="last_name">Last name</label>
                        <input class="form-control" id="last_name" name="last_name" type="text"
                            value="{{ old('last_name', $user->last_name) }}">
                        <small class="field-error" data-error-for="last_name">
                            @error('last_name')
                                {{ $message }}
                            @enderror
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email address</label>
                        <input class="form-control" id="email" name="email" type="email"
                            value="{{ old('email', $user->email) }}" @readonly($user->exists)>
                        <small class="field-error" data-error-for="email">
                            @if ($user->exists)
                                Email cannot be changed.
                            @else
                                @error('email')
                                    {{ $message }}
                                @enderror
                            @endif
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mobile_number">Mobile number</label>
                        <input class="form-control" id="mobile_number" name="mobile_number" type="tel"
                            value="{{ old('mobile_number', $user->mobile_number) }}">
                        <small class="field-error" data-error-for="mobile_number">
                            @error('mobile_number')
                                {{ $message }}
                            @enderror
                        </small>
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
                        <small class="field-error" data-error-for="role_id">
                            @error('role_id')
                                {{ $message }}
                            @enderror
                        </small>
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
                        <small class="field-error" data-error-for="status">
                            @error('status')
                                {{ $message }}
                            @enderror
                        </small>
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
    <script src="{{ asset('js/tenant-user-form.js') }}"></script>
@endpush
