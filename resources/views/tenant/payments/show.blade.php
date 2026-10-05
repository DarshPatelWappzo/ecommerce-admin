@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Payment #' . $payment->id)
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-4">
            <h1 class="page-title">Payment #{{ $payment->id }}</h1>
            <a class="btn btn-light" href="{{ route('tenant.payments.index') }}">Back to payments</a>
        </div>
        <section class="dashboard-card mb-4">
            <h2 class="h5">{{ $order->order_number }} · {{ $order->customer_name }}</h2>
            <dl class="row">
                @foreach (['Method' => $payment->method, 'Status' => $payment->status, 'Amount' => $payment->currency . ' ' . $payment->amount, 'Provider' => $payment->gateway, 'Provider order ID' => $payment->gateway_order_id, 'Provider payment ID' => $payment->gateway_payment_id, 'Transaction reference' => $payment->reference_number, 'Recorded by' => $payment->recorder?->first_name ?? ($payment->gateway ? 'Provider verified' : $payment->recorded_by), 'Failure code' => $payment->failure_code, 'Failure details' => $payment->failure_message] as $label => $value)
                    <dt class="col-sm-4">{{ $label }}</dt>
                    <dd class="col-sm-8 text-break">{{ $value ?: '—' }}</dd>
                @endforeach
            </dl>
            <p class="text-muted">Only captured payments count as received. Authorization alone does not mark an order paid.
            </p>
        </section>
        <section class="dashboard-card">
            @include('tenant.payments._timeline', [
                'payments' => collect([$payment]),
                'checkout' => $order->paymentCheckout,
            ])
        </section>
    </div>
@endsection
