@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $product ? 'Edit Product' : 'Add Product')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">{{ $product ? 'Edit Product' : 'Add Product' }}</h1>
        <form data-product-form method="POST" enctype="multipart/form-data" novalidate
            action="{{ $product ? route('tenant.products.update', $product) : route('tenant.products.store') }}"
            data-product="{{ json_encode($product?->toArray()) }}" data-catalog="{{ json_encode($catalog) }}">
            @csrf @if ($product)
                @method('PUT')
            @endif
            <div class="alert alert-danger d-none" data-form-error role="alert"></div>
            <section class="dashboard-card mb-4">
                <h2 class="section-title">Basic information</h2>
                <div class="row g-3">
                    @foreach (['name' => 'Product name', 'slug' => 'Slug'] as $field => $label)
                        <div class="col-md-6"><label class="form-label"
                                for="{{ $field }}">{{ $label }}</label><input class="form-control"
                                id="{{ $field }}" name="{{ $field }}" value="{{ $product?->$field }}"><span
                                class="field-error" data-error-for="{{ $field }}"></span></div>
                    @endforeach
                    <div class="col-md-4"><label class="form-label" for="product_type">Product type</label><select
                            class="form-select" id="product_type" name="product_type">
                            <option value="simple" @selected($product?->product_type !== 'configurable')>Simple</option>
                            <option value="configurable" @selected($product?->product_type === 'configurable')>Configurable</option>
                        </select><span class="field-error" data-error-for="product_type"></span></div>
                    <div class="col-md-4"><label class="form-label" for="status">Status</label><select class="form-select"
                            id="status" name="status">
                            <option value="1" @selected($product?->status ?? true)>Active</option>
                            <option value="0" @selected($product && !$product->status)>Inactive</option>
                        </select></div>
                    <div class="col-md-4 d-flex align-items-end"><label class="form-check"><input class="form-check-input"
                                type="checkbox" name="featured" value="1" @checked($product?->featured)> Featured
                            product</label></div>
                    <div class="col-md-6">
                        <label class="form-label" for="tax_id">Tax</label>
                        <select class="form-select" id="tax_id" name="tax_id" data-allow-clear="true">
                            <option value="">Tax not configured</option>
                            @foreach ($catalog['taxes'] as $tax)
                                <option value="{{ $tax->id }}" @selected((string) old('tax_id', $product?->tax_id) === (string) $tax->id)>{{ $tax->name }} —
                                    {{ rtrim(rtrim($tax->rate, '0'), '.') }}%{{ $tax->trashed() ? ' (Deleted)' : ($tax->is_active ? '' : ' (Inactive)') }}
                                </option>
                            @endforeach
                        </select>
                        @error('tax_id')
                            <div class="alert alert-danger mt-2" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="hsn_code">HSN code (optional)</label>
                        <input class="form-control" id="hsn_code" name="hsn_code" maxlength="20"
                            value="{{ old('hsn_code', $product?->hsn_code) }}">
                    </div>
                    @foreach (['short_description' => 'Short description', 'description' => 'Description'] as $field => $label)
                        <div class="col-12"><label class="form-label"
                                for="{{ $field }}">{{ $label }}</label>
                            <textarea class="form-control" id="{{ $field }}" name="{{ $field }}"
                                rows="{{ $field === 'description' ? 5 : 2 }}">{{ $product?->$field }}</textarea><span class="field-error"
                                data-error-for="{{ $field }}"></span>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="dashboard-card mb-4">
                <h2 class="section-title">Return &amp; Replacement Policy</h2>
                <div class="row g-3">
                    @foreach (['is_returnable' => ['Returnable', 'return_days', 'Return Period (Days)'], 'is_replaceable' => ['Replaceable', 'replacement_days', 'Replacement Period (Days)']] as $flag => [$label, $days, $periodLabel])
                        @php($enabled = (bool) old($flag, $product?->$flag ?? false))
                        <div class="col-md-6" data-product-policy>
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="{{ $flag }}" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $flag }}"
                                    name="{{ $flag }}" value="1" data-policy-switch
                                    @checked($enabled)>
                                <label class="form-check-label" for="{{ $flag }}">{{ $label }}</label>
                            </div>
                            <span class="field-error" data-error-for="{{ $flag }}"></span>
                            <div data-policy-period @class(['d-none' => !$enabled])>
                                <label class="form-label" for="{{ $days }}">{{ $periodLabel }}</label>
                                <input class="form-control" type="number" min="1" max="4294967295" step="1"
                                    id="{{ $days }}" name="{{ $days }}" placeholder="7"
                                    value="{{ $enabled ? old($days, $product?->$days) : '' }}"
                                    @disabled(!$enabled) @required($enabled)>
                                <span class="field-error" data-error-for="{{ $days }}"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="dashboard-card mb-4">
                <h2 class="section-title">Organization</h2>
                <div class="row g-3">
                    @foreach (['categories' => 'category_ids', 'tags' => 'tag_ids', 'related_products' => 'related_product_ids'] as $key => $field)
                        <div class="col-md-4"><label class="form-label"
                                for="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $key)) }}</label><select
                                class="form-select" id="{{ $field }}" name="{{ $field }}[]" multiple>
                                @foreach ($catalog[$key] as $option)
                                    <option value="{{ $option->id }}" @selected($product && ($key === 'related_products' ? $product->relatedProducts : $product->$key)->contains('id', $option->id))>{{ $option->name }}
                                    </option>
                                @endforeach
                            </select><span class="field-error" data-error-for="{{ $field }}"></span></div>
                    @endforeach
                </div>
                <a class="small d-inline-block mt-3" href="{{ route('tenant.catalog.index') }}" target="_blank"
                    rel="noopener">Manage reusable tags and attributes</a>
            </section>
            <section class="dashboard-card mb-4">
                <div class="d-flex justify-content-between">
                    <h2 class="section-title">Sellable variants</h2><button class="btn btn-sm btn-outline-primary"
                        type="button" data-add-variant>Add variant</button>
                </div>
                <p class="text-secondary small">Simple products have one SKU. For configurable products, add only the
                    combinations you want to sell. Quantities are managed per variant.</p>
                <span class="field-error" data-error-for="variants"></span>
                <div data-variants></div>
            </section>
            <section class="dashboard-card mb-4">
                <div class="d-flex justify-content-between">
                    <h2 class="section-title">Product attributes</h2><button class="btn btn-sm btn-outline-primary"
                        type="button" data-add-attribute>Add attribute</button>
                </div>
                <div data-attributes></div>
            </section>
            <section class="dashboard-card mb-4">
                <div class="d-flex justify-content-between">
                    <h2 class="section-title">Images</h2><button class="btn btn-sm btn-outline-primary" type="button"
                        data-add-image>Add image</button>
                </div>
                <p class="text-secondary small">JPEG, PNG or WebP, up to 5 MB each. Choose one primary image. Lower sort
                    numbers appear first.</p>
                <span class="field-error" data-error-for="images"></span>
                <div data-images></div>
            </section>
            <section class="dashboard-card mb-4">
                <h2 class="section-title">SEO</h2>
                @foreach (['meta_title' => 'Meta title', 'meta_description' => 'Meta description'] as $field => $label)
                    <label class="form-label mt-2" for="{{ $field }}">{{ $label }}</label><input
                        class="form-control" id="{{ $field }}" name="{{ $field }}"
                        value="{{ $product?->$field }}"><span class="field-error"
                        data-error-for="{{ $field }}"></span>
                @endforeach
            </section>
            <div class="d-flex justify-content-end gap-2 mb-4"><a class="btn btn-light"
                    href="{{ route('tenant.products.index') }}">Cancel</a><button class="btn btn-primary"
                    type="submit">Save product</button></div>
        </form>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::make(
        ['tax_id' => ['nullable', 'integer', Illuminate\Validation\Rule::in($catalog['taxes']->modelKeys())]],
        [],
        [],
        '[data-product-form]',
    ) !!}
    <script src="{{ asset('js/product-form.js') }}"></script>
@endpush
