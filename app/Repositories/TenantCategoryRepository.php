<?php

namespace App\Repositories;

use App\Models\Tenant\Category;
use App\Services\TenantAuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class TenantCategoryRepository
{
    public function __construct(private readonly TenantAuditLogService $audit) {}

    public function find(int $id): stdClass
    {
        $category = DB::connection('tenant')->table('categories')->whereNull('deleted_at')->find($id);
        abort_if($category === null, 404);

        return $category;
    }

    /** @return Collection<int, stdClass> */
    public function parentOptions(?int $categoryId = null): Collection
    {
        return DB::connection('tenant')->table('categories')->whereNull('deleted_at')
            ->whereNotIn('id', $this->excludedParentIds($categoryId))->orderBy('name')->get(['id', 'name']);
    }

    /** @return array<int, int> */
    public function excludedParentIds(?int $categoryId): array
    {
        if ($categoryId === null) {
            return [];
        }

        $parents = DB::connection('tenant')->table('categories')->pluck('parent_id', 'id');
        $excluded = [$categoryId];
        do {
            $count = count($excluded);
            foreach ($parents as $id => $parentId) {
                if ($parentId !== null && in_array((int) $parentId, $excluded, true) && ! in_array((int) $id, $excluded, true)) {
                    $excluded[] = (int) $id;
                }
            }
        } while (count($excluded) > $count);

        return $excluded;
    }

    /** @param array{name: string, parent_id?: int|string|null, status: bool|int|string} $data */
    public function save(array $data, ?int $id = null): int
    {
        return DB::connection('tenant')->transaction(function () use ($data, $id): int {
            $before = $id === null ? null : Category::query()->lockForUpdate()->findOrFail($id)->only(['name', 'slug', 'parent_id', 'status']);
            $savedId = $this->persist($data, $id);
            $category = Category::findOrFail($savedId);
            $this->audit->recordSnapshot($category, $id === null ? 'created' : 'updated', $before, $category->only(['name', 'slug', 'parent_id', 'status']));

            return $savedId;
        });
    }

    /** @param array{name: string, parent_id?: int|string|null, status: bool|int|string} $data */
    private function persist(array $data, ?int $id = null): int
    {
        $base = substr(Str::slug($data['name']), 0, 240) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (DB::connection('tenant')->table('categories')->where('slug', $slug)
            ->when($id !== null, fn($query) => $query->where('id', '!=', $id))->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }
        $attributes = [];
        $attributes['name'] = $data['name'];
        $attributes['slug'] = $slug;
        $attributes['parent_id'] = $data['parent_id'] ?? null;
        $attributes['status'] = (bool) $data['status'];
        $attributes['updated_at'] = now();
        if ($id === null) {
            $attributes['created_at'] = now();
            $id = DB::connection('tenant')->table('categories')->insertGetId($attributes);
        } else {
            DB::connection('tenant')->table('categories')->where('id', $id)->whereNull('deleted_at')->update($attributes);
        }

        return $id;
    }

    /** @param array{search?: ?string, status?: bool|int|string|null, parent_id?: int|string|null, from?: ?string, to?: ?string} $filters */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return DB::connection('tenant')->table('categories')
            ->leftJoin('categories as parent', function (JoinClause $join): void {
                $join->on('categories.parent_id', '=', 'parent.id')->whereNull('parent.deleted_at');
            })
            ->select(['categories.id', 'categories.name', 'categories.slug', 'categories.parent_id', 'categories.status', 'parent.name as parent_name'])
            ->whereNull('categories.deleted_at')
            ->when(filled($filters['search'] ?? null), fn(Builder $query): Builder => $query->where('categories.name', 'like', '%' . $filters['search'] . '%'))
            ->when(isset($filters['status']), fn(Builder $query): Builder => $query->where('categories.status', $filters['status']))
            ->when($filters['parent_id'] ?? null, fn(Builder $query, int|string $parentId): Builder => $query->where('categories.parent_id', $parentId))
            ->when($filters['from'] ?? null, fn(Builder $query, string $from): Builder => $query->where('categories.created_at', '>=', $from . ' 00:00:00'))
            ->when($filters['to'] ?? null, fn(Builder $query, string $to): Builder => $query->where('categories.created_at', '<', Carbon::parse($to)->addDay()->startOfDay()))
            ->orderByDesc('categories.id')
            ->paginate(10)->withQueryString();
    }
}
