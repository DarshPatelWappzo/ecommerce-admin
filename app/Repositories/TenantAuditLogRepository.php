<?php

namespace App\Repositories;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TenantAuditLogRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::query()->select(['id', 'user_id', 'module', 'action', 'auditable_type', 'auditable_id', 'description', 'created_at'])
            ->with('user:id,first_name,last_name');
        foreach (['user_id', 'module', 'action'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }
        if (isset($filters['search']) && $filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('description', 'like', $term)->orWhere('module', 'like', $term)
                    ->orWhere('action', 'like', $term)->orWhere('auditable_id', 'like', $term);
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function findById(int $id): AuditLog
    {
        return AuditLog::with('user:id,first_name,last_name')->findOrFail($id);
    }

    /** @return array<string, mixed> */
    public function choices(): array
    {
        return [
            'users' => User::withTrashed()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ];
    }
}
