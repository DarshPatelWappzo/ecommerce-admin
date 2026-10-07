@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])

@section('title', 'Taxes')

@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <p class="text-primary fw-semibold mb-1">Tenant Administration</p>
                <h1 class="page-title mb-0">Taxes</h1>
                <p class="text-secondary mb-0">Manage percentage tax rates.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($canCreate)
                <a class="btn btn-primary" href="{{ route('tenant.taxes.create') }}">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add Tax
                </a>
                @endif
                <x-filter-button :filters="['search', 'is_active']" />
            </div>
        </div>
        <x-filter-offcanvas :action="route('tenant.taxes.index')" :filters="['search', 'is_active']">
            <x-filter-field name="search" label="Name or code" type="search" maxlength="100" />
            <x-filter-field name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" />
        </x-filter-offcanvas>

        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $style)
            @if (session($key))
                <div class="alert alert-{{ $style }}" role="alert">{{ session($key) }}</div>
            @endif
        @endforeach
        <section class="dashboard-card listing-table-card" data-ajax-pagination-container>

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">No.</th>
                            <th scope="col">Tax Name</th>
                            <th scope="col">Code</th>
                            <th scope="col">Rate (%)</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($taxes as $tax)
                            <tr>
                                <td>{{ $taxes->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $tax->name }}</td>
                                <td>{{ $tax->code }}</td>
                                <td>{{ $tax->rate }}</td>
                                <td><span
                                        class="badge {{ $tax->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $tax->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>
                                    @if ($canEdit)
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('tenant.taxes.edit', $tax) }}"
                                            aria-label="Edit {{ $tax->name }}"><i class="fa-solid fa-pen-to-square"
                                                aria-hidden="true"></i></a>
                                    @else
                                        <span class="text-secondary" aria-label="No actions available">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="listing-empty">No taxes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($taxes->hasPages())
                <div class="listing-table-footer">{{ $taxes->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
