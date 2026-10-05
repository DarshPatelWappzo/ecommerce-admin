@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Orders')
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Orders</h1>
            @if ($permissions['create'])
                <a class="btn btn-primary" href="{{ route('tenant.orders.create') }}">Add order</a>
            @endif
        </div>
        @include('tenant.customers._notifications')
        <section class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <div class="p-3 border-bottom">
                <label class="visually-hidden" for="tenant-order-search">Search orders</label>
                <input class="form-control" id="tenant-order-search" type="search" value="{{ request('search') }}"
                    placeholder="Search orders..." maxlength="200" data-ajax-search>
            </div>
            <div class="table-responsive">
                <table class="table user-table align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach (['Order number', 'Customer', 'Date', 'Total', 'Payment', 'Status', 'Actions'] as $heading)
                                <th>{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ $order->customer_name }}</td>
                                <td>{{ $order->order_date->format('d M Y H:i') }}</td>
                                <td>INR {{ $order->grand_total }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $order->payment_status)) }}</td>
                                <td>{{ ucfirst($order->status) }}</td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-light"
                                        href="{{ route('tenant.orders.show', $order) }}">View</a>
                                    @if ($order->status === 'draft' && $permissions['update'])
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="{{ route('tenant.orders.edit', $order) }}">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @empty<tr>
                                <td colspan="7" class="text-center text-muted py-5">No orders match your search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="border-top p-3">{{ $orders->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
