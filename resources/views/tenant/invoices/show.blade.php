@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $invoice->number ?? 'Invoice draft')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/invoice-document.css') }}">
@endpush
@section('content')
    <div class="container-fluid">
        @include('tenant.customers._notifications')
        @include('tenant.partials.validation-errors')
        @if ($invoice->order->invoice_error)
            <div class="alert alert-warning">{{ $invoice->order->invoice_error }}</div>
        @endif
        <div class="d-flex gap-2 mb-4">
            <a class="btn btn-light" href="{{ route('tenant.invoices.index') }}">Back to invoices</a>
            @if ($invoice->status === 'draft')
                @if ($permissions['update'])
                    <a class="btn btn-outline-primary" href="{{ route('tenant.invoices.edit', $invoice) }}">Edit draft</a>
                @endif
                @if ($permissions['issue'])
                    <form method="POST" action="{{ route('tenant.invoices.issue', $invoice) }}">@csrf<button
                            class="btn btn-primary">Issue invoice</button></form>
                @endif
            @elseif($permissions['download'])
                <a class="btn btn-primary" href="{{ route('tenant.invoices.pdf', $invoice) }}">Download PDF</a>
                <a class="btn btn-outline-primary" target="_blank" rel="noopener"
                    href="{{ route('tenant.invoices.print', $invoice) }}">Print</a>
            @endif
        </div>
        <div class="dashboard-card">@include('tenant.invoices._document')</div>
    </div>
@endsection
