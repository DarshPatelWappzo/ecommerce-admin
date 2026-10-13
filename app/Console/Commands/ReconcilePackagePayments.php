<?php

namespace App\Console\Commands;

use App\Exceptions\PlatformPaymentException;
use App\Models\PackagePayment;
use App\Services\PackageCheckoutService;
use Illuminate\Console\Command;

class ReconcilePackagePayments extends Command
{
    protected $signature = 'packages:reconcile-payments {--payment= : Reconcile one local payment ID} {--limit=100 : Maximum attempts}';

    protected $description = 'Confirm pending platform package payments without creating orders';

    public function handle(PackageCheckoutService $checkout): int
    {
        $failed = 0;
        $records = PackagePayment::query()->whereNotIn('status', ['captured', 'rejected'])
            ->when($this->option('payment'), fn($query) => $query->whereKey($this->option('payment')))
            ->orderBy('updated_at')
            ->limit(max(1, min(1000, (int) $this->option('limit'))))
            ->get();
        foreach ($records as $payment) {
            try {
                $checkout->reconcile($payment);
            } catch (PlatformPaymentException) {
                $failed++;
            }
            $payment->touch();
        }
        $this->info('Checked ' . $records->count() . ' attempts; ' . $failed . ' require another reconciliation.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
