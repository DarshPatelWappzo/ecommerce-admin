@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $return->return_number)
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-4">
            <h1 class="page-title">{{ $return->return_number }}</h1><a class="btn btn-light"
                href="{{ route('tenant.returns.index') }}">Back to returns</a>
        </div>
        @include('tenant.customers._notifications')
        <div class="row g-4">
            <div class="col-lg-8">
                <section class="dashboard-card p-4 mb-4">
                    <h2 class="h5">Return information</h2>
                    <dl class="row">
                        @foreach (['Order #' => $return->order->order_number, 'Customer' => $return->order->customer_name, 'Request date' => $return->requested_at->format('d M Y H:i'), 'Status' => ucwords(str_replace('_', ' ', $return->status))] as $label => $value)
                            <dt class="col-sm-4">{{ $label }}</dt>
                            <dd class="col-sm-8">{{ $value }}</dd>
                        @endforeach
                    </dl>
                </section>
                <section class="dashboard-card p-4 mb-4">
                    <h2 class="h5">Product and purchased policy</h2>
                    <dl class="row">
                        @foreach (['Product' => $return->item->product_name, 'Variant' => $return->item->variant_name, 'SKU' => $return->item->sku, 'Purchased quantity' => $return->item->quantity, 'Return quantity' => $return->quantity, 'Unit price' => $return->item->unit_price, 'Returnable' => $return->item->is_returnable ? 'Yes' : 'No', 'Return days' => $policy['return_days'], 'Delivery date' => $policy['delivery_date'], 'Return deadline' => $policy['return_deadline'], 'Inventory disposition' => $return->inventory_disposition] as $label => $value)
                            <dt class="col-sm-4">{{ $label }}</dt>
                            <dd class="col-sm-8">{{ $value ?? '—' }}</dd>
                        @endforeach
                    </dl>
                </section>
                <section class="dashboard-card p-4 mb-4">
                    <h2 class="h5">Reason</h2>
                    <p>{{ $return->reason->name }}</p>
                    <p>{{ $return->reason_note }}</p>
                    @if ($return->rejection_reason)
                        <p>Rejection / inspection reason: {{ $return->rejection_reason }}</p>
                    @endif
                    <p>
                        Admin note: {{ $return->admin_note }}</p>
                </section>
                <section class="dashboard-card p-4 mb-4">
                    <h2 class="h5">Payment / refund</h2>
                    <p>Original paid amount: {{ $paidAmount }} {{ $return->order->currency }}</p>
                    <p>Payment method:
                        {{ $return->refund?->payment_method ?? $return->order->payments->where('status', 'captured')->first()?->method }}
                    </p>
                    <p>Calculated item refund: {{ $return->refund_amount }} {{ $return->order->currency }} (shipping
                        excluded)</p>
                    <p>Refund type: {{ $return->refund?->gateway ? 'Razorpay gateway' : 'Manual / COD' }}</p>
                    <p>Refund status: {{ $return->refund?->status ?? 'Not initiated' }}</p>
                    <p>Transaction ID / reference:
                        {{ $return->refund?->gateway_refund_id ?? ($return->refund?->reference_number ?? '—') }}</p>
                    @if ($return->refund?->manual_method)
                        <p>Manual method: {{ $return->refund->manual_method }}; Refund date:
                            {{ $return->refund->processed_at?->format('d M Y H:i') }}; Note:
                            {{ $return->refund->admin_note }}</p>
                    @endif
                    @if ($return->refund?->failure_reason)
                        <p class="text-danger">{{ $return->refund->failure_reason }}</p>
                    @endif
                    @foreach ($return->refund?->attempts ?? [] as $attempt)
                        <p>Attempt {{ $loop->iteration }}: {{ $attempt->status }} —
                            {{ $attempt->gateway_refund_id ?? 'Awaiting reconciliation' }}</p>
                    @endforeach
                </section>
            </div>
            <div class="col-lg-4">
                <section class="dashboard-card p-4">
                    <h2 class="h5">Admin actions</h2>
                    @foreach ($actions as $target)
                        <form id="return-{{ $target }}" method="POST"
                            action="{{ route('tenant.returns.transition', $return) }}" class="mb-3 border-bottom pb-3">
                            @csrf
                            <input type="hidden" name="status" value="{{ $target }}">
                            @if (in_array($target, ['rejected', 'inspection_failed']))
                                <label class="form-label">Reason
                                    <textarea class="form-control" name="rejection_reason" required maxlength="2000"></textarea>
                                </label>
                            @endif
                            @if ($target === 'inspection_passed')
                                <label class="form-label">Inventory disposition
                                    <select class="form-select" name="inventory_disposition" required>
                                        @foreach (['do_not_restock', 'restock', 'damaged', 'defective', 'quarantine'] as $disposition)
                                            <option value="{{ $disposition }}">
                                                {{ ucwords(str_replace('_', ' ', $disposition)) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <label class="form-label d-block">Admin note
                                <textarea class="form-control" name="admin_note" maxlength="2000"></textarea>
                            </label>
                            <button
                                class="btn btn-outline-primary">{{ match ($target) {'approved' => 'Approve','rejected' => 'Reject','received' => 'Mark item received','in_transit' => 'Mark in transit','cancelled' => 'Cancel return','inspection_passed' => 'Pass inspection','inspection_failed' => 'Fail inspection','closed' => 'Close return'} }}</button>
                        </form>
                    @endforeach
                    @if (in_array($return->status, ['inspection_passed', 'refund_pending']) &&
                            !$return->refund &&
                            $permissions['initiate'] &&
                            \App\Services\TenantOrderCalculationService::money($return->refund_amount)->isGreaterThan('0'))
                        <form method="POST" action="{{ route('tenant.returns.initiate', $return) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-primary">Initiate refund</button>
                        </form>
                    @endif
                    @if ($return->status === 'refund_failed' && $permissions['retry'])
                        <form method="POST" action="{{ route('tenant.returns.retry', $return) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-primary">Retry refund</button>
                        </form>
                    @endif
                    @if ($return->refund?->gateway === 'razorpay' && $return->refund->status === 'processing' && $permissions['reconcile'])
                        <form method="POST" action="{{ route('tenant.returns.reconcile', $return) }}" class="mb-3">
                            @csrf
                            <button class="btn btn-outline-primary">Reconcile gateway status</button>
                        </form>
                    @endif
                    @if ($return->refund && !$return->refund->gateway && $return->refund->status === 'pending' && $permissions['manual'])
                        <form id="return-manual" method="POST" action="{{ route('tenant.returns.manual', $return) }}">
                            @csrf
                            <p>Record a completed manual payout of {{ $return->refund_amount }}
                                {{ $return->order->currency }}.</p>
                            <label class="form-label d-block">Refund method<select class="form-select" name="manual_method"
                                    required>
                                    @foreach (['bank_transfer', 'upi', 'cash', 'other'] as $method)
                                        <option value="{{ $method }}">{{ ucwords(str_replace('_', ' ', $method)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="form-label d-block">Reference
                                <input class="form-control" name="reference_number" required maxlength="100">
                            </label>
                            <label class="form-label d-block">Refund date
                                <input class="form-control" name="refund_date" type="datetime-local" required>
                            </label>
                            <label class="form-label d-block">Admin note
                                <textarea class="form-control" name="admin_note" required maxlength="2000"></textarea>
                            </label>
                            <button class="btn btn-primary">Confirm manual refund</button>
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
            \App\Http\Requests\TenantReturnActionRequest::rulesFor('transition'),
            [],
            [],
            '#return-' . $target,
        ) !!}
    @endforeach
    {!! JsValidator::make(
        \App\Http\Requests\TenantReturnActionRequest::rulesFor('manual'),
        [],
        [],
        '#return-manual',
    ) !!}
@endpush
