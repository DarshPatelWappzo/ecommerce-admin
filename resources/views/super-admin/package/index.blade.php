@extends('layouts.app')

@section('title', 'Packages')

@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <h1 class="page-title mb-0">Packages</h1>
                <p class="text-secondary mb-0">Manage packages available to your users.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a class="btn btn-primary" href="{{ route('super-admin.package.create') }}">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add package
                </a>
                <x-filter-button :filters="['search', 'status']" />
            </div>
        </div>
        <x-filter-offcanvas :action="route('super-admin.package.index')" :filters="['search', 'status']">
            <x-filter-field name="search" label="Search" type="search" maxlength="200" />
            <x-filter-field name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
        </x-filter-offcanvas>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <div class="dashboard-card listing-table-card" data-ajax-pagination-container>
            <x-listing-search :action="route('super-admin.package.index')" label="Search packages..." :count="$packages->total()" />

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">S. No.</th>
                            <th scope="col">Name</th>
                            {{-- <th scope="col">Description</th> --}}
                            <th scope="col">Price</th>
                            <th scope="col">RAM</th>
                            <th scope="col">Storage</th>
                            <th scope="col">CPU Vcores</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($packages as $package)
                            <tr>
                                <td>{{ $packages->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $package->name }}</td>
                                {{-- <td>{{ $package->description }}</td> --}}
                                <td>{{ number_format((float) $package->min_monthly_cost, 2) }} -
                                    {{ number_format((float) $package->max_monthly_cost, 2) }}
                                </td>
                                <td> {{ $package->ram_gb }}</td>
                                <td> {{ $package->storage_gb }}</td>
                                <td> {{ $package->cpu_vcores }}</td>
                                <td>
                                    <span
                                        class="badge rounded-pill text-bg-{{ $package->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($package->status) }}
                                    </span>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('super-admin.package.edit', $package) }}">
                                        <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="listing-empty">No packages found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($packages->hasPages())
                <div class="listing-table-footer">{{ $packages->links() }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
