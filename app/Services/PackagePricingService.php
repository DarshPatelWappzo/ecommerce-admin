<?php

namespace App\Services;

use App\Models\Package;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class PackagePricingService
{
    /** @return array{package_id: int, name: string, billing_cycle: string, duration_months: int, amount: int, currency: string, pricing_basis: string, capacity: null} */
    public function quote(int $packageId, string $cycle): array
    {
        $package = Package::on(config('subscriptions.connection'))->where('status', 'active')->where('is_tenant_visible', true)->findOrFail($packageId);
        $price = $package->prices()->where('billing_cycle', $cycle)->where('currency', 'INR')->where('is_active', true)->first();
        if (! $price || ! isset(self::CYCLES[$cycle]) || $price->duration_months !== self::CYCLES[$cycle]) {
            throw ValidationException::withMessages(['billing_cycle' => 'This billing cycle is unavailable.']);
        }
        $amount = BigDecimal::of($price->price)->multipliedBy(100)->toScale(0, RoundingMode::HalfUp)->toInt();
        if ($amount < 100 || $amount > 999999999999) {
            throw ValidationException::withMessages(['package' => 'The configured price is outside the supported payment range.']);
        }

        return ['package_id' => $package->id, 'name' => $package->name, 'billing_cycle' => $cycle, 'duration_months' => $price->duration_months, 'amount' => $amount, 'currency' => 'INR', 'pricing_basis' => 'fixed_cycle_price', 'capacity' => null];
    }

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
