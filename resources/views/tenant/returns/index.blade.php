@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Returns')
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Returns</h1>
            @if ($canManageReasons)
                <a class="btn btn-outline-primary" href="{{ route('tenant.returns.reasons') }}">Return reasons</a>
            @endif
        </div>
        @include('tenant.customers._notifications')
        <section class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <form class="row g-2 p-3 border-bottom" method="GET">
                @foreach (['search' => 'Return number', 'order_number' => 'Order number', 'customer_id' => 'Customer ID'] as $field => $label)
                    <div class="col-md-2"><label class="form-label"
                            for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}"
                            class="form-control" name="{{ $field }}" value="{{ request($field) }}"></div>
                @endforeach
                <div class="col-md-2"><label class="form-label" for="status">Status</label><select id="status"
                        class="form-select" name="status">
                        <option value="">All</option>
                        @foreach (array_keys(\App\Models\Tenant\ReturnRequest::TRANSITIONS) as $status)
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
                            @foreach (['Return #', 'Order #', 'Customer', 'Product', 'Quantity', 'Reason', 'Requested', 'Status', 'Refund amount', 'Action'] as $heading)
                                <th>{{ $heading }}</th>
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
                                <td colspan="10" class="text-center text-muted py-5">No returns match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($returns->hasPages())
                <div class="border-top p-3">{{ $returns->links() }}</div>
            @endif
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
