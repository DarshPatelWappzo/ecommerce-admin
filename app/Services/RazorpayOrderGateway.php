<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Razorpay\Api\Utility;
use Throwable;

class RazorpayOrderGateway implements OrderPaymentGateway
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function publicKey(): string
    {
        return (string) config('order_payments.razorpay.key_id');
    }

    private function api(): Api
    {
        if (! $this->publicKey() || ! config('order_payments.razorpay.key_secret')) {
            throw new OrderPaymentGatewayException;
        }

        return new Api($this->publicKey(), config('order_payments.razorpay.key_secret'));
    }

    public function createOrder(array $data): array
    {
        return $this->call(
            fn(): array => $this->api()
                ->order
                ->create($data)
                ->toArray()
        );
    }

    public function ensureConfigured(): void
    {
        $this->api();
    }

    public function findOrder(string $receipt): ?array
    {
        $orders = $this->call(
            fn(): array => $this->api()
                ->order
                ->all(['receipt' => $receipt, 'count' => 2])
                ->toArray()
        );
        if (count($orders['items'] ?? []) > 1) {
            throw new OrderPaymentGatewayException;
        }

        return $orders['items'][0] ?? null;
    }

    public function fetchOrder(string $id): array
    {
        return $this->call(
            fn(): array => $this->api()
                ->order
                ->fetch($id)
                ->toArray()
        );
    }

    public function fetchPayment(string $id): array
    {
        return $this->call(
            fn(): array => $this->api()
                ->payment
                ->fetch($id)
                ->toArray()
        );
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): void
    {
        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);
        } catch (SignatureVerificationError) {
            throw ValidationException::withMessages(['razorpay_signature' => 'The payment signature is invalid.']);
        }
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        $secret = (string) config('order_payments.razorpay.webhook_secret');
        if ($secret === '') {
            throw new OrderPaymentGatewayException;
        }
        try {
            (new Utility)->verifyWebhookSignature($body, $signature, $secret);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    private function call(callable $operation): array
    {
        try {
            return $operation();
        } catch (Throwable) {
            throw new OrderPaymentGatewayException;
        }
    }
}
