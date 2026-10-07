@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', 'Categories')

@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <p class="text-primary fw-semibold mb-1">Tenant Administration</p>
                <h1 class="page-title mb-0">Categories</h1>
                <p class="text-secondary mb-0">Browse your product categories.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.categories.create') }}">
                        <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add category
                    </a>
                @endif
                <x-filter-button :filters="['search', 'parent_id', 'status', 'from', 'to']" />
            </div>
        </div>
        <x-filter-offcanvas :action="route('tenant.categories.index')" :filters="['search', 'parent_id', 'status', 'from', 'to']">
            <x-filter-field name="search" label="Search" type="search" maxlength="200" />
            <x-filter-field name="parent_id" label="Parent category ID" type="number" min="1" />
            <x-filter-field name="status" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" />
            <x-filter-field name="from" label="Created from" type="date" />
            <x-filter-field name="to" label="Created to" type="date" />
        </x-filter-offcanvas>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <section class="dashboard-card listing-table-card">
            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Sr No</th>
                            <th scope="col">Category Name</th>
                            <th scope="col">Parent Category</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>{{ $categories->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $category->name ?: 'Unnamed category' }}</td>
                                <td>{{ $category->parent_name ?: 'Root' }}</td>
                                <td>
                                    @if ($canEdit)
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('tenant.categories.edit', $category->id) }}"
                                            aria-label="Edit {{ $category->name }}">
                                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                        </a>
                                    @else
                                        <span class="text-secondary" aria-label="No actions available">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="listing-empty">No categories found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($categories->hasPages())
                <div class="listing-table-footer">
                    {{ $categories->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
