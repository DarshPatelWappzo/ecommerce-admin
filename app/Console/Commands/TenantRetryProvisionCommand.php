<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('tenant:retry-provision {user_id}')]
#[Description('Retry provisioning all failed tenant databases for a central user')]
class TenantRetryProvisionCommand extends Command
{
    public function __construct(private readonly TenantProvisioningService $provisioningService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $centralUser = User::with('domains.tenantDatabase')->findOrFail($this->argument('user_id'));
        $plainPassword = Str::ucfirst(Str::lower(Str::password(5, letters: true, numbers: false, symbols: false))).'@'.random_int(100, 999);
        foreach ($centralUser->domains as $domain) {
            $tenantDatabase = $domain->tenantDatabase;
            if ($tenantDatabase?->status === 'failed') {
                $this->provisioningService->provision($centralUser, $domain, $plainPassword);
            }
        }

        return self::SUCCESS;
    }
}
