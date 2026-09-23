@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Tags & Attributes')
@section('content')
    <div class="container-fluid" data-catalog-editor
        data-catalog="{{ json_encode(['tags' => $tags, 'attributes' => $attributes]) }}">
        <div class="d-flex justify-content-between mb-4">
            <h1 class="page-title">Tags &amp; attributes</h1><a class="btn btn-light"
                href="{{ route('tenant.products.index') }}">Products</a>
        </div>
        <p class="text-secondary">Tags are flexible labels such as ?Best Seller?. Use attributes for Color, Size, Storage,
            and other product characteristics.</p>
        <div class="row g-4">
            @foreach (['tags' => 'Tag', 'attributes' => 'Attribute'] as $type => $label)
                <div class="col-lg-6">
                    <section class="dashboard-card">
                        <h2 class="section-title">{{ $label }}</h2>
                        <form data-catalog-form="{{ $type }}" method="POST"
                            action="{{ route('tenant.catalog.' . $type) }}" novalidate>
                            @csrf
                            <div class="alert alert-danger d-none" data-form-error role="alert"></div>
                            <div class="alert alert-success d-none" data-success role="status"></div>
                            <label class="form-label" for="{{ $type }}-record">Add or edit</label>
                            <select class="form-select mb-3" id="{{ $type }}-record" name="id"
                                data-select2-disabled>
                                <option value="">New {{ strtolower($label) }}</option>
                                @foreach ($$type as $record)
                                    <option value="{{ $record->id }}">{{ $record->name }}</option>
                                @endforeach
                            </select>
                            <label class="form-label" for="{{ $type }}-name">Name</label><input
                                class="form-control mb-3" id="{{ $type }}-name" name="name">
                            @if ($type === 'tags')
                                <label class="form-label" for="tag-slug">Slug</label><input class="form-control mb-3"
                                    id="tag-slug" name="slug">
                            @else
                                <label class="form-label" for="attribute-code">Code</label><input class="form-control mb-3"
                                    id="attribute-code" name="code" placeholder="e.g. color">
                                <label class="form-label" for="attribute-type">Type</label><select class="form-select mb-3"
                                    id="attribute-type" name="type" data-select2-disabled>
                                    @foreach (['select', 'text', 'number', 'boolean', 'date'] as $value)
                                        <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                                    @endforeach
                                </select>
                                <label class="form-check mb-2"><input class="form-check-input" type="checkbox"
                                        name="is_variant"> Used for variants</label>
                                <label class="form-check mb-3"><input class="form-check-input" type="checkbox"
                                        name="is_filterable"> Filterable</label>
                                <div data-option-fields>
                                    <h3 class="h6">Options for select attributes</h3>
                                    <div data-options></div>
                                    <button class="btn btn-sm btn-outline-secondary mb-3" type="button" data-add-option>Add
                                        option</button>
                                </div>
                            @endif
                            <label class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="status" checked>
                                Active
                            </label>
                            <button class="btn btn-primary" type="submit">Save {{ strtolower($label) }}</button>
                        </form>
                    </section>
                </div>
            @endforeach
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/product-catalog.js') }}"></script>
@endpush
