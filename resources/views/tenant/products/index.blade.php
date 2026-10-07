@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Products')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <h1 class="page-title mb-0">Products</h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($canCreate)
                    <a class="btn btn-outline-secondary" href="{{ route('tenant.catalog.index') }}">Tags &amp; attributes</a>
                    <a class="btn btn-primary" href="{{ route('tenant.products.create') }}">Add product</a>
                @endif
                <x-filter-button :filters="['search', 'category', 'tag', 'status', 'product_type', 'stock', 'min_price', 'max_price']" />
            </div>
        </div>
        <x-filter-offcanvas :action="route('tenant.products.index')" :filters="['search', 'category', 'tag', 'status', 'product_type', 'stock', 'min_price', 'max_price', 'sort']">
            <x-filter-field name="search" label="Name or SKU" type="search" maxlength="191" />
            <x-filter-field name="category" label="Category" :options="$catalog['categories']->pluck('name', 'id')" />
            <x-filter-field name="tag" label="Tag" :options="$catalog['tags']->pluck('name', 'id')" />
            <x-filter-field name="status" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" />
            <x-filter-field name="product_type" label="Product type" :options="['simple' => 'Simple', 'configurable' => 'Configurable']" />
            <x-filter-field name="stock" label="Stock" :options="['in_stock' => 'In Stock', 'out_of_stock' => 'Out Of Stock', 'low_stock' => 'Low Stock']" />
            <x-filter-field name="min_price" label="Minimum price" type="number" min="0" step="0.01" />
            <x-filter-field name="max_price" label="Maximum price" type="number" min="0" step="0.01" />
            <x-filter-field name="sort" label="Sort" :options="[
                'newest' => 'Newest',
                'oldest' => 'Oldest',
                'name' => 'Name',
                'price_asc' => 'Price: low to high',
                'price_desc' => 'Price: high to low',
            ]" />
        </x-filter-offcanvas>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        <div class="alert alert-danger d-none" data-list-error role="alert"></div>

        <section class="dashboard-card listing-table-card">
            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Image</th>
                            <th scope="col">Name / SKU</th>
                            <th scope="col">Type</th>
                            <th scope="col">Categories</th>
                            <th scope="col">Price from</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Status</th>
                            <th scope="col">Return / Replacement</th>
                            <th scope="col">Created</th>
                            <th scope="col">Actions</th>
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
                                    <div>Return: {{ $product->is_returnable ? $product->return_days . ' Days' : 'No' }}
                                    </div>
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
                                <td colspan="10" class="listing-empty">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="listing-table-footer">{{ $products->links() }}</div>
            @endif
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/product-form.js') }}"></script>
@endpush
