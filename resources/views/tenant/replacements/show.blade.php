@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $replacement->replacement_number)
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-4">
            <h1 class="page-title">{{ $replacement->replacement_number }}</h1><a class="btn btn-light"
                href="{{ route('tenant.replacements.index') }}">Back to replacements</a>
        </div>
        @include('tenant.customers._notifications')
        <div class="row g-4">
            <div class="col-lg-8">
                <section class="dashboard-card p-4 mb-4">
                    <h2 class="h5">Replacement information</h2>
                    <dl class="row">
                        @foreach (['Order' => $replacement->order->order_number, 'Customer' => $replacement->order->customer_name, 'Product' => $replacement->item->product_name, 'Variant' => $replacement->item->variant_name, 'SKU' => $replacement->item->sku, 'Quantity' => $replacement->quantity, 'Reason' => $replacement->reason->name, 'Customer comment' => $replacement->reason_note, 'Admin note' => $replacement->admin_note, 'Status' => ucwords(str_replace('_', ' ', $replacement->status)), 'Inventory disposition' => $replacement->inventory_disposition, 'Stock reserved' => $replacement->stock_reserved ? 'Yes' : 'No', 'Original delivery' => $replacement->policy_snapshot['delivery_date'], 'Replacement deadline' => $replacement->policy_snapshot['replacement_deadline']] as $label => $value)
                            <dt class="col-sm-4">{{ $label }}</dt>
                            <dd class="col-sm-8">{{ $value ?? '—' }}</dd>
                        @endforeach
                    </dl>
                    <a href="{{ route('tenant.orders.show', $replacement->order_id) }}">View original order</a>
                </section>
                <section class="dashboard-card p-4 mb-4">
                    <h2 class="h5">Timeline</h2>
                    <ol class="list-group list-group-numbered">
                        @foreach ($replacement->histories as $history)
                            <li class="list-group-item">
                                <strong>{{ ucwords(str_replace('_', ' ', $history->to_status)) }}</strong><span
                                    class="text-muted ms-2">{{ $history->created_at->format('d M Y H:i') }}</span>
                                @if ($history->note)
                                    <p class="mb-0">{{ $history->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
                @if ($replacement->shipment)
                    <section class="dashboard-card p-4 mb-4">
                        <h2 class="h5">Replacement shipment</h2>
                        <p>{{ $replacement->shipment->courier_name }} — {{ $replacement->shipment->tracking_number }}</p>
                        <p>Shipped: {{ $replacement->shipment->shipped_at?->format('d M Y H:i') }}<br>Delivered:
                            {{ $replacement->shipment->delivered_at?->format('d M Y H:i') ?? 'Awaiting delivery' }}</p>
                    </section>
                @endif
                @if ($replacement->return_request_id)
                    <section class="dashboard-card p-4 mb-4">
                        <h2 class="h5">Refund handoff</h2>
                        <p>Refund status: {{ $replacement->returnRequest->refund?->status ?? 'Awaiting initiation' }}</p><a
                            href="{{ route('tenant.returns.show', $replacement->return_request_id) }}">Manage refund in
                            Returns</a>
                    </section>
                @endif
            </div>
            <div class="col-lg-4">
                <section class="dashboard-card p-4">
                    <h2 class="h5">Available actions</h2>
                    @forelse ($actions as $target)
                        <form id="replacement-{{ $target }}" method="POST" novalidate
                            action="{{ route('tenant.replacements.transition', $replacement) }}"
                            class="border-bottom pb-3 mb-3"
                            data-replacement-confirm="{{ ucwords(str_replace('_', ' ', $target)) }} this replacement?">
                            @csrf
                            <input type="hidden" name="status" value="{{ $target }}">
                            <label class="form-label" for="note-{{ $target }}">Admin note</label>
                            <textarea id="note-{{ $target }}" name="admin_note" class="form-control mb-2" maxlength="2000">{{ old('status') === $target ? old('admin_note') : '' }}</textarea>
                            @if ($target === 'qc_passed')
                                <label for="inventory-disposition" class="form-label">
                                    Returned item disposition</label>
                                <select id="inventory-disposition" name="inventory_disposition" class="form-select mb-2">
                                    @foreach (['do_not_restock', 'restock', 'damaged', 'defective', 'quarantine'] as $disposition)
                                        <option value="{{ $disposition }}">
                                            {{ ucwords(str_replace('_', ' ', $disposition)) }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @if ($target === 'shipped')
                                @foreach (['courier_name' => 'Courier', 'tracking_number' => 'Tracking number', 'tracking_url' => 'Tracking URL (optional)'] as $field => $label)
                                    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                    <input id="{{ $field }}" name="{{ $field }}" class="form-control mb-2"
                                        value="{{ old($field) }}">
                                @endforeach
                            @endif
                            <button class="btn btn-primary mt-2">{{ ucwords(str_replace('_', ' ', $target)) }}</button>
                        </form>
                    @empty
                        <p class="text-muted">No status actions available.</p>
                    @endforelse
                    @if ($canRefund)
                        <form method="POST" novalidate action="{{ route('tenant.replacements.refund', $replacement) }}"
                            data-replacement-confirm="Convert this claim and initiate its refund?">@csrf<button
                                class="btn btn-outline-danger">{{ $replacement->return_request_id ? 'Initiate existing refund' : 'Convert to refund' }}</button>
                        </form>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    @foreach ($actions as $target)
        {!! JsValidator::make(
            \App\Http\Requests\TenantReplacementRequest::transitionRules(),
            [],
            [],
            '#replacement-' . $target,
        ) !!}
    @endforeach
    <script src="{{ asset('js/replacement-actions.js') }}"></script>
@endpush
