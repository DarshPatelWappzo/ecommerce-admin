<?php

namespace App\Repositories;

use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Services\TenantAuditAction;
use App\Services\TenantAuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantRoleRepository
{
    public function __construct(private readonly TenantAuditLogService $audit) {}

    /**
     * Retrieve active roles available for user assignment.
     *
     * @return Collection<int, Role>
     */
    public function allActive(): Collection
    {
        return Role::query()
            ->where('status', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Retrieve all tenant roles with their assignment counts.
     *
     * @return Collection<int, Role>
     */
    public function allWithAssignmentCounts(): Collection
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->latest()
            ->get();
    }

    /**
     * Create an unsaved tenant role instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function make(array $attributes = []): Role
    {
        return new Role($attributes);
    }

    /**
     * Persist a tenant role and synchronize its permissions.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $permissionIds
     */
    public function create(array $attributes, array $permissionIds = []): Role
    {
        return DB::connection('tenant')->transaction(function () use ($attributes, $permissionIds): Role {
            $role = Role::create($attributes);
            $role->permissions()->sync($permissionIds);
            $this->audit->recordSnapshot($role, TenantAuditAction::CREATED, null, $role->attributesToArray());
            $this->audit->recordSnapshot($role, TenantAuditAction::PERMISSION_CHANGED, ['permissions' => []], ['permissions' => $this->permissionNames($role)]);

            return $role;
        });
    }

    /**
     * Persist tenant role changes and synchronize its permissions.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $permissionIds
     */
    public function update(Role $role, array $attributes, array $permissionIds = []): Role
    {
        return DB::connection('tenant')->transaction(function () use ($role, $attributes, $permissionIds): Role {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            $before = $role->attributesToArray();
            $permissions = $this->permissionNames($role);
            $role->update($attributes);
            $role->permissions()->sync($permissionIds);
            $this->audit->recordSnapshot($role, TenantAuditAction::UPDATED, $before, $role->attributesToArray());
            $this->audit->recordSnapshot($role, TenantAuditAction::PERMISSION_CHANGED, ['permissions' => $permissions], ['permissions' => $this->permissionNames($role)]);

            return $role->refresh();
        });
    }

    /**
     * Retrieve the IDs of permissions assigned to a tenant role.
     *
     * @return array<int, int>
     */
    public function permissionIds(Role $role): array
    {
        return $role->permissions()
            ->pluck('permissions.id')
            ->map(fn (mixed $permissionId): int => (int) $permissionId)
            ->all();
    }

    /**
     * Determine whether any tenant users are assigned to a role.
     */
    public function hasAssignedUsers(Role $role): bool
    {
        return $role->users()->exists();
    }

    /**
     * Determine whether a tenant user has a role with the given permission.
     */
    public function userHasPermission(User $user, string $permission): bool
    {
        return $user->roles()
            ->where('status', true)
            ->whereHas('permissions', fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    /**
     * Delete a tenant role.
     */
    public function delete(Role $role): bool
    {
        return DB::connection('tenant')->transaction(function () use ($role): bool {
            $before = $role->attributesToArray();
            $deleted = (bool) $role->delete();
            $this->audit->recordSnapshot($role, TenantAuditAction::DELETED, $before, null);

            return $deleted;
        });
    }

    /** @return list<string> */
    private function permissionNames(Role $role): array
    {
        return $role->permissions()->orderBy('slug')->pluck('slug')->all();
    }
}
