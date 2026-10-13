<?php

namespace App\Services;

use App\Models\PackagePayment;
use App\Models\PlatformGatewayVersion;

interface PlatformPaymentGateway
{
    /** @return array<string, mixed> */
    public function createOrder(PackagePayment $payment, PlatformGatewayVersion $version): array;

    /** @return array<string, mixed>|null */
    public function findOrder(PackagePayment $payment, PlatformGatewayVersion $version): ?array;

    public function verifySignature(PackagePayment $payment, PlatformGatewayVersion $version, string $paymentId, string $signature): void;

    /** @return array{order: array<string, mixed>, payments: list<array<string, mixed>>} */
    public function fetchStatus(PackagePayment $payment, PlatformGatewayVersion $version, ?string $paymentId = null): array;

    /** @return array{type: string, order_id: ?string, payment_id: ?string, amount: ?int, currency: ?string} */
    public function webhook(string $raw, string $signature, PlatformGatewayVersion $version): array;
}
