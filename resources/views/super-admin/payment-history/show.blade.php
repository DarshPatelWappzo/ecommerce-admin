@extends('layouts.app')
@section('title', 'Payment Details')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <div>
                <h1 class="page-title">Payment Details</h1>
                <p>{{ $payment['reference'] }}</p>
            </div>
            <a class="btn btn-outline-secondary"
                href="{{ route('super-admin.payment-history.index', ['mode' => $payment['mode']]) }}">Payment History</a>
        </div>
        @if ($payment['fulfillment'] === 'resolution_required')
            <div class="alert alert-warning">Payment received; resolution required. This capture remains included in
                collected payments. No automatic activation or refund is performed here.</div>
        @endif
        <div class="row g-3">
            <div class="col-lg-6">
                <section class="dashboard-card p-4">
                    <h2 class="h5">Payment and fulfillment</h2>
                    <dl class="row">
                        @foreach (['id' => 'Local payment ID', 'status' => 'Financial status', 'fulfillment' => 'Subscription fulfillment', 'checkout_state' => 'Local checkout state', 'mode' => 'Mode', 'gateway' => 'Gateway', 'amount_minor' => 'Amount (minor units)', 'currency' => 'Currency', 'gateway_order_id' => 'Gateway order ID', 'gateway_payment_id' => 'Gateway payment ID', 'purchase_type' => 'Purchase type', 'billing_cycle' => 'Billing cycle', 'created_at' => 'Attempt created', 'paid_at' => 'Capture confirmed', 'checkout_expires_at' => 'Local checkout expiry', 'failure' => 'Safe failure / reconciliation information'] as $key => $label)
                            <dt class="col-sm-5">{{ $label }}</dt>
                            <dd class="col-sm-7 text-break">{{ $payment[$key] ?? 'Not recorded' }}</dd>
                        @endforeach
                    </dl>
                    <p class="small text-secondary">Refund amounts and timestamps are unavailable: no platform refund ledger
                        exists.</p>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="dashboard-card p-4">
                    <h2 class="h5">Owner and tenant (current records)</h2>
                    <p>{{ $payment['owner_name'] }} · {{ $payment['email'] }} @if ($payment['owner_deleted'])
                            (deleted user)
                        @endif
                    </p>
                    <p>{{ $payment['tenant'] }} · Central owner #{{ $payment['owner_id'] }}</p>
                    <p>Assigned domain: {{ $payment['domain'] }} @if ($payment['domain_deleted'])
                            (deleted domain)
                        @endif
                    </p>
                    <p class="small text-secondary">The assigned domain is the tenant's primary domain. Historical
                        owner/business identity was not snapshotted. A separate business name is not recorded centrally.</p>
                    <h3 class="h6">All domains associated with this owner</h3>
                    <ul>
                        @forelse($domains as $domain)
                            <li>{{ $domain->domain_name }} @if ($domain->deleted_at)
                                    (deleted)
                                @endif
                            </li>
                        @empty<li>No available domains.</li>
                        @endforelse
                    </ul>
                    <p class="small text-secondary">Other owner domains can belong to separate tenant databases; they are
                        not assumed to be aliases of this payment's tenant.</p>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="dashboard-card p-4">
                    <h2 class="h5">Purchased package</h2>
                    <p>{{ $payment['package'] }} · {{ $payment['package_source'] }}</p>
                    <dl class="row">
                        @foreach ($payment['snapshot'] as $key => $value)
                            <dt class="col-sm-5">{{ Illuminate\Support\Str::headline($key) }}</dt>
                            <dd class="col-sm-7 text-break">
                                {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value ?? 'Not recorded' }}
                            </dd>
                        @endforeach
                    </dl>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="dashboard-card p-4">
                    <h2 class="h5">Related subscription</h2>
                    <dl class="row">
                        @foreach (['subscription_id' => 'Subscription ID', 'subscription_status' => 'Stored lifecycle status', 'starts_at' => 'Effective / scheduled start', 'expires_at' => 'Exclusive expiry', 'grace_ends_at' => 'Grace ends'] as $key => $label)
                            <dt class="col-sm-5">{{ $label }}</dt>
                            <dd class="col-sm-7">{{ $payment[$key] ?? 'Unavailable' }}</dd>
                        @endforeach
                    </dl>
                    @if ($payment['subscription_deleted'])
                        <p>Related subscription has been deleted.</p>
                    @endif
                    <p class="small text-secondary">Fulfillment uses effective dates even when the scheduler has not updated
                        the stored lifecycle status. Dates use {{ config('app.timezone') }}.</p>
                </section>
            </div>
        </div>
    </div>
@endsection
