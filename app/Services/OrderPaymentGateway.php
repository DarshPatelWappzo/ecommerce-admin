<?php

namespace App\Services;

interface OrderPaymentGateway
{
    public function name(): string;

    public function publicKey(?int $credentialId = null): string;

    public function ensureConfigured(?int $credentialId = null): void;

    /** @param array{receipt: string, amount: int, currency: string, partial_payment: bool, notes: array<string, string>} $data */
    public function createOrder(array $data, ?int $credentialId = null): array;

    public function findOrder(string $receipt, ?int $credentialId = null): ?array;

    public function fetchOrder(string $id, ?int $credentialId = null): array;

    public function fetchPayment(string $id, ?int $credentialId = null): array;

    /** @return list<array<string, mixed>> */
    public function fetchOrderPayments(string $id, ?int $credentialId = null): array;

    public function verifyPayment(string $orderId, string $paymentId, string $signature, ?int $credentialId = null): void;

    public function verifyWebhook(string $body, string $signature, ?int $credentialId = null): bool;
}
