<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class TenantCategoryRepository
{
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
        $base = substr(Str::slug($data['name']), 0, 240) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (DB::connection('tenant')->table('categories')->where('slug', $slug)
            ->when($id !== null, fn ($query) => $query->where('id', '!=', $id))->exists()
        ) {
            $slug = $base.'-'.$suffix++;
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

    public function paginate(): LengthAwarePaginator
    {
        return DB::connection('tenant')->table('categories')
            ->leftJoin('categories as parent', function (JoinClause $join): void {
                $join->on('categories.parent_id', '=', 'parent.id')->whereNull('parent.deleted_at');
            })
            ->select(['categories.id', 'categories.name', 'categories.slug', 'categories.parent_id', 'categories.status', 'parent.name as parent_name'])
            ->whereNull('categories.deleted_at')
            ->orderByDesc('categories.id')
            ->paginate(10);
    }
}
