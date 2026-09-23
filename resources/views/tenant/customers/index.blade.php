@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Customers')
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Customers</h1>
            @if ($canCreate)
                <a class="btn btn-primary" href="{{ route('tenant.customers.create') }}">Add customer</a>
            @endif
        </div>
        @include('tenant.customers._notifications')
        <section class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <div class="p-3 border-bottom">
                <label class="visually-hidden" for="tenant-customer-search">Search customers by code, name, email or mobile</label>
                <input class="form-control" id="tenant-customer-search" type="search" value="{{ request('search') }}"
                    placeholder="Search customers..." maxlength="254" data-ajax-search>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach (['Customer code', 'Customer name', 'Email', 'Mobile', 'Type', 'Status', 'Joined date', 'Actions'] as $heading)
                                <th>{{ $heading }}</th>
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
                                <td colspan="8" class="text-center text-secondary py-4">No customers found. Try another search or add a customer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($customers->hasPages())
                <div class="border-top p-3">{{ $customers->links() }}</div>
            @endif
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
    <script src="{{ asset('js/customer-management.js') }}"></script>
@endpush
