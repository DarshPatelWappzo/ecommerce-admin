<?php

namespace App\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TenantDashboardRepository
{
    private function table(string $table): Builder
    {
        return DB::connection('tenant')->table($table);
    }

    private function during(Builder $query, string $column, CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $query->where($column, '>=', $from)->where($column, '<', $until);
    }

    private function orders(CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $this->during($this->table('orders')->where('currency', 'INR')->where('status', '!=', 'draft'), 'order_date', $from, $until);
    }

    private function captures(CarbonImmutable $from, CarbonImmutable $until): Builder
    {
        return $this->during($this->table('order_payments')->where('order_payments.currency', 'INR')->where('order_payments.status', 'captured'), 'order_payments.paid_at', $from, $until);
    }

    /** @param array<string, bool> $access
     * @return array<string, int|float>
     */
    public function totals(CarbonImmutable $from, CarbonImmutable $until, array $access): array
    {
        $totals = [];
        if ($access['payments']) {
            $totals['revenue'] = (float) $this->captures($from, $until)->sum('amount');
        }
        if ($access['orders']) {
            $totals['orders'] = $this->orders($from, $until)->count();
            $totals['pending'] = $this->orders($from, $until)->where('status', 'pending')->count();
        }
        if ($access['customers']) {
            $totals['customers'] = $this->during($this->table('customers')->whereNull('deleted_at'), 'created_at', $from, $until)->count();
        }
        if ($access['returns']) {
            $totals['returns'] = $this->during($this->table('return_requests')->whereNotIn('status', ['rejected', 'cancelled']), 'requested_at', $from, $until)->count();
            $totals['refunds'] = (float) $this->during($this->table('refunds')->where('status', 'processed')->where('currency', 'INR'), 'processed_at', $from, $until)->sum('amount');
        }
        if ($access['replacements']) {
            $totals['replacements'] = $this->during($this->table('replacement_requests')->whereNotIn('status', ['rejected', 'cancelled', 'converted_to_refund']), 'requested_at', $from, $until)->count();
        }

        return $totals;
    }

    /** @return array{products: int, low_stock: int} */
    public function catalog(): array
    {
        $products = $this->table('products')->whereNull('products.deleted_at');

        return [
            'products' => (clone $products)->count(),
            'low_stock' => (clone $products)->where('products.status', true)->whereExists(function (Builder $variants): void {
                $variants->selectRaw('1')->from('product_variants')->whereColumn('product_variants.product_id', 'products.id')
                    ->whereNull('product_variants.deleted_at')->where('product_variants.status', true)
                    ->whereRaw('product_variants.quantity - product_variants.reserved_quantity <= product_variants.reorder_level');
            })->count(),
        ];
    }

    /** @return array<string, int> */
    public function orderStatuses(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->orders($from, $until)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn($value): int => (int) $value)->all();
    }

    /** Payment attempt counts use creation date; retries remain separate attempts.
     * @return array<string, int>
     */
    public function paymentStatuses(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->during($this->table('order_payments')->where('currency', 'INR'), 'created_at', $from, $until)
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->map(fn($value): int => (int) $value)->all();
    }

    /** @return array<string, float> */
    public function revenueTimeline(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->captures($from, $until)->selectRaw('DATE(paid_at) AS day, SUM(amount) AS total')
            ->groupByRaw('DATE(paid_at)')->orderBy('day')->pluck('total', 'day')->map(fn($value): float => (float) $value)->all();
    }

    /** @return array<string, int> */
    public function orderTimeline(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->orders($from, $until)->selectRaw('DATE(order_date) AS day, COUNT(*) AS total')
            ->groupByRaw('DATE(order_date)')->orderBy('day')->pluck('total', 'day')->map(fn($value): int => (int) $value)->all();
    }

    /** @return list<object> */
    public function topProducts(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->during($this->table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.currency', 'INR')->whereIn('orders.status', ['confirmed', 'processing', 'shipped', 'delivered']), 'orders.order_date', $from, $until)
            ->selectRaw('order_items.product_id, MAX(order_items.product_name) AS name, SUM(order_items.quantity) AS sold, SUM(order_items.total_amount) AS revenue')
            ->groupBy('order_items.product_id')->orderByDesc('sold')->orderBy('order_items.product_id')->limit(5)->get()->all();
    }

    /** @return list<object> */
    public function recentOrders(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->orders($from, $until)->select(['id', 'order_number', 'customer_name', 'grand_total', 'payment_status', 'status', 'order_date'])
            ->orderByDesc('order_date')->orderByDesc('id')->limit(5)->get()->all();
    }

    /** @return list<object> */
    public function recentPayments(CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->table('order_payments')->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('order_payments.currency', 'INR')
            ->whereRaw('COALESCE(order_payments.paid_at, order_payments.created_at) >= ?', [$from])
            ->whereRaw('COALESCE(order_payments.paid_at, order_payments.created_at) < ?', [$until])
            ->select(['order_payments.id', 'orders.customer_name', 'order_payments.amount', 'order_payments.status'])
            ->selectRaw('COALESCE(order_payments.paid_at, order_payments.created_at) AS date')
            ->orderByDesc('date')->orderByDesc('order_payments.id')->limit(5)->get()->all();
    }

    /** @return list<object> */
    public function newCustomers(CarbonImmutable $from, CarbonImmutable $until, bool $canReadOrders, bool $canReadPayments): array
    {
        $query = $this->during($this->table('customers')->whereNull('customers.deleted_at'), 'customers.created_at', $from, $until)
            ->select(['customers.id', 'customers.first_name', 'customers.last_name']);
        if ($canReadOrders) {
            $query->selectSub($this->orders($from, $until)->whereColumn('orders.customer_id', 'customers.id')->selectRaw('COUNT(*)'), 'orders');
        }
        if ($canReadPayments) {
            $query->selectSub($this->captures($from, $until)->join('orders', 'orders.id', '=', 'order_payments.order_id')
                ->whereColumn('orders.customer_id', 'customers.id')->selectRaw('COALESCE(SUM(order_payments.amount), 0)'), 'spent');
        }

        return $query->orderByDesc('customers.created_at')->orderByDesc('customers.id')->limit(5)->get()->all();
    }

    /** Order cohort rates count each eligible order once, even with multiple item requests.
     * @return array{eligible: int, returns: int, replacements: int}
     */
    public function rates(CarbonImmutable $from, CarbonImmutable $until, bool $returns, bool $replacements): array
    {
        $orders = $this->orders($from, $until)->where('status', '!=', 'cancelled');
        $counts = ['eligible' => (clone $orders)->count(), 'returns' => 0, 'replacements' => 0];
        foreach (['returns' => $returns, 'replacements' => $replacements] as $type => $allowed) {
            if ($allowed) {
                $table = $type === 'returns' ? 'return_requests' : 'replacement_requests';
                $excluded = $type === 'returns' ? ['rejected', 'cancelled'] : ['rejected', 'cancelled', 'converted_to_refund'];
                $counts[$type] = (clone $orders)->whereExists(function (Builder $query) use ($table, $excluded): void {
                    $query->selectRaw('1')->from($table)->whereColumn($table . '.order_id', 'orders.id')->whereNotIn($table . '.status', $excluded);
                })->count();
            }
        }

        return $counts;
    }
}
