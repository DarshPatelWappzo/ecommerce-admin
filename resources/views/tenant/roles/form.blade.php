@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', $role->exists ? 'Edit Role' : 'Add Role')

@section('content')
    <div class="container-fluid">
        <div class="mb-4">
            <p class="text-primary fw-semibold mb-1">Role</p>
            <h1 class="page-title mb-1">{{ $role->exists ? 'Edit Role' : 'Add Role' }}</h1>
            <p class="text-secondary mb-0">Set a role name and choose the permissions it can use.</p>
        </div>

        <form data-role-form method="POST"
            action="{{ $role->exists ? route('tenant.roles.update', $role) : route('tenant.roles.store') }}" novalidate>
            @csrf
            @if ($role->exists)
                @method('PUT')
            @endif
            <section class="dashboard-card">
                <div class="alert alert-danger d-none" data-form-error role="alert"></div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label" for="name">Role name <span
                                class="text-danger">*</span></label><input class="form-control" id="name"
                            name="name" value="{{ old('name', $role->name) }}"><small class="field-error"
                            data-error-for="name"></small></div>
                    <div class="col-md-6"><label class="form-label" for="description">Role description</label>
                        <textarea class="form-control" id="description" name="description" rows="1">{{ old('description', $role->description) }}</textarea><small class="field-error" data-error-for="description"></small>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="section-title mb-0">Role permissions <span class="text-danger">*</span></h2><label
                        class="form-check mb-0"><input class="form-check-input" type="checkbox" data-select-all> <span
                            class="form-check-label">Select all</span></label>
                </div>
                <div class="table-responsive border rounded">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Create</th>
                                <th>Update</th>
                                <th>Delete</th>
                                <th>View</th>
                                {{-- <th>Other permissions</th> --}}
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissionsByModule as $module => $permissions)
                                <tr>
                                    <td class="fw-semibold text-capitalize">{{ $module }}</td>
                                    @foreach (['create', 'update', 'delete', 'view'] as $action)
                                        @php $permission = $permissions->first(fn ($item) => str($item->slug)->after('.')->toString() === $action); @endphp
                                        <td>
                                            @if ($permission)
                                                <input class="form-check-input permission-checkbox" type="checkbox"
                                                    name="permissions[]" value="{{ $permission->id }}"
                                                    {{ in_array($permission->id, old('permissions', $selectedPermissions), false) ? 'checked' : '' }}
                                                    aria-label="{{ $permission->name }}">
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        @foreach ($permissions->reject(fn($item) => in_array(str($item->slug)->after('.')->toString(), ['create', 'update', 'delete', 'view'], true)) as $permission)
                                            <label class="form-check form-check-inline"><input
                                                    class="form-check-input permission-checkbox" type="checkbox"
                                                    name="permissions[]" value="{{ $permission->id }}"
                                                    {{ in_array($permission->id, old('permissions', $selectedPermissions), false) ? 'checked' : '' }}>
                                                <span
                                                    class="form-check-label">{{ str($permission->slug)->after('.') }}</span></label>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <small class="field-error" data-error-for="permissions"></small>
                <div class="form-check form-switch mt-4"><input type="hidden" name="status" value="0"><input
                        class="form-check-input" type="checkbox" id="status" name="status" value="1"
                        {{ old('status', $role->status) ? 'checked' : '' }}><label class="form-check-label"
                        for="status">Active role</label></div>
            </section>
            <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light"
                    href="{{ route('tenant.roles.index') }}">Cancel</a><button class="btn btn-primary"
                    type="submit">{{ $role->exists ? 'Update' : 'Submit' }}</button></div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-role-form]');
            const formError = form.querySelector('[data-form-error]');
            const nameField = form.querySelector('[name="name"]');

            form.querySelector('[data-select-all]')?.addEventListener('change', (event) => {
                form.querySelectorAll('.permission-checkbox').forEach((input) => {
                    input.checked = event.target.checked;
                });
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                form.querySelectorAll('[data-error-for]').forEach((error) => {
                    error.textContent = '';
                });
                formError.classList.add('d-none');
                formError.textContent = '';

                if (nameField.value.trim() === '') {
                    form.querySelector('[data-error-for="name"]').textContent = 'Role name is required.';
                    nameField.focus();
                    return;
                }

                if (!form.querySelector('.permission-checkbox:checked')) {
                    form.querySelector('[data-error-for="permissions"]').textContent =
                        'Select at least one permission.';
                    return;
                }

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
                        throw new Error('Unable to save the role. Please try again.');
                    }

                    window.location.href = data.redirect;
                } catch (error) {
                    formError.textContent = error.message;
                    formError.classList.remove('d-none');
                }
            });

            nameField.addEventListener('input', () => {
                form.querySelector('[data-error-for="name"]').textContent = '';
            });

            form.querySelectorAll('.permission-checkbox').forEach((field) => {
                field.addEventListener('change', () => {
                    if (form.querySelector('.permission-checkbox:checked')) {
                        form.querySelector('[data-error-for="permissions"]').textContent = '';
                    }
                });
            });
        })();
    </script>
@endpush
