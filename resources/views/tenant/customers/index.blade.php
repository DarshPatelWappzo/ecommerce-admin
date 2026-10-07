@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Customers')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <h1 class="page-title mb-0">Customers</h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.customers.create') }}">Add customer</a>
                @endif
                <x-filter-button :filters="['search', 'status', 'customer_type', 'joined_from', 'joined_to']" />
            </div>
        </div>
        <x-filter-offcanvas :show-errors="false" :action="route('tenant.customers.index')" :filters="['search', 'status', 'customer_type', 'joined_from', 'joined_to', 'sort', 'direction']">
            <x-filter-field name="search" label="Search" type="search" maxlength="200" />
            <x-filter-field name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
            <x-filter-field name="customer_type" label="Customer type" :options="['individual' => 'Individual', 'business' => 'Business']" />
            <x-filter-field name="joined_from" label="Joined from" type="date" />
            <x-filter-field name="joined_to" label="Joined to" type="date" />
            <x-filter-field name="sort" label="Sort" :options="[
                'created_at' => 'Created date',
                'customer_code' => 'Customer code',
                'first_name' => 'First name',
            ]" />
            <x-filter-field name="direction" label="Direction" :options="['asc' => 'Ascending', 'desc' => 'Descending']" />
        </x-filter-offcanvas>

        @include('tenant.customers._notifications')
        <section class="dashboard-card listing-table-card" data-ajax-pagination-container>

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach (['Customer code', 'Customer name', 'Email', 'Mobile', 'Type', 'Status', 'Joined date', 'Actions'] as $heading)
                                <th scope="col">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td>{{ $customer->customer_code }}</td>
                                <td>{{ $customer->first_name }} {{ $customer->last_name }}</td>
                                <td>{{ $customer->email ?? '—' }}</td>
                                <td>{{ $customer->phone ? $customer->phone_country_code . ' ' . $customer->phone : '—' }}
                                </td>
                                <td>{{ ucfirst($customer->customer_type) }}</td>
                                <td><span
                                        class="badge {{ $customer->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($customer->status) }}</span>
                                </td>
                                <td>{{ $customer->created_at->format('d M Y') }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('tenant.customers.show', $customer) }}">View</a>
                                        @include('tenant.customers._actions')
                                    </div>
                                </td>
                            </tr>
                        @empty<tr>
                                <td colspan="8" class="listing-empty">No customers found. Try another search or add a
                                    customer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="listing-table-footer">{{ $customers->links() }}</div>
            @endif
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
    <script src="{{ asset('js/customer-management.js') }}"></script>
@endpush
