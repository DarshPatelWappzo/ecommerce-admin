<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformPaymentHistoryRepository
{
    /** One-to-one joins keep each ledger payment a single row, including deleted identities. */
    private function base(): Builder
    {
        return DB::connection(config('subscriptions.connection'))->table('package_payments as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.owner_id')
            ->leftJoin('tenant_databases as t', 't.id', '=', 'p.tenant_database_id')
            ->leftJoin('user_domains as d', 'd.id', '=', 't.domain_id')
            ->leftJoin('packages as k', 'k.id', '=', 'p.package_id')
            ->leftJoin('user_packages as s', 's.id', '=', 'p.subscription_id');
    }

    /** @return list<string> */
    private function fields(): array
    {
        return [
            'p.id',
            'p.reference',
            'p.owner_id',
            'p.tenant_database_id',
            'p.package_id',
            'p.quote',
            'p.billing_cycle',
            'p.amount',
            'p.currency',
            'p.gateway',
            'p.mode',
            'p.status',
            'p.failure_code',
            'p.gateway_order_id',
            'p.gateway_payment_id',
            'p.created_at',
            'p.paid_at',
            'p.checkout_state',
            'p.checkout_expires_at',
            'p.subscription_id',
            'u.name as owner_name',
            'u.first_name',
            'u.last_name',
            'u.email',
            'u.deleted_at as owner_deleted_at',
            't.id as existing_tenant_id',
            'd.domain_name',
            'd.deleted_at as domain_deleted_at',
            'k.name as current_package_name',
            's.start_date',
            's.end_date',
            's.grace_expires_at',
            's.status as subscription_status',
            's.deleted_at as subscription_deleted_at'
        ];
    }

    private function fulfillmentSql(): string
    {
        return "CASE WHEN p.status <> 'captured' THEN 'unpaid'
            WHEN p.failure_code = 'resolution_required' OR s.id IS NULL OR s.deleted_at IS NOT NULL THEN 'resolution_required'
            WHEN s.start_date > ? THEN 'scheduled' ELSE 'activated' END";
    }

    /** @param array<string, mixed> $filters */
    public function filtered(array $filters, bool $earnings = false): Builder
    {
        $query = $this->base()->where('p.mode', $filters['mode'] ?? 'live');
        foreach (
            [
                'user_id' => 'p.owner_id',
                'tenant_id' => 'p.tenant_database_id',
                'package_id' => 'p.package_id',
                'gateway' => 'p.gateway',
                'currency' => 'p.currency',
                'billing_cycle' => 'p.billing_cycle'
            ] as $key => $column
        ) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }
        if (! empty($filters['domain'])) {
            $query->where('d.domain_name', 'like', '%' . $filters['domain'] . '%');
        }
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $query) use ($term): void {
                foreach (
                    [
                        'p.reference',
                        'u.name',
                        'u.first_name',
                        'u.last_name',
                        'u.email',
                        'd.domain_name',
                        'p.gateway_order_id',
                        'p.gateway_payment_id'
                    ] as $column
                ) {
                    $query->orWhere($column, 'like', $term);
                }
            });
        }
        if (! empty($filters['fulfillment'])) {
            $query->whereRaw('(' . $this->fulfillmentSql() . ') = ?', [now(), $filters['fulfillment']]);
        }
        if ($earnings) {
            $query->where('p.status', 'captured');
        } elseif (! empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }
        foreach (['from' => '>=', 'to' => '<'] as $key => $operator) {
            if (empty($filters[$key])) {
                continue;
            }
            $date = Carbon::parse($filters[$key])->startOfDay();
            if ($key === 'to') {
                $date->addDay();
            }
            if ($earnings) {
                $query->where('p.paid_at', $operator, $date);
            } else {
                $query->where(function (Builder $query) use ($operator, $date): void {
                    $query->where(fn(Builder $q) => $q->where('p.status', 'captured')->where('p.paid_at', $operator, $date))
                        ->orWhere(fn(Builder $q) => $q->where('p.status', '!=', 'captured')->where('p.created_at', $operator, $date));
                });
            }
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    public function rows(array $filters): Builder
    {
        $sort = in_array($filters['sort'] ?? '', ['id', 'amount', 'paid_at', 'created_at'], true) ? $filters['sort'] : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->filtered($filters)->select($this->fields())
            ->selectRaw($this->fulfillmentSql() . ' as fulfillment', [now()])
            ->orderBy('p.' . $sort, $direction)->orderBy('p.id', $direction);
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, object>
     */
    public function earnings(array $filters): Collection
    {
        return $this->filtered($filters, true)->select('p.currency')
            ->selectRaw('SUM(p.amount) as amount_minor, COUNT(*) as payment_count')
            ->groupBy('p.currency')->orderBy('p.currency')->get();
    }

    public function find(int $id): object
    {
        return $this->base()->where('p.id', $id)->select($this->fields())
            ->selectRaw($this->fulfillmentSql() . ' as fulfillment', [now()])->firstOrFail();
    }

    /** Owner-associated domains are not assumed to be aliases of the paid tenant. */
    public function domains(int $ownerId): Collection
    {
        return DB::connection(config('subscriptions.connection'))->table('user_domains')
            ->where('user_id', $ownerId)->orderBy('id')->get(['domain_name', 'deleted_at']);
    }
}
