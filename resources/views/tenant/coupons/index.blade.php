@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Coupons')
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-4">
            <h1 class="page-title">Coupons</h1>
            @if ($canCreate)
                <a class="btn btn-primary" href="{{ route('tenant.coupons.create') }}">Add coupon</a>
            @endif
        </div>
        @include('tenant.customers._notifications')
        @include('tenant.partials.validation-errors')
        <section class="dashboard-card p-0 overflow-hidden" data-ajax-pagination-container>
            <div class="p-3 border-bottom">
                <label class="visually-hidden" for="tenant-coupon-search">Search coupons</label>
                <input class="form-control" id="tenant-coupon-search" type="search" value="{{ request('search') }}"
                    placeholder="Search coupons..." data-ajax-search>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Discount</th>
                            <th>Status</th>
                            <th>Validity</th>
                            <th>Usage</th>
                            <th>Actions</th>
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
                                <td colspan="6" class="text-center text-muted">No coupons found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($coupons->hasPages())
                <div class="border-top p-3">{{ $coupons->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ajax-pagination.js') }}"></script>
@endpush
