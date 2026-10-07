@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Returns')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <h1 class="page-title mb-0">Returns</h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($canManageReasons)
                    <a class="btn btn-outline-primary" href="{{ route('tenant.returns.reasons') }}">Return reasons</a>
                @endif
                <x-filter-button :filters="['search', 'order_number', 'customer_id', 'status', 'from', 'to']" />
            </div>
        </div>
        <x-filter-offcanvas :show-errors="false" :action="route('tenant.returns.index')" :filters="['search', 'order_number', 'customer_id', 'status', 'from', 'to']">
            <x-filter-field name="search" label="Return number" maxlength="200" />
            <x-filter-field name="order_number" label="Order number" maxlength="40" />
            <x-filter-field name="customer_id" label="Customer ID" type="number" min="1" />
            <x-filter-field name="status" label="Status" :options="collect(array_keys(\App\Models\Tenant\ReturnRequest::TRANSITIONS))->mapWithKeys(
                fn($value) => [$value => \Illuminate\Support\Str::headline($value)],
            )" />
            <x-filter-field name="from" label="Requested from" type="date" />
            <x-filter-field name="to" label="Requested to" type="date" />
        </x-filter-offcanvas>

        @include('tenant.customers._notifications')
        <section class="dashboard-card listing-table-card" data-ajax-pagination-container>

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            @foreach (['Return #', 'Order #', 'Customer', 'Product', 'Quantity', 'Reason', 'Requested', 'Status', 'Refund amount', 'Action'] as $heading)
                                <th scope="col">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $return)
                            <tr>
                                <td>{{ $return->return_number }}</td>
                                <td>{{ $return->order->order_number }}</td>
                                <td>{{ $return->order->customer_name }}</td>
                                <td>{{ $return->item->product_name }}</td>
                                <td>{{ $return->quantity }}</td>
                                <td>{{ $return->reason->name }}</td>
                                <td>{{ $return->requested_at->format('d M Y H:i') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $return->status)) }}</td>
                                <td>{{ $return->order->currency }} {{ $return->refund_amount }}</td>
                                <td><a class="btn btn-sm btn-light"
                                        href="{{ route('tenant.returns.show', $return) }}">View</a></td>
                            </tr>
                        @empty<tr>
                                <td colspan="10" class="listing-empty">No returns match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($returns->hasPages())
                <div class="listing-table-footer">{{ $returns->links() }}</div>
            @endif
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
