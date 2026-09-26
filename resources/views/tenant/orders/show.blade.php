@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $order->order_number)
@section('content')
    @php
        $actions = [];
        foreach (
            [
                'confirm' => 'confirmed',
                'process' => 'processing',
                'cancel' => 'cancelled',
                'ship' => 'shipped',
                'deliver' => 'delivered',
            ]
            as $action => $target
        ) {
            if (
                $permissions[$action] &&
                in_array($target, \App\Models\Tenant\Order::TRANSITIONS[$order->status], true) &&
                !($action === 'cancel' && (float) $summary['received_amount'] > 0)
            ) {
                $actions[$action] = ucfirst($action) . ' order';
            }
        }
        if (
            $permissions['payments'] &&
            !in_array($order->status, ['draft', 'cancelled']) &&
            (float) $summary['outstanding_amount'] > 0
        ) {
            $actions['payments'] = 'Record payment';
        }
    @endphp
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h1 class="page-title mb-1">{{ $order->order_number }}</h1><span
                    class="text-muted">{{ ucfirst($order->status) }} ·
                    {{ ucwords(str_replace('_', ' ', $order->payment_status)) }} ·
                    {{ $order->order_date->format('d M Y H:i') }}</span>
            </div><a href="{{ route('tenant.orders.index') }}" class="btn btn-light">Back to orders</a>
        </div>
        @include('tenant.customers._notifications')
        <div class="d-flex flex-wrap gap-2 mb-4">
            @if ($order->status === 'draft' && $permissions['update'])
                <a class="btn btn-outline-primary" href="{{ route('tenant.orders.edit', $order) }}">Edit draft</a>
            @endif
            @foreach ($actions as $action => $label)
                <button type="button" class="btn {{ $action === 'cancel' ? 'btn-outline-danger' : 'btn-primary' }}"
                    data-bs-toggle="modal" data-bs-target="#order-{{ $action }}-modal">{{ $label }}</button>
            @endforeach
        </div>
        @if ((float) $summary['received_amount'] > 0 && in_array($order->status, ['pending', 'confirmed', 'processing']))
            <div class="alert alert-info">This order has received funds. Cancellation requires a refund workflow, which is
                not available in this release.</div>
        @endif
        @if ($order->status === 'cancelled')
            <div class="alert alert-warning">Cancelled: {{ $order->cancellation_reason }}</div>
        @endif
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <section class="dashboard-card h-100">
                    <h2 class="h5">Customer</h2>
                    <p>{{ $order->customer_name }}<br>{{ $order->customer_email }}<br>{{ $order->customer_phone }}</p>
                    @if ($order->company_name)
                        <p>{{ $order->company_name }}<br>GSTIN: {{ $order->gstin ?: 'Not provided' }}</p>
                    @endif
                </section>
            </div>
            @foreach ($order->addresses as $address)
                <div class="col-lg-4">
                    <section class="dashboard-card h-100">
                        <h2 class="h5">{{ ucfirst($address->type) }} address</h2>
                        <p class="mb-0">
                            {{ $address->name }}<br>{{ $address->phone }}<br>{{ $address->address_line_1 }}<br>
                            @if ($address->address_line_2)
                                {{ $address->address_line_2 }}
                                <br>
                            @endif{{ $address->city }}, {{ $address->state_name }}
                            {{ $address->postal_code }}<br>{{ $address->country_code }}
                        </p>
                    </section>
                </div>
            @endforeach
        </div>
        <section class="dashboard-card mb-4">
            <h2 class="h5 mb-3">Purchased items</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Item / SKU / HSN</th>
                            <th>Qty</th>
                            <th>Unit price</th>
                            <th>Subtotal</th>
                            <th>Discount</th>
                            <th>Taxable</th>
                            <th>Tax</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $item->product_name }} @if ($item->variant_name)
                                        <div>{{ $item->variant_name }}</div>
                                    @endif
                                    <div class="text-muted">
                                        {{ $item->sku }} · HSN {{ $item->hsn_code ?: 'Not provided' }}</div>
                                </td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ $item->unit_price }} @if ($item->price_overridden)
                                        <small class="text-muted">Override</small>
                                    @endif
                                </td>
                                <td>{{ $item->subtotal }}</td>
                                <td>{{ $item->discount_amount }}</td>
                                <td>{{ $item->taxable_amount }}</td>
                                <td>{{ $item->tax_amount }}<div class="text-muted">{{ $item->tax_name }}
                                        ({{ $item->tax_code }})
                                        · {{ $item->tax_rate }}%</div>
                                </td>
                                <td>{{ $item->total_amount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="form-text mb-0">All amounts are INR. Tax is applied as a single percentage to tax-exclusive prices and
                rounded half up to two decimal places per line.</p>
        </section>
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <section class="dashboard-card h-100">
                    <h2 class="h5">Notes</h2>
                    <h3 class="h6 mt-3">Customer note</h3>
                    <p class="text-break">{{ $order->customer_note ?: 'None' }}</p>
                    <h3 class="h6">Internal note</h3>
                    <p class="text-break">{{ $order->internal_note ?: 'None' }}</p>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="dashboard-card">
                    <h2 class="h5">Totals · INR</h2>
                    <dl class="row mb-0">
                        @foreach (['subtotal' => 'Subtotal', 'discount_total' => 'Discount', 'shipping_amount' => 'Shipping', 'shipping_tax_amount' => 'Shipping tax (included below)', 'tax_total' => 'Total tax', 'rounding_adjustment' => 'Rounding adjustment', 'grand_total' => 'Grand total', 'received_amount' => 'Received', 'outstanding_amount' => 'Outstanding'] as $field => $label)
                            <dt class="col-8">{{ $label }}</dt>
                            <dd class="col-4 text-end">{{ $summary[$field] }}</dd>
                        @endforeach
                    </dl>
                    @if ($order->shipping_tax_name)
                        <div class="form-text">Shipping: {{ $order->shipping_tax_name }} ({{ $order->shipping_tax_code }})
                            · {{ $order->shipping_tax_rate }}%</div>
                    @endif
                </section>
            </div>
        </div>
        <section class="dashboard-card mb-4">
            <h2 class="h5">Payments</h2>
            <p class="form-text">Only captured receipts count as received. The pending payment method records the intended
                method.</p>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Amount (INR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($order->payments as $payment)
                            <tr>
                                <td>{{ ($payment->paid_at ?? $payment->created_at)->format('d M Y H:i') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $payment->method)) }}</td>
                                <td>{{ $payment->reference_number ?: '—' }}</td>
                                <td>{{ ucfirst($payment->status) }}</td>
                                <td>{{ $payment->amount }}</td>
                        </tr>@empty<tr>
                                <td colspan="5">No payments recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="dashboard-card mb-4">
            <h2 class="h5">Shipment</h2>
            @if ($order->shipment)
                <p>{{ ucfirst($order->shipment->status) }} ·
                    {{ $order->shipment->courier_name ?: 'Courier not provided' }} ·
                    {{ $order->shipment->tracking_number ?: 'Tracking not provided' }}</p>
                @if (
                    $order->shipment->tracking_url &&
                        in_array(strtolower(parse_url($order->shipment->tracking_url, PHP_URL_SCHEME) ?? ''), ['http', 'https']))
                    <a href="{{ $order->shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">Track
                        shipment</a>
                @endif
                <p class="form-text">
                    Shipped {{ $order->shipment->shipped_at?->format('d M Y H:i') }} @if ($order->shipment->delivered_at)
                        · Delivered {{ $order->shipment->delivered_at->format('d M Y H:i') }}
                    @endif
                </p>
            @else<p>No shipment yet.</p>
            @endif
        </section>
        <section class="dashboard-card">
            <h2 class="h5">Status history</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transition</th>
                            <th>Staff ID</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->histories as $history)
                            <tr>
                                <td>{{ $history->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $history->from_status ?: 'New' }} → {{ $history->to_status }}</td>
                                <td>{{ $history->changed_by ?: '—' }}</td>
                                <td>{{ $history->comment ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    @foreach ($actions as $action => $label)
        <div class="modal fade" id="order-{{ $action }}-modal" tabindex="-1"
            aria-labelledby="order-{{ $action }}-title" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="order-{{ $action }}-form" data-order-action method="POST"
                        action="{{ route('tenant.orders.' . $action, $order) }}" novalidate>
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="order-{{ $action }}-title">{{ $label }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger d-none" data-order-errors role="alert"></div>
                            @if ($action === 'payments')
                                <input type="hidden" name="idempotency_key"
                                    value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden"
                                    name="currency" value="INR">
                                <p>Outstanding: INR {{ $summary['outstanding_amount'] }}</p>
                                <div class="mb-3"><label class="form-label" for="receipt-method">Method</label><select
                                        id="receipt-method" name="method" class="form-select" data-select2-disabled>
                                        <option value="cod">Cash on delivery received</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank transfer</option>
                                    </select></div>
                                <div class="mb-3"><label class="form-label" for="receipt-reference">Receipt / bank
                                        reference</label><input class="form-control" id="receipt-reference"
                                        name="reference_number" maxlength="100"></div>
                                <div class="mb-3"><label class="form-label" for="receipt-amount">Amount received
                                        (INR)</label><input class="form-control" id="receipt-amount" name="amount"
                                        type="number" min="0.01" max="{{ $summary['outstanding_amount'] }}"
                                        step="0.01"></div>
                            @else
                                @if ($action === 'confirm')
                                    <p>Confirm this order and reserve its stock?</p>
                                @elseif ($action === 'ship')
                                    <p>Shipment deducts stock. This action cannot be repeated.</p>
                                @elseif ($action === 'cancel')
                                    <p>Cancel this order and release any reserved stock?</p>
                                @endif
                                @if ($action === 'ship')
                                    @foreach (['courier_name' => 'Courier', 'tracking_number' => 'Tracking number', 'tracking_url' => 'Tracking URL'] as $field => $fieldLabel)
                                        <div class="mb-3"><label class="form-label"
                                                for="ship-{{ $field }}">{{ $fieldLabel }}</label><input
                                                class="form-control" id="ship-{{ $field }}"
                                                name="{{ $field }}"
                                                maxlength="{{ $field === 'tracking_url' ? 1000 : 100 }}"></div>
                                    @endforeach
                                @endif
                                <label class="form-label"
                                    for="{{ $action }}-comment">{{ $action === 'cancel' ? 'Cancellation reason (required)' : 'Comment (optional)' }}</label>
                                <textarea class="form-control" id="{{ $action }}-comment" name="comment" rows="3" maxlength="2000"></textarea>
                            @endif
                        </div>
                        <div class="modal-footer"><button class="btn btn-light" type="button"
                                data-bs-dismiss="modal">Close</button><button
                                class="btn {{ $action === 'cancel' ? 'btn-danger' : 'btn-primary' }}"
                                type="submit">{{ $label }}</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    @foreach ($actions as $action => $label)
        {!! JsValidator::make(
            \App\Http\Requests\TenantOrderActionRequest::rulesFor($action),
            [],
            [],
            '#order-' . $action . '-form',
        ) !!}
    @endforeach
    <script src="{{ asset('js/order-form.js') }}"></script>
@endpush
