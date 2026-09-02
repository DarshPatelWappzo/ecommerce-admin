@extends('layouts.app')

@section('title', 'Packages')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="page-title mb-1">Packages</h1>
                <p class="text-secondary mb-0">Manage packages available to your users.</p>
            </div>
            <a class="btn btn-primary" href="{{ route('super-admin.package.create') }}">
                <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add package
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <div class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <div class="p-3 border-bottom">
                <label class="visually-hidden" for="package-search">Search packages</label>
                <div class="input-group">
                    {{-- <span class="input-group-text"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span> --}}
                    <input class="form-control" id="package-search" type="search" value="{{ request('search') }}"
                        placeholder="Search packages..." data-ajax-search>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table user-table align-middle mb-0">
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
                                        <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-2 text-center text-secondary" colspan="8">No packages found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($packages->hasPages())
                <div class="border-top p-3">{{ $packages->links() }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
