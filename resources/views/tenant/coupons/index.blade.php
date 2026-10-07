@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Coupons')
@section('content')
    <div class="container-fluid">
        <div class="listing-page-header">
            <h1 class="page-title mb-0">Coupons</h1>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($canCreate)
                    <a class="btn btn-primary" href="{{ route('tenant.coupons.create') }}">Add coupon</a>
                @endif
                <x-filter-button :filters="['search', 'discount_type', 'status', 'from', 'to']" />
            </div>
        </div>
        <x-filter-offcanvas :show-errors="false" :action="route('tenant.coupons.index')" :filters="['search', 'discount_type', 'status', 'from', 'to']">
            <x-filter-field name="search" label="Code or description" type="search" maxlength="200" />
            <x-filter-field name="discount_type" label="Discount type" :options="['fixed' => 'Fixed', 'percentage' => 'Percentage']" />
            <x-filter-field name="status" label="Availability" :options="[
                'active' => 'Active',
                'scheduled' => 'Scheduled',
                'expired' => 'Expired',
                'disabled' => 'Disabled',
            ]" />
            <x-filter-field name="from" label="Valid during from" type="date" />
            <x-filter-field name="to" label="Valid during to" type="date" />
        </x-filter-offcanvas>

        @include('tenant.customers._notifications')
        <section class="dashboard-card listing-table-card" data-ajax-pagination-container>

            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Code</th>
                            <th scope="col">Discount</th>
                            <th scope="col">Status</th>
                            <th scope="col">Validity</th>
                            <th scope="col">Usage</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coupons as $coupon)
                            <tr>
                                <td><a href="{{ route('tenant.coupons.show', $coupon) }}">{{ $coupon->code }}</a></td>
                                <td>{{ $coupon->discount_value }}
                                    {{ $coupon->discount_type === 'percentage' ? '%' : 'INR' }}</td>
                                <td><span
                                        class="badge text-bg-{{ $coupon->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($coupon->status) }}</span>
                                </td>
                                <td>{{ $coupon->starts_at?->format('d M Y H:i') ?? 'Any time' }} —
                                    {{ $coupon->ends_at?->format('d M Y H:i') ?? 'No expiry' }}</td>
                                <td>{{ $coupon->usage_count }} / {{ $coupon->usage_limit ?? 'Unlimited' }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        @if ($canUpdate)
                                            <a class="btn btn-sm btn-outline-primary"
                                                href="{{ route('tenant.coupons.edit', $coupon) }}">Edit</a>
                                            <form method="POST" action="{{ route('tenant.coupons.status', $coupon) }}">
                                                @csrf @method('PATCH')<input type="hidden" name="is_active"
                                                    value="{{ $coupon->is_active ? 0 : 1 }}"><button
                                                    class="btn btn-sm btn-outline-secondary">{{ $coupon->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="listing-empty">No coupons found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($coupons->hasPages())
                <div class="listing-table-footer">{{ $coupons->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
