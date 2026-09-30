@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Payments')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">Payments</h1>
        @include('tenant.partials.validation-errors')
        <section class="dashboard-card">

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Transaction</th>
                            <th></th>
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
                                <td colspan="8" class="text-center text-muted py-5">No payments match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        </section>
    </div>
@endsection
