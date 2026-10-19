@extends('layouts.app')
@section('title', 'Payment History')
@section('content')
    <div class="container-fluid">
        @php
            $filterFields = [
                'from',
                'to',
                'user_id',
                'tenant_id',
                'domain',
                'package_id',
                'status',
                'fulfillment',
                'gateway',
                'mode',
                'billing_cycle',
                'currency',
                'sort',
                'direction',
                'per_page',
            ];
        @endphp
        <div class="listing-page-header">
            <div>
                <h1 class="page-title mb-0">Payment History</h1>
                <p class="text-secondary mb-0">Central platform package purchases and renewals.</p>
            </div>
            <div class="d-flex gap-2">
                <x-filter-button :filters="array_diff($filterFields, ['sort', 'direction', 'per_page'])" />
                <a class="btn btn-outline-primary" href="{{ route('super-admin.payment-history.export', $filters) }}">Export
                    Excel</a>
            </div>
        </div>
        <x-filter-offcanvas :action="route('super-admin.payment-history.index')" :filters="$filterFields">
            <x-filter-field name="from" label="From date" type="date" />
            <x-filter-field name="to" label="Through date" type="date" />
            <x-filter-field name="user_id" label="Central owner user ID" type="number" min="1" />
            <x-filter-field name="tenant_id" label="Tenant ID" type="number" min="1" />
            <x-filter-field name="domain" label="Assigned domain contains" maxlength="191" />
            <x-filter-field name="package_id" label="Package ID" type="number" min="1" />
            <x-filter-field name="status" label="Financial status (table only)" :options="[
                'creating' => 'Creating',
                'pending' => 'Pending',
                'authorized' => 'Authorized',
                'failed' => 'Failed',
                'rejected' => 'Rejected',
                'captured' => 'Captured',
            ]" />
            <x-filter-field name="fulfillment" label="Subscription fulfillment" :options="[
                'unpaid' => 'Unpaid',
                'activated' => 'Activated',
                'scheduled' => 'Scheduled',
                'resolution_required' => 'Resolution required',
            ]" />
            <x-filter-field name="gateway" label="Gateway" maxlength="30" placeholder="razorpay" />
            <x-filter-field name="mode" label="Mode (default: live)" :options="['live' => 'Live', 'test' => 'Test']" />
            <x-filter-field name="billing_cycle" label="Billing cycle" :options="['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly']" />
            <x-filter-field name="currency" label="Currency code" maxlength="3" placeholder="INR" />
            <x-filter-field name="sort" label="Sort by" :options="[
                'id' => 'Payment ID',
                'amount' => 'Amount (minor units)',
                'paid_at' => 'Capture date',
                'created_at' => 'Attempt creation date',
            ]" />
            <x-filter-field name="direction" label="Sort direction" :options="['desc' => 'Descending', 'asc' => 'Ascending']" />
            <x-filter-field name="per_page" label="Rows per page" :options="[15 => 15, 30 => 30, 50 => 50, 100 => 100]" />
        </x-filter-offcanvas>
        <h2 class="h5">{{ $mode === 'test' ? 'Test collections' : 'Collected payments' }}</h2>
        <p class="text-secondary">Captured payments only, including payments requiring resolution. Financial status filters
            affect the table and export, not these totals. Other filters apply to both.</p>
        <div class="row g-3 mb-3">
            @forelse($earnings as $total)
                <div class="col-md-6">
                    <div class="dashboard-card p-3">
                        <h3 class="h6">{{ strtoupper($mode) }} · {{ $total->currency }}</h3>
                        <p class="mb-1">Total collected: <strong>
                                @if ($total->currency === 'INR')
                                    @php
                                        $paise = str_pad((string) $total->amount_minor, 3, '0', STR_PAD_LEFT);
                                        $rupees = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', substr($paise, 0, -2));
                                    @endphp
                                    ₹{{ $rupees }}.{{ substr($paise, -2) }}
                                @else
                                    {{ $total->amount_minor }} minor units
                                @endif
                            </strong></p>
                        <p class="mb-0">Successful payment count: <strong>{{ $total->payment_count }}</strong></p>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="dashboard-card p-3">No captured payments match these filters. Successful payment count: 0.
                    </div>
                </div>
            @endforelse
        </div>
        <p class="small text-secondary">INR amounts are displayed in rupees in the table, totals, and Excel export. Other
            currencies use
            minor units, separated by currency. Platform refunds are not recorded; refund and net totals are unavailable.
            Purchase type was not recorded and is not inferred.</p>
        <p class="small text-secondary">Dates use capture time for captured payments and attempt creation time for unpaid
            attempts ({{ config('app.timezone') }}). Owner/domain details are current records, not historical identity
            snapshots.</p>
        <div class="dashboard-card listing-table-card">
            <x-listing-search :action="route('super-admin.payment-history.index')" label="Reference, owner, domain or gateway ID" :count="$payments->total()" />
            <div class="table-responsive">
                <table class="table listing-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Owner / tenant</th>
                            <th>Assigned domain</th>
                            <th>Package / cycle</th>
                            <th>Amount</th>
                            <th>Gateway / mode</th>
                            <th>Financial status</th>
                            <th>Fulfillment</th>
                            <th>Date</th>
                            <th>Gateway IDs</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            @if ($payment['currency'] === 'INR')
                                @php
                                    $paymentInPaise = str_pad((string) $payment['amount_minor'], 3, '0', STR_PAD_LEFT);
                                    $paymentInRupees = preg_replace(
                                        '/\B(?=(\d{3})+(?!\d))/',
                                        ',',
                                        substr($paymentInPaise, 0, -2),
                                    );
                                    $amount = '₹' . $paymentInRupees . '.' . substr($paymentInPaise, -2);
                                @endphp
                            @else
                                @php
                                    $amount = $payment['amount_minor'] . ' ' . $payment['currency'];
                                @endphp
                            @endif
                            <tr>
                                <td>{{ $payment['reference'] }}</td>
                                <td>{{ $payment['owner_name'] }} @if ($payment['owner_deleted'])
                                        <span class="badge text-bg-secondary">Deleted user</span>
                                    @endif
                                    <div class="small">{{ $payment['email'] }}</div>
                                    <div class="small text-secondary">{{ $payment['tenant'] }} · Owner
                                        #{{ $payment['owner_id'] }}</div>
                                </td>
                                <td>{{ $payment['domain'] }} @if ($payment['domain_deleted'])
                                        <span class="badge text-bg-secondary">Deleted domain</span>
                                    @endif
                                </td>
                                <td>{{ $payment['package'] }}<div class="small">{{ ucfirst($payment['billing_cycle']) }}
                                    </div>
                                    <div class="small text-secondary">{{ $payment['package_source'] }} · Purchase type:
                                        {{ $payment['purchase_type'] }}</div>
                                </td>
                                <td>{{ $amount }}</strong>
                                </td>
                                <td>{{ $payment['gateway'] }}<div><span
                                            class="badge text-bg-{{ $payment['mode'] === 'test' ? 'warning' : 'primary' }}">{{ strtoupper($payment['mode']) }}</span>
                                    </div>
                                </td>
                                <td>{{ ucfirst($payment['status']) }}<div class="small text-secondary">Checkout:
                                        {{ $payment['checkout_state'] }}</div>
                                </td>
                                <td>{{ Illuminate\Support\Str::headline($payment['fulfillment']) }}</td>
                                <td><span class="small text-secondary">{{ $payment['date_label'] }}</span>
                                    <div>{{ $payment['date'] ?? 'Not recorded' }}</div>
                                </td>
                                <td class="small">{{ $payment['gateway_order_id'] ?? '—' }}<div>
                                        {{ $payment['gateway_payment_id'] ?? '—' }}</div>
                                </td>
                                <td><a class="btn btn-sm btn-outline-primary"
                                        href="{{ route('super-admin.payment-history.show', $payment['id']) }}">
                                        <i class="fa-solid fa-eye me-1 text-primary" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="listing-empty">No platform payments match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="listing-table-footer">{{ $payments->links() }}</div>
            @endif
        </div>
    </div>
@endsection
