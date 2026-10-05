@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Prepare invoice')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">{{ $invoice?->exists ? 'Edit invoice draft' : 'Prepare invoice' }}</h1>
        @include('tenant.partials.validation-errors')
        @if (!$invoice)
            <form method="GET" class="d-flex gap-2 mb-4"><input class="form-control" name="search"
                    value="{{ request('search') }}" placeholder="Search order or customer"><button
                    class="btn btn-primary">Search</button></form>
            <p>Choose an order with a saved snapshot. Dispatch approval and prepaid collection are required before issuance.
            </p>
            <div class="dashboard-card table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($candidates as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ $order->customer_name }}</td>
                                <td>{{ $order->status }}</td>
                                <td>{{ $order->currency }} {{ $order->grand_total }}</td>
                                <td><a href="{{ route('tenant.invoices.create', ['order_id' => $order->id]) }}">Select</a>
                                </td>
                            </tr>
                        @empty<tr>
                                <td colspan="5">No eligible orders. Existing drafts are available in Invoices.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>{{ $candidates->links() }}
            </div>
        @else
            <form id="invoice-form" method="POST"
                action="{{ $invoice->exists ? route('tenant.invoices.update', $invoice) : route('tenant.invoices.store') }}"
                class="dashboard-card">
                @csrf
                @if ($invoice->exists)
                    @method('PUT')
                @endif
                <input type="hidden" name="order_id" value="{{ $invoice->order_id }}">
                <p>Order: {{ $invoice->customer['order_number'] }}. Financial values are read-only. No final number is
                    assigned to a draft.</p>
                <div class="row g-3">
                    <div class="col-md-4"><label for="invoice_date" class="form-label">Invoice date</label><input
                            class="form-control" id="invoice_date" type="date" name="invoice_date"
                            max="{{ today()->toDateString() }}"
                            value="{{ old('invoice_date', $invoice->invoice_date->toDateString()) }}" required></div>
                    <div class="col-md-4"><label for="customer_name" class="form-label">Billing identity</label><input
                            class="form-control" id="customer_name" name="customer_name"
                            value="{{ old('customer_name', $invoice->customer_name) }}" required></div>
                    <div class="col-md-4"><label class="form-label">GSTIN (order snapshot)</label><input
                            class="form-control" value="{{ $invoice->customer['gstin'] ?? '' }}" readonly></div>
                    @foreach (['name' => 'Billing name', 'phone' => 'Billing phone', 'address_line_1' => 'Address line 1', 'address_line_2' => 'Address line 2'] as $field => $label)
                        <div class="col-md-6"><label class="form-label"
                                for="billing-{{ $field }}">{{ $label }}</label><input class="form-control"
                                id="billing-{{ $field }}" name="billing[{{ $field }}]"
                                value="{{ old('billing.' . $field, $invoice->billing[$field] ?? '') }}"></div>
                    @endforeach
                    <p class="text-muted">Place of supply: {{ $invoice->billing['city'] }},
                        {{ $invoice->billing['state_name'] }}, {{ $invoice->billing['country_code'] }}
                        {{ $invoice->billing['postal_code'] }}. GSTIN or location changes require correction of the order
                        first.</p>
                    @foreach (['notes' => 'Notes', 'terms' => 'Terms'] as $field => $label)
                        <div class="col-md-6"><label class="form-label"
                                for="{{ $field }}">{{ $label }}</label>
                            <textarea class="form-control" rows="4" id="{{ $field }}" name="{{ $field }}">{{ old($field, $invoice->$field) }}</textarea>
                        </div>
                    @endforeach
                </div>
                @include('tenant.invoices._items', ['financials' => $invoice->financials])
                <button class="btn btn-primary">Save draft</button>
                <a class="btn btn-light" href="{{ route('tenant.invoices.index') }}">Back</a>
            </form>
        @endif
    </div>
@endsection
@push('scripts')
    @if ($invoice)
        <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
        {!! JsValidator::make(\App\Http\Requests\TenantInvoiceSaveRequest::formRules(), [], [], '#invoice-form') !!}
    @endif
@endpush
