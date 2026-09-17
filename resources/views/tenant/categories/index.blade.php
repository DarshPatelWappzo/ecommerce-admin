@extends('layouts.app', [
    'sidebarView' => 'tenant.partials.sidebar',
    'headerView' => 'tenant.partials.header',
])

@section('title', 'Categories')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <p class="text-primary fw-semibold mb-1">Tenant Administration</p>
                <h1 class="page-title mb-1">Categories</h1>
                <p class="text-secondary mb-0">Browse your product categories.</p>
            </div>
            @if ($canCreate)
                <a class="btn btn-primary mt-3" href="{{ route('tenant.categories.create') }}">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add category
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <section class="dashboard-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
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
                                <td class="text-center text-secondary py-4" colspan="4">No categories found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($categories->hasPages())
                <div class="p-3 border-top">
                    {{ $categories->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
