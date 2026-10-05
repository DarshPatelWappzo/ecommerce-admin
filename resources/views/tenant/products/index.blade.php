@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Products')
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Products</h1>
            <div class="d-flex gap-2">
                @if ($canCreate)
                    <a class="btn btn-outline-secondary" href="{{ route('tenant.catalog.index') }}">Tags &amp; attributes</a>
                    <a class="btn btn-primary" href="{{ route('tenant.products.create') }}">Add product</a>
                @endif
            </div>
        </div>
        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        <div class="alert alert-danger d-none" data-list-error role="alert"></div>
        {{-- <form class="dashboard-card mb-4" method="GET" action="{{ route('tenant.products.index') }}">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label" for="search">Name or SKU</label><input
                        class="form-control" id="search" name="search" value="{{ $filters['search'] ?? '' }}"></div>
                @foreach (['category' => 'categories', 'tag' => 'tags'] as $field => $items)
                    <div class="col-md-3"><label class="form-label" for="{{ $field }}">{{ ucfirst($field) }}</label>
                        <select class="form-select" id="{{ $field }}" name="{{ $field }}">
                            <option value="">All</option>
                            @foreach ($catalog[$items] as $option)
                                <option value="{{ $option->id }}" @selected(($filters[$field] ?? '') == $option->id)>{{ $option->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                @foreach (['status' => ['1' => 'Active', '0' => 'Inactive'], 'product_type' => ['simple' => 'Simple', 'configurable' => 'Configurable'], 'stock' => ['in_stock' => 'In stock', 'out_of_stock' => 'Out of stock', 'low_stock' => 'Low stock'], 'sort' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'name' => 'Name', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low']] as $field => $choices)
                    <div class="col-md-3"><label class="form-label"
                            for="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</label>
                        <select class="form-select" name="{{ $field }}" id="{{ $field }}">
                            <option value="">All / default</option>
                            @foreach ($choices as $value => $label)
                                <option value="{{ $value }}" @selected((string) ($filters[$field] ?? '') === (string) $value)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                @foreach (['min_price' => 'Minimum price', 'max_price' => 'Maximum price'] as $field => $label)
                    <div class="col-md-3"><label class="form-label"
                            for="{{ $field }}">{{ $label }}</label><input class="form-control"
                            type="number" min="0" step="0.01" id="{{ $field }}"
                            name="{{ $field }}" value="{{ $filters[$field] ?? '' }}"></div>
                @endforeach
                <div class="col-12"><button class="btn btn-primary">Filter</button> <a class="btn btn-light"
                        href="{{ route('tenant.products.index') }}">Reset</a></div>
            </div>
        </form> --}}
        <section class="dashboard-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name / SKU</th>
                            <th>Type</th>
                            <th>Categories</th>
                            <th>Price from</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Return / Replacement</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    @if ($image = $product->images->firstWhere('is_primary', true))
                                        <img src="{{ $image->url }}" alt="{{ $image->alt_text ?: $product->name }}"
                                            width="56" height="56" class="rounded object-fit-cover">
                                    @else
                                        <span class="text-secondary small">No image</span>
                                    @endif
                                </td>
                                <td><strong>{{ $product->name }}</strong>
                                    <div class="text-secondary small">{{ $product->variants->pluck('sku')->join(', ') }}
                                    </div>
                                </td>
                                <td>{{ ucfirst($product->product_type) }}</td>
                                <td>{{ $product->categories->pluck('name')->join(', ') ?: 'Uncategorized' }}</td>
                                <td>{{ number_format((float) $product->variants->min('price'), 2) }}</td>
                                <td>{{ $product->variants->sum('quantity') }}<div class="small text-secondary">
                                        {{ $product->variants->sum('reserved_quantity') }} reserved</div>
                                </td>
                                <td><span
                                        class="badge text-bg-{{ $product->status ? 'success' : 'secondary' }}">{{ $product->status ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="small text-nowrap">
                                    <div>Return: {{ $product->is_returnable ? $product->return_days . ' Days' : 'No' }}</div>
                                    <div>Replacement:
                                        {{ $product->is_replaceable ? $product->replacement_days . ' Days' : 'No' }}</div>
                                </td>
                                <td>{{ $product->created_at->format('d M Y') }}</td>
                                <td>
                                    @if ($canUpdate)
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('tenant.products.edit', $product) }}">Edit</a>
                                    @endif
                                    @if ($canDelete)
                                        <form class="d-inline" method="POST"
                                            action="{{ route('tenant.products.destroy', $product) }}" data-product-delete>
                                            @csrf @method('DELETE')<button
                                                class="btn btn-sm btn-outline-danger">Delete</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty<tr>
                                <td colspan="10" class="text-center py-4">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $products->links() }}</div>
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/product-form.js') }}"></script>
@endpush
