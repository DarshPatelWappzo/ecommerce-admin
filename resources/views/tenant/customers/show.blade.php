@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Customer details')
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <h1 class="page-title">{{ $customer->first_name }} {{ $customer->last_name }}</h1>
            <div class="d-flex gap-2">@include('tenant.customers._actions')<a class="btn btn-sm btn-light"
                    href="{{ route('tenant.customers.index') }}">Back to customers</a></div>
        </div>
        @include('tenant.customers._notifications')
        <section class="dashboard-card mb-4">
            <h2 class="section-title">Profile</h2>
            <dl class="row mb-0">
                @foreach (['customer_code' => 'Customer code', 'email' => 'Email', 'phone_country_code' => 'Calling code', 'phone' => 'Mobile', 'customer_type' => 'Customer type', 'status' => 'Status', 'company_name' => 'Registered business name', 'gstin' => 'GSTIN'] as $field => $label)
                    <dt class="col-sm-4">{{ $label }}</dt>
                    <dd class="col-sm-8">{{ $customer->$field ?? '—' }}</dd>
                @endforeach
                <dt class="col-sm-4">Joined date</dt>
                <dd class="col-sm-8">{{ $customer->created_at->format('d M Y H:i') }}</dd>
                <dt class="col-sm-4">Internal notes</dt>
                <dd class="col-sm-8 text-break">{{ $customer->notes ?? '—' }}</dd>
            </dl>
        </section>
        <section class="dashboard-card">
            <div class="d-flex justify-content-between mb-3">
                <h2 class="section-title">Addresses</h2>
                @if ($canAddresses)
                    <a class="btn btn-primary" href="{{ route('tenant.customers.addresses.create', $customer) }}">Add
                        address</a>
                @endif
            </div>
            <div class="row g-3">
                @forelse ($customer->addresses as $address)
                    <div class="col-lg-6">
                        <article class="border rounded p-3 h-100">
                            <h3 class="h6">{{ $address->label ?? 'Address' }}</h3>
                            @if ($address->is_default_shipping)
                                <span class="badge bg-primary mb-2">Default shipping</span>
                            @endif
                            @if ($address->is_default_billing)
                                <span class="badge bg-success mb-2">Default billing</span>
                            @endif
                            <p>{{ $address->recipient_name }}<br>{{ $address->phone_country_code }}
                                {{ $address->phone }}<br>{{ $address->address_line_1 }}<br>
                                @if ($address->address_line_2)
                                    {{ $address->address_line_2 }}<br>
                                @endif
                                @if ($address->landmark)
                                    {{ $address->landmark }}<br>
                                @endif
                                {{ $address->city }},
                                {{ config('customer_locations.' . $address->country_code . '.states.' . $address->state_code, $address->state_code) }}
                                {{ $address->postal_code }}<br>{{ config('customer_locations.' . $address->country_code . '.name', $address->country_code) }}
                            </p>
                            @if ($canAddresses)
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('tenant.customers.addresses.edit', [$customer, $address]) }}">Edit</a>
                                    @foreach (['is_default_shipping' => 'Set shipping default', 'is_default_billing' => 'Set billing default'] as $field => $label)
                                        @if (!$address->$field)
                                            <form method="POST"
                                                action="{{ route('tenant.customers.addresses.default', [$customer, $address]) }}">
                                                @csrf @method('PATCH')<input type="hidden" name="{{ $field }}"
                                                    value="1"><button
                                                    class="btn btn-sm btn-outline-secondary">{{ $label }}</button>
                                            </form>
                                        @endif
                                    @endforeach
                                    <form method="POST"
                                        action="{{ route('tenant.customers.addresses.destroy', [$customer, $address]) }}"
                                        data-confirm-delete="Delete this address?">@csrf @method('DELETE')<button
                                            class="btn btn-sm btn-outline-danger">Delete</button></form>
                                </div>
                            @endif
                        </article>
                </div>@empty<div class="col-12 text-secondary">No addresses saved for this customer.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('js/customer-management.js') }}"></script>
@endpush
