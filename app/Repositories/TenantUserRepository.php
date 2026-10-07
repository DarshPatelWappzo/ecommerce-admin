<?php

namespace App\Repositories;

use App\Models\Tenant\User;
use App\Services\TenantAuditAction;
use App\Services\TenantAuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TenantUserRepository
{
    public function __construct(private readonly TenantAuditLogService $audit) {}

    public function findById(string $id): ?User
    {
        return User::query()->with('roles:id,name')->find($id);
    }

    public function delete(User $user): bool
    {
        return DB::connection('tenant')->transaction(function () use ($user): bool {
            $deleted = (bool) $user->delete();
            $this->audit->recordSnapshot($user, TenantAuditAction::DELETED, $user->only(['first_name', 'last_name', 'email', 'mobile_number', 'status']), null);

            return $deleted;
        });
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
            $this->audit->recordSnapshot($user, TenantAuditAction::CREATED, null, $user->only(['first_name', 'last_name', 'email', 'mobile_number', 'status']));
            $this->syncRoles($user, $roleIds);

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
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $user->only(['first_name', 'last_name', 'email', 'mobile_number', 'status']);
            $user->update($attributes);
            $this->audit->recordSnapshot($user, TenantAuditAction::UPDATED, $before, $user->only(['first_name', 'last_name', 'email', 'mobile_number', 'status']));
            $this->syncRoles($user, $roleIds);

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

    /** @param array<int, int|string> $roleIds */
    private function syncRoles(User $user, array $roleIds): void
    {
        $before = $user->roles()->pluck('name', 'roles.id')->all();
        $changes = $user->roles()->sync($roleIds);
        if ($changes['attached'] !== []) {
            $this->audit->log('users', TenantAuditAction::ROLE_ASSIGNED, $user, newValues: ['roles' => $user->roles()->whereIn('roles.id', $changes['attached'])->pluck('name', 'roles.id')->all()]);
        }
        if ($changes['detached'] !== []) {
            $this->audit->log('users', TenantAuditAction::ROLE_REMOVED, $user, oldValues: ['roles' => array_intersect_key($before, array_flip($changes['detached']))]);
        }
    }
}
