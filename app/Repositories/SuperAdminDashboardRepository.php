<?php

namespace App\Repositories;

use App\Models\UserPackage;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class SuperAdminDashboardRepository
{
    public function __construct(
        private readonly PlatformPaymentHistoryRepository $payments,
        private readonly TenantSubscriptionRepository $subscriptions,
    ) {}

    private function table(string $table): Builder
    {
        return DB::connection(config('subscriptions.connection'))->table($table);
    }

    private function owners(): Builder
    {
        return $this->table('users')->where('is_super_admin', false)->whereNull('deleted_at');
    }

    /** @return array{total: int, current: int, previous: int} */
    public function registrations(Carbon $start, Carbon $previousStart, Carbon $previousEnd): array
    {
        $row = $this->owners()->where('created_at', '<=', now())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN created_at >= ? THEN 1 END) AS current', [$start])
            ->selectRaw('COUNT(CASE WHEN created_at >= ? AND created_at < ? THEN 1 END) AS previous', [$previousStart, $previousEnd])
            ->first();

        return ['total' => (int) $row->total, 'current' => (int) $row->current, 'previous' => (int) $row->previous];
    }

    /** @return Collection<string, int> */
    public function registrationTimeline(Carbon $start, bool $monthly): Collection
    {
        $expression = $monthly ? 'SUBSTR(created_at, 1, 7)' : 'DATE(created_at)';

        return $this->owners()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', now())
            ->selectRaw($expression . ' AS period, COUNT(*) AS total')
            ->groupByRaw($expression)
            ->pluck('total', 'period');
    }

    /** One current entitlement per central business account, regardless of renewal history or domain count. */
    private function currentIds(): Builder
    {
        $candidate = $this->subscriptions->currentCandidates('test') // instead of 'test' write 'live' on production
            ->select('user_packages.id')
            ->whereColumn('user_packages.user_id', 'users.id')
            ->where(function (EloquentBuilder $query): void {
                $query->whereNull('tenant_database_id')
                    ->orWhereIn(
                        'tenant_database_id',
                        $this->table('tenant_databases')
                            ->select('id')
                            ->whereColumn('tenant_databases.user_id', 'users.id')
                    );
            })->limit(1);

        return $this->owners()->where('created_at', '<=', now())->selectSub($candidate, 'subscription_id');
    }

    /** Stream only selected entitlement dates/status, reusing the lifecycle service without N+1 queries.
     * @return LazyCollection<int, UserPackage>
     */
    public function currentSubscriptions(): LazyCollection
    {
        return UserPackage::query()->whereIn('id', $this->currentIds())
            ->select(['id', 'status', 'start_date', 'end_date', 'grace_expires_at'])->cursor();
    }

    /** @return Collection<int, object> */
    public function upcomingRenewals(): Collection
    {
        return $this->table('user_packages as s')
            ->whereIn('s.id', $this->currentIds())
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('packages as k', 'k.id', '=', 's.package_id')
            ->whereIn('s.status', ['active', 'scheduled', 'grace_period'])
            ->where('s.end_date', '>', now())
            ->where('s.end_date', '<=', now()
            ->addDays(7))
            ->orderBy('s.end_date')
            ->orderBy('s.id')
            ->limit(5)
            ->get(['u.name', 'u.first_name', 'u.last_name', 's.price_snapshot', 'k.name as package_name', 's.end_date']);
    }

    /** The existing ledger scope owns capture and live-mode accounting rules. */
    private function collected(?Carbon $start = null, ?string $currency = null): Builder
    {
        return $this->payments->filtered(['mode' => 'test'], true) // instead of 'test' write 'live' on production
            ->where('p.paid_at', '<=', now())
            ->when($start, fn(Builder $query) => $query->where('p.paid_at', '>=', $start))
            ->when($currency, fn(Builder $query) => $query->where('p.currency', $currency));
    }

    /** @return Collection<int, object> */
    public function revenueTotals(Carbon $start, Carbon $previousStart, Carbon $previousEnd): Collection
    {
        return $this->collected()->select('p.currency')->selectRaw('SUM(p.amount) AS total')
            ->selectRaw('SUM(CASE WHEN p.paid_at >= ? THEN p.amount ELSE 0 END) AS current', [$start])
            ->selectRaw('SUM(CASE WHEN p.paid_at >= ? AND p.paid_at < ? THEN p.amount ELSE 0 END) AS previous', [$previousStart, $previousEnd])
            ->groupBy('p.currency')->orderBy('p.currency')->get();
    }

    /** @return Collection<string, string> */
    public function monthlyRevenue(Carbon $start, string $currency): Collection
    {
        return $this->collected($start, $currency)->selectRaw('SUBSTR(p.paid_at, 1, 7) AS period, SUM(p.amount) AS amount')
            ->groupByRaw('SUBSTR(p.paid_at, 1, 7)')->pluck('amount', 'period');
    }

    /** @return Collection<int, object> */
    public function packageRevenue(?Carbon $start, ?Carbon $end, string $currency): Collection
    {
        $totals = $this->collected($start, $currency)
            ->when($end, fn(Builder $query) => $query->where('p.paid_at', '<', $end))
            ->select('p.package_id')->selectRaw('SUM(p.amount) AS amount')->groupBy('p.package_id');

        $catalog = $this->table('packages as k')->leftJoinSub($totals, 'totals', 'totals.package_id', '=', 'k.id')
            ->where(fn(Builder $query) => $query->whereNull('k.deleted_at')->orWhereNotNull('totals.package_id'))
            ->select(['k.id', 'k.name'])->selectRaw('COALESCE(totals.amount, 0) AS amount');
        $unavailable = $this->table('packages')->select('id');
        $orphaned = (clone $totals)->whereNotIn('p.package_id', $unavailable)
            ->select('p.package_id as id')->selectRaw("'Unavailable package' as name, SUM(p.amount) as amount");

        return DB::connection(config('subscriptions.connection'))->query()->fromSub($catalog->unionAll($orphaned), 'ranked')
            ->orderByDesc('amount')->orderBy('id')->limit(4)->get();
    }

    /** @return Collection<int, object> */
    public function recentPayments(): Collection
    {
        return $this->payments->rows(['mode' => 'test'])->reorder() // instead of 'test' write 'live' on production
            ->orderByRaw("CASE WHEN p.status = 'captured' THEN p.paid_at ELSE p.created_at END DESC")
            ->orderByDesc('p.id')->limit(5)->get();
    }
}
