<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\UserDomain;
use Illuminate\Support\Collection;

class UserDomainRepository
{
    /**
     * Create all initial domains for a user.
     *
     * @param  array<int, string>  $domainNames
     * @return Collection<int, UserDomain>
     */
    public function createForUser(User $user, array $domainNames): Collection
    {
        return collect($domainNames)->map(fn (string $domainName): UserDomain => UserDomain::create([
            'user_id' => $user->id,
            'domain_name' => $domainName,
        ]));
    }

    /**
     * Synchronize a user's domains and soft-delete removed domains.
     *
     * @param  array<int, string>  $domainNames
     */
    public function syncForUser(User $user, array $domainNames): void
    {
        $currentDomains = $user->domains()->get()->keyBy('domain_name');
        $keptDomainIds = [];

        foreach ($domainNames as $domainName) {
            $domain = $currentDomains->get($domainName);

            if ($domain instanceof UserDomain) {
                $keptDomainIds[] = $domain->id;

                continue;
            }

            UserDomain::create([
                'user_id' => $user->id,
                'domain_name' => $domainName,
            ]);
        }

        $currentDomains
            ->reject(fn (UserDomain $domain): bool => in_array($domain->id, $keptDomainIds, true))
            ->each(fn (UserDomain $domain): bool => $domain->delete());
    }
}
