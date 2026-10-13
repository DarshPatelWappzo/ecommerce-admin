<?php

namespace App\Repositories;

use App\Models\PackagePayment;
use App\Models\TenantDatabase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class PlatformPaymentRepository
{
    public function connection(): Connection
    {
        return DB::connection(config('subscriptions.connection'));
    }

    public function owned(TenantDatabase $tenant, int $id): PackagePayment
    {
        return PackagePayment::query()->where('tenant_database_id', $tenant->id)->findOrFail($id);
    }

    public function history(TenantDatabase $tenant): LengthAwarePaginator
    {
        return PackagePayment::query()->with('subscription')->where('tenant_database_id', $tenant->id)->latest('id')->paginate(15)->withQueryString();
    }

    public function requiringResolution(): LengthAwarePaginator
    {
        return PackagePayment::query()->where('status', 'captured')->where('failure_code', 'resolution_required')
            ->latest('id')->paginate(15)->withQueryString();
    }

    public function lockOwner(TenantDatabase $tenant): void
    {
        $this->connection()->table('users')->where('id', $tenant->user_id)->lockForUpdate()->firstOrFail();
    }
}
