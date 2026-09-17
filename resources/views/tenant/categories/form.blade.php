@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', $category ? 'Edit Category' : 'Add Category')

@section('content')
    <div class="container-fluid">
        <div class="mb-4">
            <p class="text-primary fw-semibold mb-1">Categories</p>
            <h1 class="page-title mb-1">{{ $category ? 'Edit Category' : 'Add Category' }}</h1>
        </div>
        <form data-tenant-category-form data-save-error="Unable to save the category. Please try again." method="POST"
            action="{{ $category ? route('tenant.categories.update', $category->id) : route('tenant.categories.store') }}">
            @csrf
            @if ($category)
                @method('PUT')
            @endif
            <section class="dashboard-card">
                <div class="alert alert-danger d-none" data-form-error role="alert"></div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Category name <span class="text-danger">*</span></label>
                        <input class="form-control" id="name" name="name"
                            value="{{ old('name', $category?->name) }}" maxlength="255">
                        <small class="field-error" data-error-for="name" aria-live="polite"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="slug">Slug</label>
                        <input class="form-control" id="slug" value="{{ $category?->slug }}" readonly
                            aria-describedby="slug-help">
                        <div class="form-text" id="slug-help">Generated automatically from the category name. A number is
                            added if needed to keep it unique.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="parent_id">Parent category</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">No parent category</option>
                            @foreach ($parentCategories as $parentCategory)
                                <option value="{{ $parentCategory->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $parentCategory->id)>
                                    {{ $parentCategory->name ?: 'Unnamed category' }}</option>
                            @endforeach
                        </select>
                        <small class="field-error" data-error-for="parent_id" aria-live="polite"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status">
                            <option value="1" @selected((string) old('status', $category?->status ?? 1) === '1')>Active</option>
                            <option value="0" @selected((string) old('status', $category?->status ?? 1) === '0')>Inactive</option>
                        </select>
                        <small class="field-error" data-error-for="status" aria-live="polite"></small>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4 pt-4 border-top">
                    <a class="btn btn-light" href="{{ route('tenant.categories.index') }}">Cancel</a>
                    <button class="btn btn-primary" type="submit">{{ $category ? 'Update' : 'Submit' }}</button>
                </div>
            </section>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/tenant-user-form.js') }}"></script>
    <script>
        const categoryName = document.getElementById('name');
        const categorySlug = document.getElementById('slug');

        function updateSlug() {
            let slug = categoryName.value.toLowerCase().normalize('NFKD');
            slug = slug.replace(/[\u0300-\u036f]/g, '');
            slug = slug.replace(/@/g, ' at ');
            slug = slug.replace(/[^a-z0-9\s-]/g, '');
            slug = slug.trim().replace(/[\s-]+/g, '-');
            categorySlug.value = slug.slice(0, 240);
        }

        categoryName.addEventListener('input', updateSlug);

        if (!categorySlug.value || @json($errors->any())) {
            updateSlug();
        }
    </script>
@endpush
