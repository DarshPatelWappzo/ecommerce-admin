<?php

namespace App\Services;

use Razorpay\Api\Request as RazorpayRequest;

class RazorpayRefundGateway extends RazorpayOrderGateway
{
    /** @param array<string, mixed> $data Immutable minor-unit refund payload. */
    public function createRefund(string $paymentId, array $data, string $key): array
    {
        return $this->call(function () use ($paymentId, $data, $key): array {
            $api = $this->api();
            $api->setHeader('X-Refund-Idempotency', $key);
            try {
                return $api->payment->fetch($paymentId)->refund($data)->toArray();
            } finally {
                RazorpayRequest::removeHeader('X-Refund-Idempotency');
            }
        });
    }

    public function fetchRefund(string $id): array
    {
        return $this->call(fn(): array => $this->api()->refund->fetch($id)->toArray());
    }

    public function findRefund(string $paymentId, string $key): ?array
    {
        for ($skip = 0;; $skip += 100) {
            $page = $this->call(fn(): array => $this->api()->payment->fetch($paymentId)->fetchMultipleRefund(['count' => 100, 'skip' => $skip])->toArray());
            foreach ($page['items'] ?? [] as $refund) {
                if (($refund['receipt'] ?? null) === $key) {
                    return $refund;
                }
            }
            if (count($page['items'] ?? []) < 100) {
                return null;
            }
        }
    }
}
