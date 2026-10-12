<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class InfrastructureCostEstimator
{
    /**
     * Internal estimate only; never a subscription price. Equal bounds select the minimum
     * at the boundary. Above-range and unknown upper bounds produce no estimate.
     *
     * @return array{status: string, estimated_cost: string|null}
     */
    public function estimate(?int $actualUsers, ?int $minUsers, ?int $maxUsers, ?string $minCost, ?string $maxCost): array
    {
        if (($actualUsers !== null && $actualUsers < 0) || ($minUsers !== null && $minUsers < 0) || ($maxUsers !== null && ($maxUsers < 0 || ($minUsers !== null && $maxUsers < $minUsers)))) {
            throw new InvalidArgumentException('Invalid user range.');
        }
        $minimum = $minCost === null ? null : BigDecimal::of($minCost)->toScale(2);
        $maximum = $maxCost === null ? null : BigDecimal::of($maxCost)->toScale(2);
        if ($minimum?->isNegative() || $maximum?->isNegative() || ($minimum !== null && $maximum !== null && $maximum->isLessThan($minimum))) {
            throw new InvalidArgumentException('Invalid cost range.');
        }
        if ($actualUsers === null || $minUsers === null || $minimum === null) {
            return ['status' => 'unavailable', 'estimated_cost' => null];
        }
        if ($actualUsers <= $minUsers) {
            return ['status' => 'estimated', 'estimated_cost' => (string) $minimum];
        }
        if ($maxUsers === null || $maximum === null) {
            return ['status' => 'unavailable', 'estimated_cost' => null];
        }
        if ($actualUsers > $maxUsers) {
            return ['status' => 'out_of_range', 'estimated_cost' => null];
        }
        $cost = $maximum->minus($minimum)->multipliedBy($actualUsers - $minUsers)
            ->dividedBy($maxUsers - $minUsers, 2, RoundingMode::HalfUp)->plus($minimum);

        return ['status' => 'estimated', 'estimated_cost' => (string) $cost];
    }
}
