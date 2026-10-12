<?php

namespace App\Services;

class RazorpayRefundGateway extends RazorpayOrderGateway
{
    /** @param array<string, mixed> $data Immutable minor-unit refund payload. */
    public function createRefund(string $paymentId, array $data, string $key, ?int $credentialId = null): array
    {
        return $this->request('POST', 'payments/'.rawurlencode($paymentId).'/refund', $data, $credentialId, ['X-Refund-Idempotency' => $key]);
    }

    public function fetchRefund(string $id, ?int $credentialId = null): array
    {
        return $this->request('GET', 'refunds/'.rawurlencode($id), [], $credentialId);
    }

    public function findRefund(string $paymentId, string $key, ?int $credentialId = null): ?array
    {
        for ($skip = 0; ; $skip += 100) {
            $page = $this->request('GET', 'payments/'.rawurlencode($paymentId).'/refunds', ['count' => 100, 'skip' => $skip], $credentialId);
            if (! is_array($page['items'] ?? null) || ! array_is_list($page['items'])) {
                throw new OrderPaymentGatewayException;
            }
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
