@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])

@section('title', 'Taxes')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <p class="text-primary fw-semibold mb-1">Tenant Administration</p>
                <h1 class="page-title mb-1">Taxes</h1>
                <p class="text-secondary mb-0">Manage percentage tax rates.</p>
            </div>
            @if ($canCreate)
                <a class="btn btn-primary" href="{{ route('tenant.taxes.create') }}">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add Tax
                </a>
            @endif
        </div>
        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $style)
            @if (session($key))
                <div class="alert alert-{{ $style }}" role="alert">{{ session($key) }}</div>
            @endif
        @endforeach
        <section class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <div class="p-3 border-bottom">
                <label class="visually-hidden" for="tenant-tax-search">Search taxes by name or code</label>
                <input class="form-control" id="tenant-tax-search" type="search" value="{{ request('search') }}"
                    placeholder="Search taxes..." maxlength="100" data-ajax-search>
                @error('search')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
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
                                <td class="text-center text-secondary py-4" colspan="6">No taxes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($taxes->hasPages())
                <div class="p-3 border-top">{{ $taxes->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
