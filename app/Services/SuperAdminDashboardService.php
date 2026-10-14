<?php

namespace App\Services;

use App\Repositories\SuperAdminDashboardRepository;
use Illuminate\Support\Carbon;

class SuperAdminDashboardService
{
    public function __construct(
        private readonly SuperAdminDashboardRepository $reports,
        private readonly TenantSubscriptionService $subscriptions,
        private readonly PlatformPaymentHistoryService $payments,
    ) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $month = now()->startOfMonth();
        $previous = $month->copy()->subMonth();
        $previousEnd = $previous->copy()->addSeconds((int) $month->diffInSeconds(now()) + 1)->min($month);
        $registrations = $this->reports->registrations($month, $previous, $previousEnd);
        $totals = $this->reports->revenueTotals($month, $previous, $previousEnd);
        $currencies = $totals->pluck('currency')->push(config('subscriptions.currency'))->unique()->sort()->values()->all();
        $revenue = [];
        foreach ($currencies as $currency) {
            $row = $totals->firstWhere('currency', $currency);
            $revenue[] = [
                'currency' => $currency,
                'amount_minor' => (string) ($row->total ?? 0),
                'formatted' => $this->money((string) ($row->total ?? 0), $currency),
                'current_minor' => (string) ($row->current ?? 0),
                'previous_minor' => (string) ($row->previous ?? 0),
                'growth' => $this->growth((int) ($row->current ?? 0), (int) ($row->previous ?? 0)),
            ];
        }

        $counts = ['Active' => 0, 'Grace Period' => 0, 'Expired' => 0, 'Cancelled' => 0];
        $expiring = 0;
        foreach ($this->reports->currentSubscriptions() as $subscription) {
            $status = $this->subscriptions->status($subscription);
            $counts[$status]++;
            if ($status === 'Active' && $subscription->end_date !== null && $subscription->end_date->lte(now()->addDays(7))) {
                $expiring++;
            }
        }
        $total = array_sum($counts);
        $legend = [];
        $stops = [];
        $offset = 0;
        foreach (['Active' => ['active', '#00b985'], 'Grace Period' => ['grace', '#ffb63f'], 'Expired' => ['expired', '#ff615c'], 'Cancelled' => ['cancelled', '#a8b7cf']] as $label => [$key, $color]) {
            $percent = $total > 0 ? $counts[$label] / $total * 100 : 0;
            $end = $offset + $percent;
            $stops[] = "$color $offset% $end%";
            $legend[] = ['label' => $label, 'count' => $counts[$label], 'percentage' => round($percent, 1), 'key' => $key];
            $offset = $end;
        }

        return [
            'registrations' => $registrations,
            'registrationGrowth' => $this->growth($registrations['current'], $registrations['previous']),
            'revenues' => $revenue,
            'currencies' => $currencies,
            'currency' => config('subscriptions.currency'),
            'active' => $counts['Active'],
            'expiring' => $expiring,
            'subscriptionTotal' => $total,
            'legend' => $legend,
            'donut' => $total > 0 ? 'conic-gradient(' . implode(', ', $stops) . ')' : '#e8eff9',
            'subscriptionDescription' => $total . ' business accounts with started subscriptions: ' . implode('; ', array_map(fn(array $item): string => $item['count'] . ' ' . $item['label'], $legend)),
            'revenueChart' => $this->chart('revenue', '6', config('subscriptions.currency')),
            'packageChart' => $this->chart('packages', 'this_month', config('subscriptions.currency')),
            'usersChart' => $this->chart('users', 'this_year', config('subscriptions.currency')),
            'recentPayments' => $this->reports->recentPayments()->map(function (object $record): array {
                $payment = $this->payments->present($record);

                return [
                    'name' => $payment['owner_name'],
                    'initials' => $this->initials($payment['owner_name']),
                    'package' => $payment['package'],
                    'amount' => $this->money($payment['amount_minor'], $payment['currency']),
                    'status' => ucfirst($payment['status']),
                    'tone' => match ($payment['status']) {
                        'captured' => 'success',
                        'failed', 'rejected' => 'danger',
                        default => 'warning'
                    },
                    'date' => $payment['date'] ? Carbon::parse($payment['date'])->format('d M Y') : 'Not recorded',
                ];
            }),
            'renewals' => $this->reports->upcomingRenewals()->map(function (object $record): array {
                $name = $record->name ?: (trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: 'Unavailable user');
                $snapshot = json_decode($record->price_snapshot ?? '{}', true);
                $package = is_string($snapshot['name'] ?? null) ? $snapshot['name'] : ($record->package_name ?? 'Unavailable package');
                $expiry = Carbon::parse($record->end_date);
                $days = (int) ceil(now()->diffInDays($expiry, false));

                return [
                    'name' => $name,
                    'initials' => $this->initials($name),
                    'package' => $package,
                    'expiry' => $expiry->format('d M Y'),
                    'days' => $days . ' ' . ($days === 1 ? 'day' : 'days'),
                    'urgency' => $days <= 2 ? 'danger' : 'warning',
                ];
            }),
        ];
    }

    /** @return array<string, mixed> */
    public function chart(string $section, string $period, string $currency): array
    {
        return match ($section) {
            'packages' => $this->packages($period, $currency),
            'users' => $this->users($period),
            'revenue' => $this->revenue((int) $period, $currency),
        };
    }

    /** @return array<string, mixed> */
    private function packages(string $period, string $currency): array
    {
        $month = now()->startOfMonth();
        $start = match ($period) {
            'this_month' => $month,
            'last_month' => $month->copy()->subMonth(),
            'last_3_months' => $month->copy()->subMonths(2),
            'last_6_months' => $month->copy()->subMonths(5),
            'this_year' => now()->startOfYear(),
            'all_time' => null,
        };
        $rows = $this->reports->packageRevenue($start, $period === 'last_month' ? $month : null, $currency);
        $maximum = (int) ($rows->max('amount') ?? 0);
        $packages = $rows->map(fn(object $row): array => [
            'id' => $row->id,
            'name' => $row->name,
            'amount_minor' => (string) $row->amount,
            'amount' => $this->money((string) $row->amount, $currency),
            'width' => $maximum > 0 ? round((int) $row->amount / $maximum * 100, 2) : 0,
        ])->all();

        return ['packages' => $packages, 'currency' => $currency, 'period' => $period];
    }

    /** @return array<string, mixed> */
    private function revenue(int $months, string $currency): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $totals = $this->reports->monthlyRevenue($start, $currency);
        $maximum = max(4, (int) ($totals->max() ?? 0));
        $step = intdiv($maximum, 4) + ($maximum % 4 > 0 ? 1 : 0);
        $ceiling = $step * 4;
        $points = [];
        for ($index = 0; $index < $months; $index++) {
            $date = $start->copy()->addMonths($index);
            $amount = (string) $totals->get($date->format('Y-m'), 0);
            $points[] = [
                'label' => $date->format('M Y'),
                'amount_minor' => $amount,
                'amount' => $this->money($amount, $currency),
                'height' => (int) $amount / $ceiling * 100,
            ];
        }
        $axis = [];
        foreach ([4, 3, 2, 1, 0] as $part) {
            $axis[] = $this->money((string) ($step * $part), $currency);
        }

        return [
            'points' => $points,
            'axis' => $axis,
            'currency' => $currency,
            'description' => implode('; ', array_map(fn(array $point): string => $point['label'] . ': ' . $point['amount'], $points)),
        ];
    }

    /** @return array<string, mixed> */
    private function users(string $period): array
    {
        $monthly = $period === 'this_year';
        $start = $monthly ? now()->startOfYear() : now()->startOfDay()->subDays((int) $period - 1);
        $totals = $this->reports->registrationTimeline($start, $monthly);
        $count = $monthly ? now()->month : (int) $period;
        $labels = [];
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $date = $monthly ? $start->copy()->addMonths($index) : $start->copy()->addDays($index);
            $labels[] = $date->format($monthly ? 'M Y' : 'j M');
            $values[] = (int) $totals->get($date->format($monthly ? 'Y-m' : 'Y-m-d'), 0);
        }
        $maximum = max(4, (int) (ceil(max($values) / 4) * 4));
        $points = [];
        $labelEvery = max(1, (int) ceil(($count - 1) / 6));
        foreach ($values as $index => $value) {
            $points[] = [
                'x' => round(40 + ($count > 1 ? $index * 420 / ($count - 1) : 210), 2),
                'y' => round(135 - $value / $maximum * 120, 2),
                'value' => $value,
                'label' => $labels[$index],
                'axis_label' => $monthly ? substr($labels[$index], 0, 3) : $labels[$index],
                'show_label' => $monthly || $index === $count - 1
                    || ($index % $labelEvery === 0 && $count - 1 - $index >= $labelEvery),
            ];
        }
        $path = 'M' . $points[0]['x'] . ' ' . $points[0]['y'];
        for ($index = 1; $index < $count; $index++) {
            $previous = $points[$index - 1];
            $current = $points[$index];
            $middle = ($previous['x'] + $current['x']) / 2;
            $path .= ' C' . $middle . ' ' . $previous['y'] . ' ' . $middle . ' ' . $current['y'] . ' ' . $current['x'] . ' ' . $current['y'];
        }
        $last = $points[$count - 1];

        return [
            'labels' => $labels,
            'year' => $monthly ? $start->format('Y') : null,
            'data' => $values,
            'points' => $points,
            'path' => $path,
            'area' => $path . ' L' . $last['x'] . ' 135 H' . $points[0]['x'] . ' Z',
            'axis' => [$maximum, $maximum * 3 / 4, $maximum / 2, $maximum / 4, 0],
            'title' => 'New business registrations from ' . $start->format('j M Y') . ' to ' . now()->format('j M Y'),
            'description' => implode('; ', array_map(fn(array $point): string => $point['label'] . ': ' . $point['value'] . ' users', $points)),
        ];
    }

    /** Integer/string formatting mirrors payment history without converting money to floating point. */
    private function money(string $minor, string $currency): string
    {
        if ($currency !== 'INR') {
            return $currency . ' ' . $minor . ' minor units';
        }
        $digits = str_pad($minor, 3, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -2);
        $lastThree = substr($whole, -3);
        $prefix = strlen($whole) > 3 ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3)) . ',' : '';

        return '₹' . $prefix . $lastThree . '.' . substr($digits, -2);
    }

    private function growth(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current === 0 ? '0%' : 'No prior baseline';
        }
        $percentage = round(($current - $previous) / $previous * 100, 1);

        return ($percentage > 0 ? '+' : '') . $percentage . '%';
    }

    private function initials(string $name): string
    {
        return mb_strtoupper(mb_substr($name, 0, 2));
    }
}
