@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $coupon->exists ? 'Edit Coupon' : 'Add Coupon')
@section('content')
    <div class="container-fluid">
        <h1 class="page-title mb-4">{{ $coupon->exists ? 'Edit Coupon' : 'Add Coupon' }}</h1>
        <form id="tenant-coupon-form" method="POST"
            action="{{ $coupon->exists ? route('tenant.coupons.update', $coupon) : route('tenant.coupons.store') }}">
            @csrf
            @if ($coupon->exists)
                @method('PUT')
            @endif
            <section class="dashboard-card">
                @include('tenant.partials.validation-errors')
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="code">Coupon code</label>
                        <input class="form-control" id="code" name="code" required maxlength="50"
                            value="{{ old('code', $coupon->code) }}">
                        <div class="form-text">Letters, numbers, hyphens and underscores. Codes are saved in uppercase.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="is_active">Status</label>
                        <select class="form-select" id="is_active" name="is_active">
                            <option value="1" @selected(old('is_active', $coupon->is_active) == 1)>Enabled</option>
                            <option value="0" @selected(old('is_active', $coupon->is_active) == 0)>Disabled</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="discount_type">Discount type</label>
                        <select class="form-select" id="discount_type" name="discount_type">
                            <option value="percentage" @selected(old('discount_type', $coupon->discount_type) === 'percentage')>Percentage</option>
                            <option value="fixed" @selected(old('discount_type', $coupon->discount_type) === 'fixed')>Fixed (INR)</option>
                        </select>
                    </div>
                    @foreach (['discount_value' => 'Discount value', 'maximum_discount' => 'Maximum discount (INR, optional)', 'minimum_subtotal' => 'Minimum eligible subtotal (INR)'] as $field => $label)
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                            <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="number"
                                min="0" step="0.01" value="{{ old($field, $coupon->$field) }}" @required($field !== 'maximum_discount')>
                        </div>
                    @endforeach
                    @foreach (['starts_at' => 'Starts at (optional)', 'ends_at' => 'Ends at (optional)'] as $field => $label)
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                            <input class="form-control" id="{{ $field }}" name="{{ $field }}"
                                type="datetime-local" value="{{ old($field, $coupon->$field?->format('Y-m-d\TH:i')) }}">
                        </div>
                    @endforeach
                    @foreach (['usage_limit' => 'Total usage limit (optional)', 'per_customer_limit' => 'Per-customer limit (optional)'] as $field => $label)
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                            <input class="form-control" id="{{ $field }}" name="{{ $field }}" type="number"
                                min="1" step="1" value="{{ old($field, $coupon->$field) }}">
                        </div>
                    @endforeach
                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" maxlength="2000">{{ old('description', $coupon->description) }}</textarea>
                    </div>
                    @foreach (['products' => 'Products', 'categories' => 'Categories', 'customers' => 'Customers'] as $relation => $label)
                        <div class="col-md-4">
                            <label class="form-label" for="{{ $relation }}">{{ $label }}</label>
                            <select class="form-select" multiple size="6" id="{{ $relation }}"
                                name="{{ $relation }}[]">
                                @foreach ($choices[$relation] as $choice)
                                    <option value="{{ $choice->id }}" @selected(in_array($choice->id, session()->hasOldInput() ? old($relation, []) : ($coupon->exists ? $coupon->$relation->modelKeys() : [])))>
                                        {{ $choice->name ?? trim($choice->first_name . ' ' . $choice->last_name) . ' - ' . $choice->customer_code }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Leave empty for no {{ strtolower($label) }} restriction.</div>
                        </div>
                    @endforeach
                    <div class="col-12 text-muted">Selected products or categories qualify. The minimum applies to eligible
                        merchandise after other line discounts, before coupon and tax. Shipping is excluded. Dates use
                        {{ config('app.timezone') }}. Customer restrictions and per-customer limits require a registered
                        customer.</div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-light" href="{{ route('tenant.coupons.index') }}">Cancel</a>
                    <button class="btn btn-primary" type="submit">Save coupon</button>
                </div>
            </section>
        </form>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('vendor/jsvalidation/js/jsvalidation.js') }}"></script>
    {!! JsValidator::formRequest(\App\Http\Requests\TenantCouponSaveRequest::class, '#tenant-coupon-form') !!}
@endpush
