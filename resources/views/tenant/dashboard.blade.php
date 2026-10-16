@extends('layouts.app', ['sidebarView' => 'tenant.partials.sidebar', 'headerView' => 'tenant.partials.header'])
@section('title', 'Admin Dashboard')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/tenant-dashboard.css') }}">
@endpush
@section('content')
    @php($access = $dashboard['access'])
    <div class="store-dashboard">
        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        <header class="store-welcome">
            <div>
                <h1>Dashboard</h1>
            </div>
            <div class="store-periods" aria-label="Reporting period">
                @foreach (['today' => 'Today', '7' => '7 Days', '30' => '30 Days', 'this_month' => 'This Month', 'this_year' => 'This Year'] as $value => $label)
                    <a href="{{ route('tenant.dashboard', ['period' => $value]) }}"
                        class="store-period {{ $dashboard['period'] === (string) $value ? 'is-selected' : '' }}"
                        @if ($dashboard['period'] === (string) $value) aria-current="page" @endif>
                        {{ $label }}
                    </a>
                @endforeach
                <details class="store-custom-period" @if ($dashboard['period'] === 'custom' || $errors->any()) open @endif>
                    <summary class="store-period {{ $dashboard['period'] === 'custom' ? 'is-selected' : '' }}">
                        Custom
                    </summary>
                    <form method="GET" action="{{ route('tenant.dashboard') }}" class="store-date-form" novalidate>
                        <input type="hidden" name="period" value="custom">
                        <div>
                            <label for="dashboard-from">From</label>
                            <input id="dashboard-from" type="date" name="from"
                                value="{{ old('from', $dashboard['from']) }}">
                            @error('from')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label for="dashboard-to">To</label>
                            <input id="dashboard-to" type="date" name="to"
                                value="{{ old('to', $dashboard['to']) }}">
                            @error('to')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    </form>
                </details>
            </div>
        </header>
        @error('period')
            <p class="field-error" role="alert">{{ $message }}</p>
        @enderror
        {{-- <p class="store-report-context">{{ $dashboard['range_label'] }} &middot; INR &middot; Changes vs
            {{ $dashboard['comparison_label'] }}</p> --}}
        <div class="store-metrics">
            @foreach ($dashboard['metrics'] as $metric)
                <section class="store-card store-metric store-tone-{{ $metric['tone'] }}">
                    <span class="store-icon">
                        <i class="fa-solid fa-{{ $metric['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h2>{{ $metric['label'] }}</h2>
                        <strong>{{ $metric['value'] }}</strong>
                        <p
                            class="store-growth {{ $metric['trend']['negative'] ? 'is-negative' : '' }} {{ $metric['trend']['neutral'] ? 'is-neutral' : '' }}">
                            {{ $metric['trend']['text'] }}
                        </p>
                    </div>
                </section>
            @endforeach
        </div>
        @if ($access['orders'] || $access['payments'])
            <div class="store-charts {{ !$access['orders'] || !$access['payments'] ? 'store-charts-limited' : '' }}">
                <section class="store-card">
                    <div class="store-section-heading">
                        <h2>Sales &amp; Orders Overview</h2>
                        <span class="store-chip">{{ $dashboard['chart']['monthly'] ? 'Monthly' : 'Daily' }}</span>
                    </div>
                    <div class="store-chart-legend">
                        @if ($access['payments'])
                            <span>
                                <i style="--dot: #2475ff"></i>
                                Captured Revenue (INR)
                            </span>
                        @endif
                        @if ($access['orders'])
                            <span>
                                <i style="--dot: #13b765"></i>
                                Orders
                            </span>
                        @endif
                    </div>
                    <svg class="store-line-chart" viewBox="0 0 780 215" role="img"
                        aria-labelledby="store-sales-title store-sales-description">
                        <title id="store-sales-title">Sales and orders for {{ $dashboard['range_label'] }}</title>
                        <desc id="store-sales-description">Captured INR revenue and non-draft order counts. Each point
                            includes its date and value.</desc>
                        @foreach ($dashboard['chart']['axis'] as $tick)
                            <line x1="65" y1="{{ $tick['y'] }}" x2="720" y2="{{ $tick['y'] }}"
                                stroke="#e8eff9" />
                            <g class="store-axis">
                                @if ($access['payments'])
                                    <text x="60" y="{{ $tick['y'] + 4 }}" text-anchor="end">{{ $tick['revenue'] }}</text>
                                @endif
                                @if ($access['orders'])
                                    <text x="730" y="{{ $tick['y'] + 4 }}">{{ $tick['orders'] }}</text>
                                @endif
                            </g>
                        @endforeach
                        @if ($access['payments'])
                            <path d="{{ $dashboard['chart']['revenue_path'] }}" fill="none" stroke="#2475ff"
                                stroke-width="2.5" stroke-linejoin="round" />
                        @endif
                        @if ($access['orders'])
                            <path d="{{ $dashboard['chart']['orders_path'] }}" fill="none" stroke="#13b765"
                                stroke-width="2.5" stroke-linejoin="round" />
                        @endif
                        @foreach ($dashboard['chart']['points'] as $point)
                            @if ($access['payments'])
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['revenue_y'] }}" r="3" fill="#2475ff">
                                    <title>{{ $point['label'] }}: {{ $point['amount'] }} captured</title>
                                </circle>
                            @endif
                            @if ($access['orders'])
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['orders_y'] }}" r="3" fill="#13b765">
                                    <title>{{ $point['label'] }}: {{ $point['orders'] }} orders</title>
                                </circle>
                            @endif
                            @if ($point['show_label'])
                                <text class="store-axis" x="{{ $point['x'] }}" y="205"
                                    text-anchor="middle">{{ $point['label'] }}</text>
                            @endif
                        @endforeach
                    </svg>
                    @if ($dashboard['chart']['empty'])
                        <p class="store-empty">No activity in this period.</p>
                    @endif
                </section>
                @foreach (['orders' => ['order_status', 'Order Status', 'Total Orders'], 'payments' => ['payment_status', 'Payment Status', 'Attempts']] as $permission => [$key, $heading, $totalLabel])
                    @if ($access[$permission])
                        <section class="store-card">
                            <div class="store-section-heading">
                                <h2>{{ $heading }}</h2>

                            </div>
                            <div class="store-status-chart">
                                <div class="store-donut store-payment-donut"
                                    style="background: {{ $dashboard[$key]['gradient'] }}" role="img"
                                    aria-label="{{ $dashboard[$key]['total'] }} {{ strtolower($totalLabel) }}. Breakdown in adjacent legend.">
                                    <div>
                                        <strong>{{ number_format($dashboard[$key]['total']) }}</strong><span>{{ $totalLabel }}</span>
                                    </div>
                                </div>
                                <ul class="store-status-legend">
                                    @forelse ($dashboard[$key]['legend'] as $status)
                                        <li>
                                            <span>
                                                <i style="--dot: {{ $status['color'] }}"></i>
                                                {{ $status['label'] }}
                                            </span>
                                            <span>
                                                {{ $status['count'] }}
                                                ({{ $status['percent'] }})
                                            </span>
                                        </li>
                                    @empty
                                        <li>No records in this period.</li>
                                    @endforelse
                                </ul>
                            </div>
                            @if ($permission === 'payments')
                                <p class="store-panel-note">Payment attempts created in this period; retries count
                                    separately.</p>
                            @endif
                        </section>
                    @endif
                @endforeach
            </div>
        @endif
        <div class="store-tables">
            @if ($access['products'] && $access['orders'])
                <section class="store-card">
                    <div class="store-section-heading">
                        <h2>Top Selling Products</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="store-table">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Units</th>
                                    <th scope="col">Order Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dashboard['products'] as $product)
                                    <tr>
                                        <td class="store-product-name" title="{{ $product['name'] }}">
                                            {{ $product['name'] }}</td>
                                        <td>{{ $product['sold'] }}</td>
                                        <td>{{ $product['revenue'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="store-empty">No products sold in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="store-panel-note">Confirmed through delivered orders, including item tax; before refunds.</p>
                </section>
            @endif
            @if ($access['customers'])
                <section class="store-card">
                    <div class="store-section-heading">
                        <h2>New Customers</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="store-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    @if ($access['orders'])
                                        <th scope="col">Orders</th>
                                        @endif @if ($access['payments'])
                                            <th scope="col">Collected</th>
                                        @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dashboard['customers'] as $customer)
                                    <tr>
                                        <td>
                                            <span class="store-customer-avatar">
                                                {{ $customer['initials'] }}
                                            </span>
                                            {{ $customer['name'] }}
                                        </td>
                                        @if ($access['orders'])
                                            <td>{{ $customer['orders'] }}</td>
                                            @endif @if ($access['payments'])
                                                <td>{{ $customer['spent'] }}</td>
                                            @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 1 + (int) $access['orders'] + (int) $access['payments'] }}"
                                            class="store-empty">No new customers in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p class="store-panel-note">Registrations and activity within the selected period.</p>
                </section>
            @endif
            @if ($access['payments'])
                <section class="store-card">
                    <div class="store-section-heading">
                        <h2>Recent Payments</h2>
                        <a class="store-orders-view-all" href="{{ route('tenant.payments.index') }}">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="store-table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dashboard['payments'] as $payment)
                                    <tr>
                                        <td>#PAY-{{ $payment['id'] }}</td>
                                        <td>{{ $payment['customer'] }}</td>
                                        <td>{{ $payment['amount'] }}</td>
                                        <td>
                                            <span
                                                class="store-badge store-order-status store-tone-{{ $payment['tone'] }}">
                                                {{ $payment['status'] }}
                                            </span>
                                        </td>
                                        <td>{{ $payment['date'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="store-empty">No payments in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
        <div class="store-insights">
            @if ($dashboard['return_metrics'])
                <section class="store-card store-returns-panel" aria-labelledby="store-returns-title">
                    <div class="store-section-heading">
                        <h2 id="store-returns-title">Returns, Replacements &amp; Refunds</h2>
                    </div>
                    <div class="store-return-metrics">
                        @foreach ($dashboard['return_metrics'] as $metric)
                            <div class="store-return-metric store-tone-{{ $metric['tone'] }}"><span class="store-icon"><i
                                        class="fa-solid fa-{{ $metric['icon'] }}" aria-hidden="true"></i></span>
                                <div>
                                    <h3>{{ $metric['label'] }}</h3><strong>{{ $metric['value'] }}</strong>
                                    <p
                                        class="store-growth {{ $metric['trend']['negative'] ? 'is-negative' : '' }} {{ $metric['trend']['neutral'] ? 'is-neutral' : '' }}">
                                        {{ $metric['trend']['text'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="store-return-rates">
                        @foreach ($dashboard['rates'] as $rate)
                            <div class="store-return-rate">
                                <span>{{ $rate['label'] }}</span>
                                <div class="store-rate-row">
                                    <div class="store-rate-track" role="meter" aria-label="{{ $rate['label'] }}"
                                        aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $rate['value'] }}">
                                        <span style="width: {{ $rate['value'] }}%"></span>
                                    </div>
                                    <strong>{{ number_format($rate['value'], 1) }}%</strong>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="store-panel-note">Requests exclude rejected/cancelled entries and replacements converted to
                        refunds. Refunds count only processed INR amounts.</p>
                    @if ($dashboard['rates'])
                        <p class="store-panel-note">Rates: non-cancelled orders placed in this period with a qualifying
                            request, counted once per order.</p>
                    @endif
                </section>
            @endif
            @if ($access['orders'])
                <section class="store-card store-orders-panel" aria-labelledby="store-orders-title">
                    <div class="store-section-heading">
                        <h2 id="store-orders-title">Recent Orders</h2>
                        <a class="store-orders-view-all"
                            href="{{ route('tenant.orders.index', ['from' => $dashboard['from'], 'to' => $dashboard['to']]) }}">
                            View All
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="store-table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col">Order Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dashboard['orders'] as $order)
                                    <tr>
                                        <td>{{ $order['number'] }}</td>
                                        <td>{{ $order['customer'] }}</td>
                                        <td>{{ $order['amount'] }}</td>
                                        <td>
                                            <span
                                                class="store-badge store-order-status store-tone-{{ $order['payment_tone'] }}">
                                                {{ $order['payment'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="store-badge store-order-status store-tone-{{ $order['tone'] }}">
                                                {{ $order['status'] }}
                                            </span>
                                        </td>
                                        <td>{{ $order['date'] }}</td>
                                        <td>
                                            <a class="store-order-view"
                                                href="{{ route('tenant.orders.show', $order['id']) }}"
                                                aria-label="View order {{ $order['number'] }}">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="store-empty">No orders in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
        @if (!$dashboard['metrics'] && !$dashboard['return_metrics'])
            <section class="store-card">
                <p>No reporting sections are available for your current permissions.</p>
            </section>
        @endif
        <p class="store-sample-note">INR reporting. Revenue is captured funds before refunds; orders exclude drafts.
            Catalog and stock are current, not historical. Dates use {{ config('app.timezone') }}.</p>
    </div>
@endsection
