@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Invoices')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <h1 class="page-title mb-0">Invoices</h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($permissions['create'])
                    <a class="btn btn-primary" href="{{ route('tenant.invoices.create') }}">Prepare invoice</a>
                @endif
                <x-filter-button :filters="['search', 'number', 'order_id', 'customer_name', 'status', 'mode', 'from', 'to']" />
            </div>
        </div>
        <x-filter-offcanvas :show-errors="false" :action="route('tenant.invoices.index')" :filters="['search', 'number', 'order_id', 'customer_name', 'status', 'mode', 'from', 'to']">
            <x-filter-field name="search" label="Search" type="search" maxlength="200" />
            <x-filter-field name="number" label="Invoice number" maxlength="16" />
            <x-filter-field name="order_id" label="Order ID" type="number" min="1" />
            <x-filter-field name="customer_name" label="Customer name" maxlength="201" />
            <x-filter-field name="status" label="Status" :options="['draft' => 'Draft', 'issued' => 'Issued']" />
            <x-filter-field name="mode" label="Mode" :options="['automatic' => 'Automatic', 'manual' => 'Manual']" />
            <x-filter-field name="from" label="Invoice date from" type="date" />
            <x-filter-field name="to" label="Invoice date to" type="date" />
        </x-filter-offcanvas>

        @include('tenant.customers._notifications')

        <div class="dashboard-card listing-table-card">
            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Invoice</th>
                            <th scope="col">Order</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Date</th>
                            <th scope="col">Status / mode</th>
                            <th scope="col">Total</th>
                            <th scope="col">Current payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoices as $invoice)
                            <tr>
                                <td><a
                                        href="{{ route('tenant.invoices.show', $invoice) }}">{{ $invoice->number ?? 'Draft #' . $invoice->id }}</a>
                                </td>
                                <td>{{ $invoice->order->order_number }}</td>
                                <td>{{ $invoice->customer_name }}</td>
                                <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                                <td>{{ ucfirst($invoice->status) }} / {{ $invoice->mode }}</td>
                                <td>{{ $invoice->currency }} {{ $invoice->grand_total }}</td>
                                <td>{{ $invoice->order->payment_status }}</td>
                            </tr>
                        @empty<tr>
                                <td colspan="7" class="listing-empty">No invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())
                <div class="listing-table-footer">{{ $invoices->links() }}</div>
            @endif
        </div>
    </div>
@endsection
