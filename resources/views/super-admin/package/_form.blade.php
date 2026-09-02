<form data-package-form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="alert alert-danger d-none" data-form-error role="alert"></div>

    <input name="sort_order" type="hidden" value="{{ old('sort_order', $package?->sort_order ?? 0) }}">
    <small class="field-error" data-error-for="sort_order">
        @error('sort_order')
            {{ $message }}
        @enderror
    </small>

    <div class="row g-4">
        @foreach (['name' => 'Package name', 'slug' => 'Slug', 'infrastructure_summary' => 'Infrastructure summary'] as $field => $label)
            <div class="col-md-{{ $field === 'infrastructure_summary' ? '12' : '6' }}">
                <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                @if ($field === 'infrastructure_summary')
                    <textarea class="form-control" id="{{ $field }}" name="{{ $field }}" rows="3">{{ old($field, $package?->{$field}) }}</textarea>
                @else
                    <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="text"
                        value="{{ old($field, $package?->{$field}) }}" @readonly($field === 'slug')>
                @endif
                <small class="field-error" data-error-for="{{ $field }}">
                    @error($field)
                        {{ $message }}
                    @enderror
                </small>
            </div>
        @endforeach
        @foreach (['min_monthly_users' => 'Min monthly users', 'max_monthly_users' => 'Max monthly users', 'min_monthly_cost' => 'Min monthly cost', 'max_monthly_cost' => 'Max monthly cost', 'application_servers' => 'Application servers'] as $field => $label)
            <div class="col-md-4">
                <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="text"
                    min="0" step="0.01" value="{{ old($field, $package?->{$field}) }}">
                <small class="field-error" data-error-for="{{ $field }}">
                    @error($field)
                        {{ $message }}
                    @enderror
                </small>
            </div>
        @endforeach
        @foreach (['cpu_vcores' => 'CPU vCores', 'ram_gb' => 'RAM (GB)', 'bandwidth_gb' => 'Bandwidth (GB)', 'storage_gb' => 'Storage (GB)'] as $field => $label)
            <div class="col-md-3">
                <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="text"
                    min="0" step="0.01" value="{{ old($field, $package?->{$field}) }}">
                <small class="field-error" data-error-for="{{ $field }}">
                    @error($field)
                        {{ $message }}
                    @enderror
                </small>
            </div>
        @endforeach
        <div class="col-md-4">
            <label class="form-label" for="billing_period">Billing period</label>
            <select class="form-select" id="billing_period" name="billing_period">
                <option value="monthly" @selected(old('billing_period', $package?->billing_period ?? 'monthly') === 'monthly')>Monthly</option>
                <option value="yearly" @selected(old('billing_period', $package?->billing_period) === 'yearly')>Yearly</option>
            </select>
            <small class="field-error" data-error-for="billing_period">
                @error('billing_period')
                    {{ $message }}
                @enderror
            </small>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="currency">Currency</label>
            <input class="form-control" id="currency" name="currency" type="text" maxlength="3"
                value="{{ old('currency', $package?->currency ?? 'INR') }}">
            <small class="field-error" data-error-for="currency">
                @error('currency')
                    {{ $message }}
                @enderror
            </small>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="active" @selected(old('status', $package?->status ?? 'active') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $package?->status) === 'inactive')>Inactive</option>
            </select>
            <small class="field-error" data-error-for="status">
                @error('status')
                    {{ $message }}
                @enderror
            </small>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="database_type">Database type</label>
            <input class="form-control" id="database_type" name="database_type" type="text"
                value="{{ old('database_type', $package?->database_type) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $package?->description) }}</textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="cost_disclaimer">Cost disclaimer</label>
            <textarea class="form-control" id="cost_disclaimer" name="cost_disclaimer" rows="3">{{ old('cost_disclaimer', $package?->cost_disclaimer) }}</textarea>
        </div>
        <div class="col-12">
            <div class="d-flex flex-wrap gap-4">
                @foreach (['backup_included' => 'Backup included', 'cdn_included' => 'CDN included', 'load_balancer_included' => 'Load balancer included', 'is_recommended' => 'Recommended package'] as $field => $label)
                    <div class="form-check">
                        <input name="{{ $field }}" type="hidden" value="0">
                        <input class="form-check-input" id="{{ $field }}" name="{{ $field }}"
                            type="checkbox" value="1" @checked(old($field, $package?->{$field} ?? false))>
                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4 pt-4 border-top">
        <a class="btn btn-light" href="{{ route('super-admin.package.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-check me-1" aria-hidden="true"></i>{{ $submitLabel }}
        </button>
    </div>
</form>

@once
    @push('scripts')
        <script>
            (() => {
                const form = document.querySelector('[data-package-form]');
                const formError = form.querySelector('[data-form-error]');
                const nameField = form.querySelector('[name="name"]');
                const slugField = form.querySelector('[name="slug"]');

                const generateSlug = (value) => value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');

                nameField.addEventListener('input', () => {
                    slugField.value = generateSlug(nameField.value);
                });

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
                            throw new Error('Unable to save the package. Please try again.');
                        }

                        window.location.href = data.redirect;
                    } catch (error) {
                        formError.textContent = error.message;
                        formError.classList.remove('d-none');
                    }
                });

                form.querySelectorAll('input, textarea, select').forEach((field) => {
                    const clearFieldError = () => {
                        const error = form.querySelector(`[data-error-for="${field.name}"]`);
                        if (error && field.value.trim() !== '') {
                            error.textContent = '';
                        }
                    };

                    field.addEventListener('input', clearFieldError);
                    field.addEventListener('change', clearFieldError);
                });
            })
            ();
        </script>
    @endpush
@endonce
