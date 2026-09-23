@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $address->exists ? 'Edit address' : 'Add address')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">{{ $address->exists ? 'Edit address' : 'Add address' }} — {{ $customer->first_name }}
        </h1>
        @include('tenant.customers._notifications')
        <form id="customer-address-form" class="dashboard-card" method="POST"
            action="{{ $address->exists ? route('tenant.customers.addresses.update', [$customer, $address]) : route('tenant.customers.addresses.store', $customer) }}"
            novalidate>
            @csrf @if ($address->exists)
                @method('PUT')
            @endif
            <div class="row g-3">
                @foreach (['label' => ['Label (Home, Office or custom)', 50], 'recipient_name' => ['Recipient name', 200], 'phone_country_code' => ['Country calling code', 5], 'phone' => ['Mobile number', 20], 'address_line_1' => ['Address line 1', 255], 'address_line_2' => ['Address line 2 (optional)', 255], 'landmark' => ['Landmark (optional)', 255], 'city' => ['City', 100], 'postal_code' => ['Postal code', 20]] as $field => [$label, $length])
                    <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input
                            class="form-control" id="{{ $field }}" name="{{ $field }}"
                            maxlength="{{ $length }}" value="{{ old($field, $address->$field) }}"></div>
                @endforeach
                <div class="col-md-6"><label class="form-label" for="country_code">Country</label><select
                        class="form-select" id="country_code" name="country_code">
                        @foreach (config('customer_locations') as $code => $country)
                            <option value="{{ $code }}" @selected(old('country_code', $address->country_code) === $code)>{{ $country['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="state_code">State / Union territory</label><select
                        class="form-select" id="state_code" name="state_code">
                        <option value="">Select a state</option>
                        @foreach (config('customer_locations.IN.states') as $code => $name)
                            <option value="{{ $code }}" @selected(old('state_code', $address->state_code) === $code)>{{ $name }}</option>
                        @endforeach
                    </select></div>
                @foreach (['is_default_shipping' => 'Default shipping address', 'is_default_billing' => 'Default billing address'] as $field => $label)
                    <div class="col-md-6"><input type="hidden" name="{{ $field }}" value="0"><label
                            class="form-check"><input class="form-check-input" type="checkbox" name="{{ $field }}"
                                value="1" @checked(old($field, $address->$field))> {{ $label }}</label></div>
                @endforeach
            </div>
            <p class="form-text mt-3">The first saved address becomes the shipping and billing default.</p>
            <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light"
                    href="{{ route('tenant.customers.show', $customer) }}">Cancel</a><button
                    class="btn btn-primary">{{ $address->exists ? 'Update address' : 'Save address' }}</button></div>
        </form>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::formRequest(
        App\Http\Requests\TenantCustomerAddressSaveRequest::class,
        '#customer-address-form',
    ) !!}
    @include('tenant.customers._server-errors', ['selector' => '#customer-address-form'])
    <script src="{{ asset('js/customer-management.js') }}"></script>
@endpush
