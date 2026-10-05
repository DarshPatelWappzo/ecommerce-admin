<?php

namespace App\Console\Commands;

use App\Models\Tenant\Refund;
use App\Models\TenantDatabase;
use App\Services\TenantConnectionManager;
use App\Services\TenantRefundService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

#[Signature('returns:reconcile-refunds {--tenant= : Central tenant database ID} {--limit=100 : Maximum refunds per tenant}')]
#[Description('Recover pending Razorpay refunds using the reserved idempotent request')]
class ReconcileRefunds extends Command
{
    public function handle(TenantConnectionManager $connections, TenantRefundService $refunds): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 1000.');

            return self::FAILURE;
        }
        $failed = false;
        foreach (TenantDatabase::where('status', 'active')->when($this->option('tenant'), fn($query, $id) => $query->whereKey($id))->lazyById() as $tenant) {
            try {
                Auth::forgetGuards();
                $connections->connect($tenant);
                foreach (Refund::where('gateway', 'razorpay')->where('status', 'processing')->orderBy('updated_at')->limit($limit)->get() as $refund) {
                    try {
                        $refunds->reconcile($refund->return_request_id);
                    } catch (Throwable) {
                        $failed = true;
                        $this->error("Tenant {$tenant->id}, refund {$refund->id}: reconciliation failed; retained for retry.");
                    } finally {
                        $refund->touch();
                    }
                }
            } catch (Throwable) {
                $failed = true;
                $this->error("Tenant {$tenant->id}: refund recovery unavailable.");
            } finally {
                Auth::forgetGuards();
                $connections->disconnect();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
