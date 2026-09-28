@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $order ? 'Edit draft order' : 'Add order')
@section('content')
    @php($input = old() ?: $order?->draft_input ?? ['same_as_billing' => true, 'shipping_amount' => '0.00', 'payment_method' => 'cod'])
    <div class="container-fluid">
        <h1 class="page-title mb-4">{{ $order ? 'Edit draft ' . $order->order_number : 'Add order' }}</h1>
        <form id="order-form" data-order-form method="POST"
            action="{{ $order ? route('tenant.orders.update', $order) : route('tenant.orders.store') }}" novalidate>
            @csrf
            @if ($order)
                @method('PATCH')
            @endif
            <input type="hidden" name="currency" value="INR">
            <input type="hidden" name="idempotency_key"
                value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
            @include('tenant.partials.validation-errors')
            <div class="alert alert-danger d-none" data-order-errors role="alert"></div>
            <section class="dashboard-card mb-4">
                <h2 class="h5 mb-3">Customer</h2>
                <label class="form-label" for="customer_id">Search customer (leave empty for a guest order)</label>
                <select class="form-select mb-3" id="customer_id" name="customer_id" data-select2-disabled>
                    <option value=""></option>
                    @if (!empty($input['customer_id']))
                        <option value="{{ $input['customer_id'] }}" selected>
                            {{ $order?->customer_name ?? 'Selected customer' }}</option>
                    @endif
                </select>
                <p class="form-text">A selected customer’s saved contact details are used. Clear the selection to enter a
                    guest.</p>
                <div class="row g-3">
                    @foreach (['customer_name' => 'Name', 'customer_email' => 'Email', 'customer_phone' => 'Phone', 'company_name' => 'Company (optional)', 'gstin' => 'GSTIN (optional)'] as $field => $label)
                        <div
                            class="col-md-{{ $field === 'customer_name' ? '4' : ($field === 'company_name' || $field === 'gstin' ? '6' : '4') }}">
                            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                            <input class="form-control" id="{{ $field }}" name="{{ $field }}"
                                value="{{ $input[$field] ?? $order?->$field }}" @readonly(!empty($input['customer_id']))>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="dashboard-card mb-4">
                <h2 class="h5 mb-3">Billing address</h2>@include('tenant.orders._address', ['type' => 'billing'])
            </section>
            <section class="dashboard-card mb-4">
                <h2 class="h5 mb-3">Shipping address</h2>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="same_as_billing"
                        id="same_as_billing" value="1" @checked($input['same_as_billing'] ?? false)><label class="form-check-label"
                        for="same_as_billing">Same as billing</label></div>
                <div data-shipping-address>@include('tenant.orders._address', ['type' => 'shipping'])</div>
            </section>
            <section class="dashboard-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Items</h2><button type="button" class="btn btn-outline-primary btn-sm"
                        data-add-item>Add item</button>
                </div>
                <p class="form-text">Prices exclude tax. Select a product with an active tax; an explicit 0% tax is
                    supported. Inventory is reserved at confirmation.</p>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Product / variant</th>
                                <th>Quantity</th>
                                @if ($permissions['price_override'])
                                    <th>Price override (INR)</th>
                                    @endif @if ($permissions['discount'])
                                        <th>Discount (INR)</th>
                                    @endif
                                    <th>
                                        Tax</th>
                                    <th>Total (INR)</th>
                                    <th></th>
                            </tr>
                        </thead>
                        <tbody data-order-items></tbody>
                    </table>
                </div>
            </section>
            <section class="dashboard-card mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="coupon_code" class="form-label">Coupon code</label>
                        <input class="form-control" id="coupon_code" name="coupon_code" maxlength="50"
                            value="{{ $input['coupon_code'] ?? '' }}">
                        <div class="form-text">Clear the code to remove it. Applied before tax to eligible items.</div>
                    </div>
                    <div class="col-md-4"><label for="shipping_amount" class="form-label">Shipping charge
                            (INR, optional)</label><input type="number" min="0" step="0.01" class="form-control"
                            id="shipping_amount" name="shipping_amount" value="{{ $input['shipping_amount'] ?? '0.00' }}">
                    </div>
                    <div class="col-md-4"><label for="shipping_tax_id" class="form-label">Shipping GST
                            (optional)</label><select class="form-select" id="shipping_tax_id" name="shipping_tax_id">
                            <option value="">No shipping GST</option>
                            @foreach ($taxes as $tax)
                                <option value="{{ $tax->id }}" @selected((string) ($input['shipping_tax_id'] ?? '') === (string) $tax->id)>{{ $tax->name }} —
                                    {{ rtrim(rtrim($tax->rate, '0'), '.') }}%</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><label for="payment_method" class="form-label">Payment method</label><select
                            class="form-select" id="payment_method" name="payment_method">
                            @foreach (['cod' => 'Cash on delivery', 'cash' => 'Cash', 'bank_transfer' => 'Bank transfer'] as $method => $label)
                                <option value="{{ $method }}" @selected(($input['payment_method'] ?? 'cod') === $method)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Selecting a method does not record a receipt.</div>
                    </div>
                    @foreach (['customer_note' => 'Customer note', 'internal_note' => 'Internal note'] as $field => $label)
                        <div class="col-md-6"><label class="form-label"
                                for="{{ $field }}">{{ $label }}</label>
                            <textarea class="form-control" id="{{ $field }}" name="{{ $field }}" rows="3"
                                maxlength="{{ $field === 'internal_note' ? 5000 : 2000 }}">{{ $input[$field] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="dashboard-card mb-4">
                <h2 class="h5">Totals · INR</h2>
                <p class="form-text" data-preview-message aria-live="polite">Add an item to calculate totals.</p>
                <div class="row g-3">
                    @foreach (['subtotal' => 'Subtotal', 'discount_total' => 'Discount', 'shipping_amount' => 'Shipping', 'shipping_tax_amount' => 'Shipping tax (included in total tax)', 'tax_total' => 'Total tax', 'grand_total' => 'Grand total'] as $key => $label)
                        <div class="col-md-4">
                            <div class="text-muted">{{ $label }}</div><strong
                                data-total="{{ $key }}">—</strong>
                        </div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top"><a class="btn btn-light"
                        href="{{ route('tenant.orders.index') }}">Cancel</a><button class="btn btn-outline-primary"
                        name="submit_as" value="draft">Save draft</button>
                    @if ($permissions['confirm'])
                        <button class="btn btn-primary" name="submit_as" value="confirmed">Confirm order</button>
                    @endif
                </div>
            </section>
        </form>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::make(
        \App\Http\Requests\TenantOrderSaveRequest::createFrom(request())->clientRules(),
        [],
        [],
        '#order-form',
    )->remote(false) !!}
    <script>
        window.orderFormConfig =
            {{ \Illuminate\Support\Js::from(['optionsUrl' => route('tenant.orders.options'), 'previewUrl' => route('tenant.orders.preview'), 'items' => $input['items'] ?? [], 'snapshots' => $order?->items ?? [], 'permissions' => $permissions]) }};
    </script>
    <script src="{{ asset('js/order-form.js') }}"></script>
@endpush
