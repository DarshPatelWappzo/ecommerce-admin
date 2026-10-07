@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Payments')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <h1 class="page-title mb-0">Payments</h1>
            </div>
            <x-filter-button :filters="['search', 'method', 'status', 'from', 'to']" />
        </div>
        <x-filter-offcanvas :show-errors="false" :action="route('tenant.payments.index')" :filters="['search', 'method', 'status', 'from', 'to']">
            <x-filter-field name="search" label="Order or customer" type="search" maxlength="200" />
            <x-filter-field name="method" label="Payment method" :options="collect(config('order_payments.methods'))->mapWithKeys(
                fn($settings, $method) => [$method => $settings['label']],
            )" />
            <x-filter-field name="status" label="Status" :options="[
                'created' => 'Created',
                'pending' => 'Pending',
                'authorized' => 'Authorized',
                'captured' => 'Captured',
                'failed' => 'Failed',
                'superseded' => 'Superseded',
            ]" />
            <x-filter-field name="from" label="Date from" type="date" />
            <x-filter-field name="to" label="Date to" type="date" />
        </x-filter-offcanvas>

        @include('tenant.partials.validation-errors')

        <section class="dashboard-card listing-table-card">

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Order</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Method</th>
                            <th scope="col">Status</th>
                            <th scope="col">Amount</th>
                            <th scope="col">Transaction</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $payment->order->order_number }}</td>
                                <td>{{ $payment->order->customer_name }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $payment->method)) }}</td>
                                <td><span
                                        class="badge text-bg-{{ $payment->status === 'captured' ? 'success' : ($payment->status === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($payment->status) }}</span>
                                </td>
                                <td>{{ $payment->currency }} {{ $payment->amount }}</td>
                                <td>{{ $payment->gateway_payment_id ?: $payment->reference_number ?: '—' }}</td>
                                <td><a class="btn btn-sm btn-light"
                                        href="{{ route('tenant.payments.show', $payment) }}">View</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="listing-empty">No payments match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="listing-table-footer">{{ $payments->links() }}</div>
            @endif
        </section>
    </div>
@endsection
