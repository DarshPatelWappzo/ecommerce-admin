@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Replacements')
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Replacements</h1>
            {{-- @if ($canCreate)
                <a class="btn btn-outline-primary" href="#create-replacement">Create replacement</a>
            @endif --}}
        </div>
        @include('tenant.customers._notifications')
        @if ($canCreate)
            <details class="dashboard-card p-3 mb-4" id="create-replacement" @if (request()->filled('create_order_number')) open @endif>
                <summary>Create a same-SKU replacement</summary>
                <form id="replacement-order-lookup" method="GET" class="row g-3 mt-1" novalidate>
                    <div class="col-md-8"><label class="form-label" for="create_order_number">Original order
                            number</label><input id="create_order_number" name="create_order_number" class="form-control"
                            value="{{ request('create_order_number') }}"></div>
                    <div class="col-md-4 align-self-end"><button class="btn btn-outline-primary">Find delivered
                            order</button></div>
                </form>
                @if ($createOrder)
                    <form id="replacement-create" method="POST" action="{{ route('tenant.replacements.store') }}"
                        class="row g-3 mt-1" novalidate>
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $createOrder->id }}">
                        <div class="col-12">{{ $createOrder->order_number }} — {{ $createOrder->customer_name }}</div>
                        <div class="col-md-8"><label class="form-label" for="order_item_id">Purchased item</label><select
                                class="form-select" id="order_item_id" name="order_item_id">
                                @foreach ($createOrder->items as $item)
                                    <option value="{{ $item->id }}" @selected(old('order_item_id') == $item->id)>
                                        {{ $item->product_name }} — {{ $item->sku }} (purchased: {{ $item->quantity }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label" for="quantity">Replacement quantity</label><input
                                id="quantity" name="quantity" class="form-control" type="number"
                                value="{{ old('quantity', 1) }}"></div>
                        <div class="col-md-6"><label class="form-label" for="reason_id">Reason</label><select
                                class="form-select" id="reason_id" name="reason_id">
                                @foreach ($reasons as $reason)
                                    <option value="{{ $reason->id }}" @selected(old('reason_id') == $reason->id)>{{ $reason->name }}
                                    </option>
                                @endforeach
                            </select></div>
                        <div class="col-md-6"><label class="form-label" for="reason_note">Customer comment</label>
                            <textarea class="form-control" id="reason_note" name="reason_note" maxlength="2000">{{ old('reason_note') }}</textarea>
                        </div>
                        <div class="col-12"><button class="btn btn-primary">Create replacement</button></div>
                    </form>
                @elseif (request()->filled('create_order_number'))
                    <p class="text-muted mt-3">No delivered customer order matches that number.</p>
                @endif
            </details>
        @endif
        <section class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <form class="row g-2 p-3 border-bottom" method="GET">
                @foreach (['search' => 'Replacement number', 'order_number' => 'Order number', 'customer_id' => 'Customer ID'] as $field => $label)
                    <div class="col-md-2"><label class="form-label"
                            for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}"
                            class="form-control" name="{{ $field }}" value="{{ request($field) }}"></div>
                @endforeach
                <div class="col-md-2"><label class="form-label" for="status">Status</label><select id="status"
                        class="form-select" name="status">
                        <option value="">All</option>
                        @foreach (array_keys(\App\Models\Tenant\ReplacementRequest::TRANSITIONS) as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach (['from' => 'From', 'to' => 'To'] as $field => $label)
                    <div class="col-md-2"><label class="form-label"
                            for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}"
                            class="form-control" type="date" name="{{ $field }}" value="{{ request($field) }}">
                    </div>
                @endforeach
                <div class="col-12"><button class="btn btn-primary">Filter</button></div>
            </form>
            <div class="table-responsive">
                <table class="table user-table align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach (['Replacement #', 'Order #', 'Customer', 'Product', 'Quantity', 'Reason', 'Requested', 'Status', 'SKU / Variant', 'Action'] as $heading)
                                <th>{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($replacements as $replacement)
                            <tr>
                                <td>{{ $replacement->replacement_number }}</td>
                                <td>{{ $replacement->order->order_number }}</td>
                                <td>{{ $replacement->order->customer_name }}</td>
                                <td>{{ $replacement->item->product_name }}</td>
                                <td>{{ $replacement->quantity }}</td>
                                <td>{{ $replacement->reason->name }}</td>
                                <td>{{ $replacement->requested_at->format('d M Y H:i') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $replacement->status)) }}</td>
                                <td>{{ $replacement->item->sku }} / {{ $replacement->item->variant_name }}</td>
                                <td><a class="btn btn-sm btn-light"
                                        href="{{ route('tenant.replacements.show', $replacement) }}">View</a></td>
                            </tr>
                        @empty<tr>
                                <td colspan="10" class="text-center text-muted py-5">No replacements match your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($replacements->hasPages())
                <div class="border-top p-3">{{ $replacements->links() }}</div>
            @endif
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    @if ($canCreate)
        {!! JsValidator::make(
            ['create_order_number' => ['required', 'string', 'max:40']],
            [],
            [],
            '#replacement-order-lookup',
        ) !!}
        @if ($createOrder)
            {!! JsValidator::make(
                \App\Http\Requests\TenantReplacementRequest::creationRules(),
                [],
                [],
                '#replacement-create',
            ) !!}
        @endif
    @endif
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
