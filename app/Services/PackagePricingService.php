<?php

namespace App\Services;

use App\Models\Package;
use Brick\Math\BigDecimal;

class PackagePricingService
{
    public const CYCLES = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12];

    public const LIMITS = ['max_monthly_orders' => 'Monthly orders', 'max_admin_users' => 'Admin users', 'audit_retention_days' => 'Audit retention (days)'];

    /** Persist only supplied catalog values. Blank disabled prices preserve prior amounts.
     * @param  array{subscription_prices?: array<string, array{price?: string|null, is_active: bool|int|string}>, package_limits?: array<string, int|string|null>}  $attributes
     */
    public function save(Package $package, array $attributes): void
    {
        foreach ($attributes['subscription_prices'] ?? [] as $cycle => $input) {
            $key = ['billing_cycle' => $cycle, 'currency' => config('subscriptions.currency')];
            if (! isset($input['price'])) {
                $package->prices()->where($key)->update(['is_active' => false]);

                continue;
            }
            $package->prices()->updateOrCreate($key, [
                'duration_months' => self::CYCLES[$cycle],
                'price' => (string) BigDecimal::of((string) $input['price'])->toScale(2),
                'is_active' => (bool) $input['is_active'],
            ]);
        }
        foreach ($attributes['package_limits'] ?? [] as $key => $value) {
            $package->limits()->updateOrCreate(['limit_key' => $key], ['limit_value' => $value]);
        }
    }
}
