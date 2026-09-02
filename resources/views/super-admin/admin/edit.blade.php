@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    @php($domains = old('domains', $user->domains->pluck('domain_name')->all() ?: ['']))

    <div class="container-fluid">
        <div class="mb-4">
            <p class="text-primary fw-semibold mb-1">Super Admin</p>
            <h1 class="page-title mb-1">Edit user</h1>
            <p class="text-secondary mb-0">Update the user account and assigned domains.</p>
        </div>

        <div class="dashboard-card">
            <form data-user-form method="POST" action="{{ route('super-admin.admin.update', $user) }}">
                @csrf
                @method('PUT')
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
                        <input class="form-control" id="email" name="email" type="email" value="{{ $user->email }}"
                            readonly>
                        <small class="field-error" data-error-for="email">Email cannot be changed.</small>
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
                    @include('super-admin.admin._domain-fields', ['domains' => $domains])
                    <div class="col-md-6">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
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
                    <a class="btn btn-light" href="{{ route('super-admin.admin.index') }}">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-1"
                            aria-hidden="true"></i>Save changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-user-form]');
            const formError = form.querySelector('[data-form-error]');

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                form.querySelectorAll('[data-error-for]').forEach((error) => {
                    error.textContent = '';
                });
                formError.classList.add('d-none');
                formError.textContent = '';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            Accept: 'application/json'
                        },
                    });

                    const data = await response.json();

                    if (response.status === 422) {
                        Object.entries(data.errors ?? {}).forEach(([field, messages]) => {
                            const error = form.querySelector(`[data-error-for="${field}"]`);
                            if (error) {
                                error.textContent = messages[0];
                            }
                        });
                        return;
                    }

                    if (!response.ok) {
                        throw new Error('Unable to update the user. Please try again.');
                    }

                    window.location.href = data.redirect;
                } catch (error) {
                    formError.textContent = error.message;
                    formError.classList.remove('d-none');
                }
            });

            form.querySelectorAll('input, select').forEach((field) => {
                const clearFieldError = () => {
                    const error = form.querySelector(`[data-error-for="${field.name}"]`);
                    const isValidValue = field.value.trim() !== '' && (field.type !== 'email' || field
                        .validity.valid);

                    if (error && isValidValue) {
                        error.textContent = '';
                    }
                };

                field.addEventListener('input', clearFieldError);
                field.addEventListener('change', clearFieldError);
            });
        })();
    </script>
@endpush
