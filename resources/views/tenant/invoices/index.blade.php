@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Invoices')
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-4">
            <h1 class="page-title">Invoices</h1>
            @if ($permissions['create'])
                <a class="btn btn-primary" href="{{ route('tenant.invoices.create') }}">Prepare invoice</a>
            @endif
        </div>
        @include('tenant.customers._notifications')
        @include('tenant.partials.validation-errors')
        {{-- <form method="GET" class="dashboard-card mb-4">
        <div class="row g-3">
            @foreach (['search' => 'Search', 'number' => 'Invoice number', 'order_id' => 'Order ID', 'customer_name' => 'Customer', 'from' => 'From date', 'to' => 'To date'] as $field => $label)
            <div class="col-md-3"><label class="form-label" for="{{ $field }}">{{ $label }}</label>
                <input id="{{ $field }}" class="form-control" name="{{ $field }}" type="{{ in_array($field, ['from','to']) ? 'date' : ($field === 'order_id' ? 'number' : 'text') }}" value="{{ request($field) }}">
            </div>
            @endforeach
            @foreach (['status' => ['draft', 'issued'], 'mode' => ['manual', 'automatic']] as $field => $values)
            <div class="col-md-3"><label class="form-label" for="{{ $field }}">{{ ucfirst($field) }}</label>
                <select class="form-select" id="{{ $field }}" name="{{ $field }}"><option value="">All</option>
                @foreach ($values as $value)<option value="{{ $value }}" @selected(request($field) === $value)>{{ ucfirst($value) }}</option>@endforeach
                </select>
            </div>
            @endforeach
        </div>
        <button class="btn btn-primary mt-3">Filter</button> <a class="btn btn-light mt-3" href="{{ route('tenant.invoices.index') }}">Reset</a>
    </form> --}}
        <div class="dashboard-card table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Status / mode</th>
                        <th>Total</th>
                        <th>Current payment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td><a
                                    href="{{ route('tenant.invoices.show', $invoice) }}">{{ $invoice->number ?? 'Draft #' . $invoice->id }}</a>
                            </td>
                            <td>{{ $invoice->order->order_number }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                            <td>{{ ucfirst($invoice->status) }} / {{ $invoice->mode }}</td>
                            <td>{{ $invoice->currency }} {{ $invoice->grand_total }}</td>
                            <td>{{ $invoice->order->payment_status }}</td>
                        </tr>
                    @empty<tr>
                            <td colspan="7" class="text-muted">No invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>{{ $invoices->links() }}
        </div>
    </div>
@endsection
