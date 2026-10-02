<?php

namespace App\Console\Commands;

use App\Models\TenantDatabase;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tenant:list')]
#[Description('List provisioned tenant databases')]
class TenantListCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->table(['Central user', 'Email', 'Domain database', 'Status', 'Provisioned'], TenantDatabase::with(['user:id,email'])->latest()->get()->map(fn (TenantDatabase $tenantDatabase): array => [
            $tenantDatabase->user_id,
            $tenantDatabase->user?->email,
            $tenantDatabase->database_name,
            $tenantDatabase->status,
            $tenantDatabase->provisioned_at?->toDateTimeString(),
        ])->all());

        return self::SUCCESS;
    }
}
