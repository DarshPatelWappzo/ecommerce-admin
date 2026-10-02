@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', $coupon->code)
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-4">
            <h1 class="page-title">{{ $coupon->code }}</h1><a class="btn btn-light"
                href="{{ route('tenant.coupons.index') }}">Back to coupons</a>
        </div>
        <section class="dashboard-card">
            <p>{{ $coupon->description }}</p>
            <dl class="row">
                @foreach (['status' => 'Status', 'discount_type' => 'Discount type', 'discount_value' => 'Discount value', 'maximum_discount' => 'Maximum discount (INR)', 'minimum_subtotal' => 'Minimum eligible subtotal (INR)', 'starts_at' => 'Starts at', 'ends_at' => 'Ends at', 'usage_count' => 'Consumed usage', 'usage_limit' => 'Total usage limit', 'per_customer_limit' => 'Per-customer limit'] as $field => $label)
                    <dt class="col-sm-4">{{ $label }}</dt>
                    <dd class="col-sm-8">{{ $coupon->$field ?? 'Not set' }}</dd>
                @endforeach
                @foreach (['products', 'categories', 'customers'] as $relation)
                    <dt class="col-sm-4">{{ ucfirst($relation) }}</dt>
                    <dd class="col-sm-8">
                        @forelse ($coupon->$relation as $record)
                            {{ $record->name ?? trim($record->first_name . ' ' . $record->last_name) . ' - ' . $record->customer_code }}{{ $loop->last ? '' : ', ' }}@empty
                            Unrestricted
                        @endforelse
                    </dd>
                @endforeach
            </dl>
            @if ($canUpdate)
                <a class="btn btn-primary" href="{{ route('tenant.coupons.edit', $coupon) }}">Edit coupon</a>
            @endif
            @if ($canDelete)
                <form class="d-inline" method="POST" action="{{ route('tenant.coupons.destroy', $coupon) }}">@csrf
                    @method('DELETE')<button class="btn btn-outline-danger">Delete coupon</button></form>
            @endif
        </section>
    </div>
@endsection
