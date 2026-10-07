@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Orders')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <h1 class="page-title mb-0">Orders</h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($permissions['create'])
                    <a class="btn btn-primary" href="{{ route('tenant.orders.create') }}">Add order</a>
                @endif
                <x-filter-button :filters="['search', 'status', 'payment_status', 'payment_method', 'from', 'to']" />
            </div>
        </div>
        <x-filter-offcanvas :show-errors="false" :action="route('tenant.orders.index')" :filters="['search', 'status', 'payment_status', 'payment_method', 'from', 'to']">
            <x-filter-field name="search" label="Order number or customer" type="search" maxlength="200" />
            <x-filter-field name="status" label="Order status" :options="collect(array_keys(\App\Models\Tenant\Order::TRANSITIONS))->mapWithKeys(
                fn($value) => [$value => \Illuminate\Support\Str::headline($value)],
            )" />
            <x-filter-field name="payment_status" label="Payment status" :options="[
                'unpaid' => 'Unpaid',
                'pending' => 'Pending',
                'failed' => 'Failed',
                'partially_paid' => 'Partially Paid',
                'paid' => 'Paid',
            ]" />
            <x-filter-field name="payment_method" label="Payment method" :options="[
                'cod' => 'COD',
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'online' => 'Online',
                'razorpay' => 'Razorpay',
            ]" />
            <x-filter-field name="from" label="Order date from" type="date" />
            <x-filter-field name="to" label="Order date to" type="date" />
        </x-filter-offcanvas>

        @include('tenant.customers._notifications')
        <section class="dashboard-card listing-table-card" data-ajax-pagination-container>

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach (['Order number', 'Customer', 'Date', 'Total', 'Payment', 'Status', 'Actions'] as $heading)
                                <th scope="col">{{ $heading }}</th>
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
                                <td colspan="7" class="listing-empty">No orders match your search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="listing-table-footer">{{ $orders->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
