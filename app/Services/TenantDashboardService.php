<?php

namespace App\Services;

use App\Models\Tenant\User;
use App\Repositories\TenantDashboardRepository;
use App\Repositories\TenantRoleRepository;
use Carbon\CarbonImmutable;

class TenantDashboardService
{
    public function __construct(private readonly TenantDashboardRepository $reports, private readonly TenantRoleRepository $roles) {}

    /** @param array{period?: string, from?: string, to?: string} $filters
     * @return array<string, mixed>
     */
    public function overview(User $user, array $filters): array
    {
        $access = [];
        foreach (['orders', 'payments', 'customers', 'products', 'returns', 'replacements'] as $module) {
            $access[$module] = $this->roles->userHasPermission($user, $module . '.view');
        }
        $range = $this->range($filters);
        $from = $range['from'];
        $until = $range['until'];
        $days = (int) $from->diffInDays($until);
        $current = $this->reports->totals($from, $until, $access);
        $previous = $this->reports->totals($from->subDays($days), $from, $access);
        $catalog = $access['products'] ? $this->reports->catalog() : [];
        $metrics = [];
        foreach ([['revenue', 'Total Revenue', 'indian-rupee-sign', 'green'], ['orders', 'Total Orders', 'cart-shopping', 'blue'], ['customers', 'New Customers', 'users', 'purple'], ['products', 'Products', 'box', 'amber'], ['pending', 'Pending Orders', 'file-lines', 'red'], ['low_stock', 'Low Stock', 'cube', 'teal']] as [$key, $label, $icon, $tone]) {
            if (array_key_exists($key, $current) || array_key_exists($key, $catalog)) {
                $value = $current[$key] ?? $catalog[$key];
                $metrics[] = [
                    'key' => $key,
                    'label' => $label,
                    'value' => $key === 'revenue' ? $this->money($value) : number_format($value),
                    'icon' => $icon,
                    'tone' => $tone,
                    'trend' => isset($catalog[$key]) ? ['text' => 'Current catalog', 'negative' => false, 'neutral' => true] : $this->trend($value, $previous[$key], $key === 'pending')
                ];
            }
        }
        $returnMetrics = [];
        foreach ([['returns', 'Returns', 'rotate-left', 'red'], ['replacements', 'Replacements', 'rotate-right', 'purple'], ['refunds', 'Refunds', 'indian-rupee-sign', 'green']] as [$key, $label, $icon, $tone]) {
            if (isset($current[$key])) {
                $returnMetrics[] = ['label' => $label, 'value' => $key === 'refunds' ? $this->money($current[$key]) : number_format($current[$key]), 'icon' => $icon, 'tone' => $tone, 'trend' => $this->trend($current[$key], $previous[$key], true)];
            }
        }
        $rates = [];
        if ($access['orders'] && ($access['returns'] || $access['replacements'])) {
            $counts = $this->reports->rates($from, $until, $access['returns'], $access['replacements']);
            foreach (['returns' => 'Return Rate', 'replacements' => 'Replacement Rate'] as $key => $label) {
                if ($access[$key]) {
                    $rates[] = ['label' => $label, 'value' => $counts['eligible'] > 0 ? round($counts[$key] / $counts['eligible'] * 100, 1) : 0];
                }
            }
        }
        $recentOrders = $access['orders'] ? array_map(fn(object $order): array => [
            'id' => $order->id,
            'number' => $order->order_number,
            'customer' => $order->customer_name,
            'amount' => $this->money($order->grand_total),
            'payment' => $this->label($order->payment_status),
            'payment_tone' => $this->tone($order->payment_status),
            'status' => $this->label($order->status),
            'tone' => $this->tone($order->status),
            'date' => CarbonImmutable::parse($order->order_date)->format('d M Y'),
        ], $this->reports->recentOrders($from, $until)) : [];
        $payments = $access['payments'] ? array_map(fn(object $payment): array => [
            'id' => $payment->id,
            'customer' => $payment->customer_name,
            'amount' => $this->money($payment->amount),
            'status' => $this->label($payment->status),
            'tone' => $this->tone($payment->status),
            'date' => CarbonImmutable::parse($payment->date)->format('d M Y'),
        ], $this->reports->recentPayments($from, $until)) : [];
        $customers = $access['customers'] ? array_map(function (object $customer) use ($access): array {
            $name = trim($customer->first_name . ' ' . $customer->last_name);

            return [
                'name' => $name,
                'initials' => mb_strtoupper(mb_substr($customer->first_name, 0, 1) . mb_substr($customer->last_name ?? '', 0, 1)),
                'orders' => $access['orders'] ? number_format($customer->orders) : null,
                'spent' => $access['payments'] ? $this->money($customer->spent) : null
            ];
        }, $this->reports->newCustomers($from, $until, $access['orders'], $access['payments'])) : [];
        $products = $access['products'] && $access['orders'] ? array_map(fn(object $product): array => [
            'name' => $product->name,
            'sold' => number_format($product->sold),
            'revenue' => $this->money($product->revenue),
        ], $this->reports->topProducts($from, $until)) : [];

        return [
            'access' => $access,
            'period' => $range['period'],
            'from' => $from->toDateString(),
            'to' => $until->subDay()->toDateString(),
            'range_label' => $from->format('d M Y') . ' - ' . $until->subDay()->format('d M Y'),
            'comparison_label' => $from->subDays($days)->format('d M Y') . ' - ' . $from->subDay()->format('d M Y'),
            'metrics' => $metrics,
            'totals' => $current,
            'return_metrics' => $returnMetrics,
            'rates' => $rates,
            'order_status' => $this->donut($access['orders'] ? $this->reports->orderStatuses($from, $until) : []),
            'payment_status' => $this->donut($access['payments'] ? $this->reports->paymentStatuses($from, $until) : []),
            'chart' => $this->chart($from, $until, $access),
            'orders' => $recentOrders,
            'payments' => $payments,
            'customers' => $customers,
            'products' => $products,
        ];
    }

    /** @param array{period?: string, from?: string, to?: string} $filters
     * @return array{period: string, from: CarbonImmutable, until: CarbonImmutable}
     */
    private function range(array $filters): array
    {
        $today = CarbonImmutable::today();
        $period = $filters['period'] ?? '30';
        $from = match ($period) {
            'today' => $today,
            '7' => $today->subDays(6),
            'this_month' => $today->startOfMonth(),
            'this_year' => $today->startOfYear(),
            'custom' => CarbonImmutable::parse($filters['from'])->startOfDay(),
            default => $today->subDays(29),
        };
        $until = $period === 'custom' ? CarbonImmutable::parse($filters['to'])->addDay()->startOfDay() : $today->addDay();

        return compact('period', 'from', 'until');
    }

    private function money(int|float|string $value): string
    {
        [$whole, $fraction] = explode('.', number_format((float) $value, 2, '.', ''));

        return "\u{20B9}" . preg_replace('/\B(?=(\d{2})*\d{3}(?!\d))/', ',', $whole) . '.' . $fraction;
    }

    /** @return array{text: string, negative: bool, neutral: bool} */
    private function trend(int|float $current, int|float $previous, bool $increaseIsBad): array
    {
        if ($previous == 0) {
            return ['text' => $current > 0 ? 'New activity' : 'No change', 'negative' => false, 'neutral' => true];
        }
        $change = ($current - $previous) / $previous * 100;

        return ['text' => ($change > 0 ? '+' : '') . number_format($change, 1) . '%', 'negative' => $increaseIsBad ? $change > 0 : $change < 0, 'neutral' => $change == 0];
    }

    private function label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    private function tone(string $status): string
    {
        return match ($status) {
            'captured', 'paid', 'delivered' => 'green',
            'failed', 'cancelled' => 'red',
            'processing', 'authorized' => 'blue',
            'shipped', 'partially_paid' => 'purple',
            default => 'amber',
        };
    }

    /** @param array<string, int> $counts
     * @return array{total: int, gradient: string, legend: list<array{label: string, count: int, percent: string, color: string}>}
     */
    private function donut(array $counts): array
    {
        $colors = ['delivered' => '#39c78e', 'captured' => '#00bb87', 'shipped' => '#46b4ff', 'processing' => '#9747ff', 'confirmed' => '#86d4ff', 'pending' => '#ffd34f', 'cancelled' => '#ff4b55', 'failed' => '#ff515c', 'authorized' => '#46b4ff', 'created' => '#a8b3c5', 'superseded' => '#bdc7d6'];
        $total = array_sum($counts);
        $offset = 0;
        $stops = [];
        $legend = [];
        foreach ($counts as $status => $count) {
            $share = $total > 0 ? $count / $total * 100 : 0;
            $color = $colors[$status] ?? '#a8b3c5';
            $stops[] = $color . ' ' . round($offset, 4) . '% ' . round($offset + $share, 4) . '%';
            $offset += $share;
            $legend[] = ['label' => $this->label($status), 'count' => $count, 'percent' => number_format($share, 1) . '%', 'color' => $color];
        }

        return ['total' => $total, 'gradient' => $total > 0 ? 'conic-gradient(' . implode(', ', $stops) . ')' : '#e5edf6', 'legend' => $legend];
    }

    /** @param array<string, bool> $access
     * @return array<string, mixed>
     */
    private function chart(CarbonImmutable $from, CarbonImmutable $until, array $access): array
    {
        $revenue = $access['payments'] ? $this->reports->revenueTimeline($from, $until) : [];
        $orders = $access['orders'] ? $this->reports->orderTimeline($from, $until) : [];
        $monthly = $from->diffInDays($until) > 62;
        $points = [];
        for ($day = $from; $day->lessThan($until); $day = $day->addDay()) {
            $key = $day->format($monthly ? 'Y-m' : 'Y-m-d');
            $points[$key] ??= ['label' => $day->format($monthly ? 'M Y' : 'd M'), 'revenue' => 0, 'orders' => 0];
            $points[$key]['revenue'] += $revenue[$day->toDateString()] ?? 0;
            $points[$key]['orders'] += $orders[$day->toDateString()] ?? 0;
        }
        $points = array_values($points);
        $revenueMax = max(1, ceil(max(array_column($points, 'revenue')) / 4) * 4);
        $ordersMax = max(4, ceil(max(array_column($points, 'orders')) / 4) * 4);
        $revenuePath = [];
        $ordersPath = [];
        foreach ($points as $index => &$point) {
            $point['x'] = round(count($points) === 1 ? 390 : 65 + $index * 655 / (count($points) - 1), 2);
            $point['revenue_y'] = round(180 - $point['revenue'] / $revenueMax * 160, 2);
            $point['orders_y'] = round(180 - $point['orders'] / $ordersMax * 160, 2);
            $point['amount'] = $this->money($point['revenue']);
            $point['show_label'] = $index % max(1, (int) ceil(count($points) / 6)) === 0 || $index === count($points) - 1;
            $revenuePath[] = ($index === 0 ? 'M' : 'L') . $point['x'] . ' ' . $point['revenue_y'];
            $ordersPath[] = ($index === 0 ? 'M' : 'L') . $point['x'] . ' ' . $point['orders_y'];
        }
        unset($point);
        $axis = [];
        for ($step = 0; $step <= 4; $step++) {
            $axis[] = ['y' => 180 - $step * 40, 'revenue' => number_format($revenueMax * $step / 4, 0), 'orders' => (int) ($ordersMax * $step / 4)];
        }

        return ['points' => $points, 'axis' => $axis, 'revenue_path' => implode(' ', $revenuePath), 'orders_path' => implode(' ', $ordersPath), 'empty' => array_sum($revenue) == 0 && array_sum($orders) == 0, 'monthly' => $monthly];
    }
}
