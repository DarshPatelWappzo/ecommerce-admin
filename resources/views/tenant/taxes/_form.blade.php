<form id="tenant-tax-form" data-tenant-tax-form method="POST" novalidate
    action="{{ $tax->exists ? route('tenant.taxes.update', $tax) : route('tenant.taxes.store') }}">
    @csrf
    @if ($tax->exists)
        @method('PUT')
    @endif
    <section class="dashboard-card">
        @include('tenant.partials.validation-errors')
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label" for="name">Tax Name <span class="text-danger">*</span></label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $tax->name) }}"
                    maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="code">Tax Code <span class="text-danger">*</span></label>
                <input class="form-control" id="code" name="code" value="{{ old('code', $tax->code) }}"
                    maxlength="50" pattern="[A-Za-z0-9_\-]+" aria-describedby="code-help">
                <div class="form-text" id="code-help">Letters, numbers, underscores and hyphens. Saved in uppercase.
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="rate">Rate (%) <span class="text-danger">*</span></label>
                <input class="form-control" type="number" id="rate" name="rate"
                    value="{{ old('rate', $tax->rate) }}" min="0" max="100" step="0.0001"
                    aria-describedby="rate-help">
                <div class="form-text" id="rate-help">Enter 0 to 100, with up to four decimal places.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                <select class="form-select" id="is_active" name="is_active">
                    <option value="1" @selected((string) old('is_active', (int) $tax->is_active) === '1')>Active</option>
                    <option value="0" @selected((string) old('is_active', (int) $tax->is_active) === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="1000">{{ old('description', $tax->description) }}</textarea>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4 pt-4 border-top">
            <a class="btn btn-light" href="{{ route('tenant.taxes.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">{{ $tax->exists ? 'Update' : 'Save' }}</button>
        </div>
    </section>
</form>

@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::formRequest(\App\Http\Requests\TenantTaxSaveRequest::class, '#tenant-tax-form') !!}
@endpush
