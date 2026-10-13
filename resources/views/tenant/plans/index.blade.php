@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Plans & Subscription')
@section('content')
<div class="container-fluid" data-plans-catalog data-checkout-scope="{{ $checkoutScope }}">
    <div class="listing-page-header mb-4"><div><p class="text-primary fw-semibold mb-1">Settings</p><h1 class="page-title">Plans &amp; Subscription</h1></div></div>
    <section class="dashboard-card mb-4" aria-labelledby="current-subscription-heading">
        <h2 class="h5" id="current-subscription-heading">Current Subscription</h2>
        @if ($subscription['record'])
            @php($current = $subscription['record'])
            <dl class="row mb-0">
                <dt class="col-sm-4">Package</dt><dd class="col-sm-8">{{ $current->package?->name ?? 'Unavailable package' }}</dd>
                <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $subscription['status'] }}</dd>
                <dt class="col-sm-4">Billing cycle</dt><dd class="col-sm-8">{{ $current->billing_cycle ? ucfirst($current->billing_cycle) : 'Not recorded' }}</dd>
                <dt class="col-sm-4">Start date</dt><dd class="col-sm-8">{{ $current->start_date->format('d M Y') }}</dd>
                <dt class="col-sm-4">Valid until</dt><dd class="col-sm-8">{{ $current->end_date?->copy()->subSecond()->format('d M Y H:i:s T') ?? 'Not recorded' }} (expiry is exclusive)</dd>
                <dt class="col-sm-4">Environment</dt><dd class="col-sm-8">{{ ucfirst($current->mode ?? 'live') }}</dd>
                <dt class="col-sm-4">Remaining days</dt><dd class="col-sm-8">{{ $subscription['remaining_days'] ?? 'Not available' }}</dd>
                @if ($current->grace_expires_at)
                    <dt class="col-sm-4">Grace-period expiry</dt><dd class="col-sm-8">{{ $current->grace_expires_at->format('d M Y') }}</dd>
                @endif
            </dl>
        @else
            <p class="fw-semibold">No active subscription</p>
        @endif
        <p class="text-muted mt-3 mb-0">One-time payments. Renewals include seven days of grace after expiry. Package changes and automatic recurring billing are unavailable.</p>
        @if (! $gateway['is_enabled'])
            <p class="alert alert-warning mt-3 mb-0">Platform payment configuration is unavailable. Please contact support.</p>
        @else
            <p class="small mt-3 mb-0">Checkout environment: {{ ucfirst($gateway['mode']) }}. Pricing basis: configured total for the selected billing cycle.</p>
        @endif
    </section>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <h2 class="h4 mb-0">Available plans</h2>
        <div class="btn-group" role="group" aria-label="Billing cycle">
            @foreach (\App\Services\PackagePricingService::CYCLES as $cycle => $months)
                <button type="button" class="btn btn-outline-primary {{ $cycle === 'monthly' ? 'active' : '' }}" data-billing-cycle="{{ $cycle }}" aria-pressed="{{ $cycle === 'monthly' ? 'true' : 'false' }}">{{ ucfirst($cycle) }}</button>
            @endforeach
        </div>
    </div>
    <p class="text-muted">Limits below are configuration previews and are not currently enforced. Products and customers are unlimited.</p>
    <div class="row g-4">
        @forelse ($packages as $package)
            <div class="col-12 col-md-6 col-xl-4">
                <article class="dashboard-card h-100 {{ $subscription['status'] === 'Active' && $subscription['record']?->package_id === $package->id ? 'border border-primary' : '' }}">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @if ($package->is_recommended)<span class="badge text-bg-primary">Recommended</span>@endif
                        @if ($subscription['status'] === 'Active' && $subscription['record']?->package_id === $package->id)<span class="badge text-bg-success">Current package</span>@endif
                    </div>
                    <h3 class="h5">{{ $package->name }}</h3>
                    <p class="text-muted">{{ $package->description ?? 'No description available.' }}</p>
                    @include('tenant.plans._prices')
                    @include('tenant.plans._limits')
                    @foreach (\App\Services\PackagePricingService::CYCLES as $purchaseCycle => $months)
                        @php($purchasePrice = $package->prices->firstWhere('billing_cycle', $purchaseCycle))
                        <div data-plan-cycle="{{ $purchaseCycle }}" @if ($purchaseCycle !== 'monthly') hidden @endif>
                            @if ($canPurchase && $gateway['is_enabled'] && $purchasePrice && $purchasePrice->price >= 1 && (! $subscription['record'] || $subscription['record']->package_id === $package->id))
                                <button type="button" class="btn btn-primary mt-3" 
                                    data-package-checkout="{{ route('tenant.package-payments.checkout', $package->id) }}" 
                                    data-cycle="{{ $purchaseCycle }}" 
                                    data-package="{{ $package->id }}">
                                    {{ $subscription['record'] ? 'Renew Now' : 'Buy Now' }}
                                </button>
                            @else
                                <button type="button" class="btn btn-secondary mt-3" disabled>Purchase unavailable</button>
                            @endif
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-outline-primary mt-3" data-bs-toggle="modal" data-bs-target="#package-details-{{ $package->id }}">View Details<span class="visually-hidden">: {{ $package->name }}</span></button>
                </article>
            </div>
            @push('overlays')
                <div class="modal fade" id="package-details-{{ $package->id }}" tabindex="-1" aria-labelledby="package-title-{{ $package->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
                        <div class="modal-header"><h2 class="modal-title h5" id="package-title-{{ $package->id }}">{{ $package->name }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <h3 class="h6">Package Information</h3>
                            <p>{{ $package->description ?? 'No description available.' }}</p>
                            @include('tenant.plans._prices')
                            <h3 class="h6 mt-4">Usage Limits</h3>
                            <p class="small text-muted">Configured only; not currently enforced. Products and customers are unlimited.</p>
                            @include('tenant.plans._limits')

                            <h3 class="h6 mt-4">Infrastructure Information</h3>
                            @if ($package->public_infrastructure)
                                <p class="small text-muted">Planning estimates only; these are not guaranteed dedicated allocations. Capacity is not a customer or admin-user limit.</p>
                                <p>{{ $package->infrastructure_summary }}</p>
                                <dl class="row">
                                    <dt class="col-sm-6">Expected monthly user capacity</dt><dd class="col-sm-6">{{ number_format($package->min_monthly_users) }} to {{ $package->max_monthly_users === null ? 'Unspecified' : number_format($package->max_monthly_users) }}</dd>
                                    @foreach (['cpu_vcores' => 'CPU vCores', 'ram_gb' => 'RAM (GB)', 'application_servers' => 'Application servers', 'database_type' => 'Database type', 'bandwidth_gb' => 'Bandwidth (GB)', 'storage_gb' => 'Storage (GB)'] as $key => $label)
                                        <dt class="col-sm-6">{{ $label }}</dt><dd class="col-sm-6">{{ $package->{$key} ?? 'Not specified' }}</dd>
                                    @endforeach
                                    @foreach (['backup_included' => 'Backup', 'cdn_included' => 'CDN', 'load_balancer_included' => 'Load balancer'] as $key => $label)
                                        <dt class="col-sm-6">{{ $label }}</dt><dd class="col-sm-6">{{ $package->{$key} === null ? 'Not specified' : ($package->{$key} ? 'Included' : 'Not included') }}</dd>
                                    @endforeach
                                </dl>
                            @else
                                <p class="text-muted">Infrastructure specifications have not been published.</p>
                            @endif
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
                    </div></div>
                </div>
            @endpush
        @empty
            <div class="col-12"><div class="dashboard-card">No packages are currently available.</div></div>
        @endforelse
    </div>
    <section class="dashboard-card listing-table-card mt-4">
        <h2 class="h5 p-3">Package payment history</h2>
        <div class="table-responsive">
            <table class="table listing-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Package / cycle</th>
                        <th>Amount</th>
                        <th>Environment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->reference }}</td>
                            <td>{{ $payment->quote['name'] }} / {{ ucfirst($payment->billing_cycle) }}</td>
                            <td>{{ $payment->currency }} {{ number_format($payment->amount / 100, 2) }}</td>
                            <td>{{ ucfirst($payment->mode) }}</td>
                            <td>{{ ucfirst($payment->status) }}</td>
                            <td>
                                @if ($canPurchase && ! in_array($payment->status, ['captured', 'rejected'], true))
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                        data-package-resume="{{ route('tenant.package-payments.show', $payment->id) }}">
                                        Check / resume
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty 
                        <tr>
                            <td colspan="6" class="listing-empty">No package payments yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="listing-table-footer">{{ $payments->links() }}</div>
    </section>
    <div data-package-payment-urls data-status="{{ route('tenant.package-payments.show', 0) }}" data-verify="{{ route('tenant.package-payments.verify', 0) }}"></div>
</div>
@endsection
@push('scripts')
    <script src="{{ asset('js/plans-catalog.js') }}" defer></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
    <script src="{{ asset('js/package-checkout.js') }}" defer></script>
@endpush
