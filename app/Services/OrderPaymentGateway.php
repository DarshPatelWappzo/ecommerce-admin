<?php

namespace App\Services;

interface OrderPaymentGateway
{
    public function name(): string;

    public function publicKey(): string;

    public function ensureConfigured(): void;

    /** @param array{receipt: string, amount: int, currency: string, partial_payment: bool, notes: array<string, string>} $data */
    public function createOrder(array $data): array;

    public function findOrder(string $receipt): ?array;

    public function fetchOrder(string $id): array;

    public function fetchPayment(string $id): array;

    public function verifyPayment(string $orderId, string $paymentId, string $signature): void;

    public function verifyWebhook(string $body, string $signature): bool;
}
