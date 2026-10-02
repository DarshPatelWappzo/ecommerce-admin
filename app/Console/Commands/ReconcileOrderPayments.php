<?php

namespace App\Console\Commands;

use App\Models\Tenant\OrderPaymentCheckout;
use App\Models\TenantDatabase;
use App\Services\TenantConnectionManager;
use App\Services\TenantPaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

#[Signature('orders:reconcile-payments {--tenant= : Central tenant database ID} {--limit=100 : Maximum checkouts per tenant}')]
#[Description('Recover customer order payments from provider facts without creating charges or refunds')]
class ReconcileOrderPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TenantConnectionManager $connections, TenantPaymentService $payments): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 1000.');

            return self::FAILURE;
        }
        $failed = false;
        $tenants = TenantDatabase::where('status', 'active')
            ->when($this->option('tenant'), fn($query, $id) => $query->whereKey($id));
        foreach ($tenants->lazyById() as $tenant) {
            try {
                Auth::forgetGuards();
                $connections->connect($tenant);
                $checkouts = OrderPaymentCheckout::whereNotNull('requested_at')->orderBy('updated_at')->orderBy('id')->limit($limit)->get();
                foreach ($checkouts as $checkout) {
                    try {
                        $payments->recover($checkout->order_id, $tenant->id);
                    } catch (Throwable) {
                        $failed = true;
                        $this->error("Tenant {$tenant->id}, checkout {$checkout->id}: reconciliation failed; retained for retry.");
                    } finally {
                        $checkout->touch();
                    }
                }
            } catch (Throwable) {
                $failed = true;
                $this->error("Tenant {$tenant->id}: payment recovery unavailable.");
            } finally {
                Auth::forgetGuards();
                $connections->disconnect();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
