@extends('layouts.app')

@section('title', 'Super Admin Dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/super-admin-dashboard.css') }}">
@endpush

@section('content')
    <div class="container-fluid commerce-dashboard">
        <div class="commerce-heading">
            <div>
                <h1>Dashboard</h1>
            </div>
        </div>
        <div class="commerce-metrics">
            <article class="commerce-card commerce-metric">
                <span class="commerce-metric-icon commerce-tone-blue"><i class="fa-solid fa-users"
                        aria-hidden="true"></i></span>
                <div>
                    <h2>Total Tenants</h2><strong>{{ $registrations['total'] }}</strong>
                    <p
                        title="New central business registrations this month to date versus the same elapsed period last month.">
                        <span
                            class="commerce-growth {{ str_starts_with($registrationGrowth, '-') ? 'text-danger' : '' }}">{{ $registrationGrowth }}</span>
                        new registrations, month to date
                    </p>
                </div>
            </article>
            <article class="commerce-card commerce-metric">
                <span class="commerce-metric-icon commerce-tone-purple"><i class="fa-solid fa-cube"
                        aria-hidden="true"></i></span>
                <div>
                    <h2>Active Subscriptions</h2><strong>{{ $active }}</strong>
                    <p>Current business accounts</p>
                </div>
            </article>
            <article class="commerce-card commerce-metric">
                <span class="commerce-metric-icon commerce-tone-green"><i class="fa-solid fa-wallet"
                        aria-hidden="true"></i></span>
                <div>
                    <h2>Total Revenue</h2>
                    @foreach ($revenues as $revenue)
                        <strong>{{ $revenue['formatted'] }}</strong>
                        <p
                            title="Captured collections this month to date versus the same elapsed period last month; currencies are never combined.">
                            <span
                                class="commerce-growth {{ str_starts_with($revenue['growth'], '-') ? 'text-danger' : '' }}">{{ $revenue['growth'] }}</span>
                            {{ count($revenues) > 1 ? $revenue['currency'] : '' }} collections, month to date
                        </p>
                    @endforeach
                </div>
            </article>
            <article class="commerce-card commerce-metric">
                <span class="commerce-metric-icon commerce-tone-orange"><i class="fa-solid fa-clock"
                        aria-hidden="true"></i></span>
                <div>
                    <h2>Expiring Soon</h2><strong>{{ $expiring }}</strong>
                    <p>in next 7 days</p>
                </div>
            </article>
        </div>
        <div class="commerce-charts">
            <section class="commerce-card" aria-labelledby="revenue-title" data-dashboard-chart="revenue"
                data-url="{{ route('super-admin.dashboard.chart') }}">
                <div class="commerce-card-heading">
                    <h2 id="revenue-title">Revenue Overview</h2>
                    <div class="commerce-chart-filters">
                        @if (count($currencies) > 1)
                            <select class="commerce-period" aria-label="Revenue currency" data-chart-currency>
                                @foreach ($currencies as $code)
                                    <option value="{{ $code }}" @selected($currency === $code)>{{ $code }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <select class="commerce-period" aria-label="Revenue period" data-chart-period>
                            <option value="6">Last 6 Months</option>
                            <option value="12">Last 12 Months</option>
                        </select>
                    </div>
                </div>
                <p data-chart-status role="status" class="commerce-chart-status" hidden></p>
                <div data-chart-content>@include('super-admin.dashboard-revenue', ['chart' => $revenueChart])</div>
            </section>
            <section class="commerce-card" aria-labelledby="subscription-title">
                <div class="commerce-card-heading">
                    <h2 id="subscription-title">Subscription Status</h2>
                </div>
                <div class="commerce-subscriptions">
                    <div class="commerce-donut" role="img" aria-label="{{ $subscriptionDescription }}"
                        style="background: {{ $donut }}">
                        <div><strong>{{ $subscriptionTotal }}</strong><span>Total</span></div>
                    </div>
                    <ul class="commerce-legend">
                        @foreach ($legend as $item)
                            <li><span
                                    class="commerce-dot commerce-dot-{{ $item['key'] }}"></span><span>{{ $item['label'] }}</span><span><strong>{{ $item['count'] }}</strong>
                                    <span class="commerce-muted">({{ $item['percentage'] }}%)</span></span></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>
        <div class="commerce-secondary-charts">
            <section class="commerce-card" aria-labelledby="packages-revenue-title" data-dashboard-chart="packages"
                data-url="{{ route('super-admin.dashboard.chart') }}">
                <div class="commerce-card-heading">
                    <h2 id="packages-revenue-title">Top Packages by Revenue</h2>
                    <div class="commerce-chart-filters">
                        @if (count($currencies) > 1)
                            <select class="commerce-period" aria-label="Package revenue currency" data-chart-currency>
                                @foreach ($currencies as $code)
                                    <option value="{{ $code }}" @selected($currency === $code)>{{ $code }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <select class="commerce-period" aria-label="Package revenue period" data-chart-period>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="last_3_months">Last 3 Months</option>
                            <option value="last_6_months">Last 6 Months</option>
                            <option value="this_year">This Year</option>
                            <option value="all_time">All Time</option>
                        </select>
                    </div>
                </div>
                <p data-chart-status role="status" class="commerce-chart-status" hidden></p>
                <div data-chart-content>@include('super-admin.dashboard-packages', ['chart' => $packageChart])</div>
            </section>
            <section class="commerce-card" aria-labelledby="new-users-title" data-dashboard-chart="users"
                data-url="{{ route('super-admin.dashboard.chart') }}">
                <div class="commerce-card-heading">
                    <h2 id="new-users-title">New Users Timeline</h2>
                    <select class="commerce-period" aria-label="New users period" data-chart-period>
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="this_year" selected>This Year</option>
                    </select>
                </div>
                <p data-chart-status role="status" class="commerce-chart-status" hidden></p>
                <div data-chart-content>@include('super-admin.dashboard-users', ['chart' => $usersChart])</div>
            </section>
        </div>
        <div class="commerce-tables">
            <section class="commerce-card" aria-labelledby="payments-title">
                <div class="commerce-card-heading">
                    <h2 id="payments-title">Recent Payments</h2><a
                        href="{{ route('super-admin.payment-history.index') }}">View All<span class="visually-hidden">
                            payments</span></a>
                </div>
                <div class="table-responsive">
                    <table class="table commerce-table align-middle mb-0">
                        <caption class="visually-hidden">Recent live platform payments and attempts</caption>
                        <thead>
                            <tr>
                                <th scope="col">Sr.No</th>
                                <th scope="col">Tenant / User</th>
                                <th scope="col">Package</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Status</th>
                                <th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentPayments as $payment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><span class="commerce-tenant"><span
                                                class="commerce-avatar commerce-tone-{{ ['purple', 'pink', 'orange', 'red', 'blue'][$loop->index] }}">{{ $payment['initials'] }}</span>{{ $payment['name'] }}</span>
                                    </td>
                                    <td>{{ $payment['package'] }}</td>
                                    <td>{{ $payment['amount'] }}</td>
                                    <td><span
                                            class="commerce-badge commerce-badge-{{ $payment['tone'] }}">{{ $payment['status'] }}</span>
                                    </td>
                                    <td>{{ $payment['date'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">No payment records yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="commerce-card" aria-labelledby="renewals-title">
                <div class="commerce-card-heading">
                    <h2 id="renewals-title">Upcoming Renewals</h2><span class="commerce-muted commerce-table-note">Next 7
                        days</span>
                </div>
                <div class="table-responsive">
                    <table class="table commerce-table align-middle mb-0">
                        <caption class="visually-hidden">Current business subscriptions expiring in the next seven days
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Tenant</th>
                                <th scope="col">Package</th>
                                <th scope="col">Expiry Date</th>
                                <th scope="col">Days Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($renewals as $renewal)
                                <tr>
                                    <td><span class="commerce-tenant"><span
                                                class="commerce-avatar commerce-tone-{{ ['purple', 'pink', 'orange', 'blue', 'blue'][$loop->index] }}">{{ $renewal['initials'] }}</span>{{ $renewal['name'] }}</span>
                                    </td>
                                    <td>{{ $renewal['package'] }}</td>
                                    <td>{{ $renewal['expiry'] }}</td>
                                    <td><span
                                            class="commerce-badge commerce-badge-{{ $renewal['urgency'] }}">{{ $renewal['days'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4">No subscriptions expire in the next 7
                                        days.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/super-admin-dashboard.js') }}" defer></script>
@endpush
