<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRepository
{
    /**
     * Create a regular user account.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createRegularUser(array $attributes): User
    {
        return User::create($attributes);
    }

    /**
     * Persist changes to an existing regular user.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateRegularUser(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh();
    }

    /**
     * Load a regular user with their domains for editing.
     */
    public function loadRegularUserForEdit(User $user): User
    {
        return $user->load('domains');
    }

    /**
     * Retrieve a paginated regular-user list filtered by an optional search term.
     */
    public function paginateRegularUsers(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->where('is_super_admin', false)
            ->with(['tenantDatabases' => function (HasMany $query): void {
                $query->select(['id', 'user_id', 'domain_id', 'database_name', 'status', 'provisioned_at'])
                    ->whereHas('domain');
            }])
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
