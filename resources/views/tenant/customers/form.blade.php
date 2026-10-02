@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $customer->exists ? 'Edit customer' : 'Add customer')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">{{ $customer->exists ? 'Edit customer' : 'Add customer' }}</h1>
        @include('tenant.customers._notifications')
        <form id="customer-form" class="dashboard-card" method="POST"
            action="{{ $customer->exists ? route('tenant.customers.update', $customer) : route('tenant.customers.store') }}"
            novalidate>
            @csrf @if ($customer->exists)
                @method('PUT')
            @endif
            <div class="row g-3">
                @foreach (['first_name' => ['First name', 100], 'last_name' => ['Last name', 100], 'email' => ['Email', 254], 'phone_country_code' => ['Country calling code', 5], 'phone' => ['Mobile number', 20]] as $field => [$label, $length])
                    <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input
                            class="form-control" id="{{ $field }}" name="{{ $field }}"
                            type="{{ $field === 'email' ? 'email' : 'text' }}" maxlength="{{ $length }}"
                            value="{{ old($field, $customer->$field) }}"
                            @if ($field === 'phone_country_code') placeholder="+91" @endif></div>
                @endforeach
                <div class="col-12 text-secondary">Provide at least one email address or mobile number. A mobile number
                    requires its calling code.</div>
                @foreach (['customer_type' => ['individual' => 'Individual', 'business' => 'Business'], 'status' => ['active' => 'Active', 'inactive' => 'Inactive']] as $field => $options)
                    <div class="col-md-6"><label class="form-label"
                            for="{{ $field }}">{{ $field === 'status' ? 'Status' : 'Customer type' }}</label><select
                            class="form-select" id="{{ $field }}" name="{{ $field }}">
                            @foreach ($options as $value => $label)
                                <option value="{{ $value }}" @selected(old($field, $customer->$field) === $value)>{{ $label }}
                                </option>
                            @endforeach
                        </select></div>
                @endforeach
                <div class="col-md-6" data-business-field><label class="form-label" for="company_name">Registered business
                        name</label><input class="form-control" id="company_name" name="company_name" maxlength="255"
                        value="{{ old('company_name', $customer->company_name) }}"></div>
                <div class="col-md-6" data-business-field><label class="form-label" for="gstin">GSTIN
                        (optional)</label><input class="form-control" id="gstin" name="gstin" maxlength="15"
                        value="{{ old('gstin', $customer->gstin) }}">
                    <div class="form-text">The format is checked; registration is not verified.</div>
                </div>
                <div class="col-12"><label class="form-label" for="notes">Internal notes (admin only)</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" maxlength="10000">{{ old('notes', $customer->notes) }}</textarea>
                </div>
            </div>
            @if (!$customer->exists)
                <p class="text-secondary mt-3">You can add delivery and billing addresses from the customer details page
                    after saving.</p>
            @endif
            <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light"
                    href="{{ route('tenant.customers.index') }}">Cancel</a><button
                    class="btn btn-primary">{{ $customer->exists ? 'Update customer' : 'Save customer' }}</button></div>
        </form>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::formRequest(App\Http\Requests\TenantCustomerSaveRequest::class, '#customer-form') !!}
    @include('tenant.customers._server-errors', ['selector' => '#customer-form'])
    <script src="{{ asset('js/customer-management.js') }}"></script>
@endpush
