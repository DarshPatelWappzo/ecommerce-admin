<?php

namespace App\Repositories;

use App\Models\Tenant\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TenantUserRepository
{
    public function findById(string $id): ?User
    {
        return User::query()->with('roles:id,name')->find($id);
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    /**
     * Retrieve a paginated tenant-user list filtered by an optional search term.
     */
    public function paginate(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->with('roles:id,name')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create an unsaved tenant user instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function make(array $attributes = []): User
    {
        return new User($attributes);
    }

    /**
     * Persist a tenant user.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $roleIds
     */
    public function create(array $attributes, array $roleIds): User
    {
        return DB::connection('tenant')->transaction(function () use ($attributes, $roleIds): User {
            $user = User::create($attributes);
            $user->roles()->sync($roleIds);

            return $user;
        });
    }

    /**
     * Persist tenant user changes.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $roleIds
     */
    public function update(User $user, array $attributes, array $roleIds): User
    {
        return DB::connection('tenant')->transaction(function () use ($user, $attributes, $roleIds): User {
            $user->update($attributes);
            $user->roles()->sync($roleIds);

            return $user->refresh();
        });
    }

    /**
     * Retrieve the IDs of roles assigned to a tenant user.
     *
     * @return array<int, int>
     */
    public function roleIds(User $user): array
    {
        return $user->roles
            ->pluck('id')
            ->map(fn (mixed $roleId): int => (int) $roleId)
            ->all();
    }
}
