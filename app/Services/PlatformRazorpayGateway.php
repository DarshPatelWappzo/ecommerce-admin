<?php

namespace App\Services;

use App\Exceptions\PlatformPaymentException;
use App\Models\PackagePayment;
use App\Models\PlatformGatewayVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Errors\SignatureVerificationError;
use Razorpay\Api\Utility;
use Throwable;

class PlatformRazorpayGateway implements PlatformPaymentGateway
{
    /** @return array<string, mixed> */
    public function createOrder(PackagePayment $payment, PlatformGatewayVersion $version): array
    {
        return $this->request($version, 'POST', 'orders', [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'receipt' => $payment->reference,
            'notes' => ['purpose' => 'platform_package', 'reference' => $payment->reference, 'mode' => $payment->mode, 'tenant_database_id' => (string) $payment->tenant_database_id],
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findOrder(PackagePayment $payment, PlatformGatewayVersion $version): ?array
    {
        $result = $this->request($version, 'GET', 'orders', ['receipt' => $payment->reference, 'count' => 2]);
        if (! is_array($result['items'] ?? null) || count($result['items']) > 1) {
            throw new PlatformPaymentException;
        }

        return $result['items'][0] ?? null;
    }

    public function verifySignature(PackagePayment $payment, PlatformGatewayVersion $version, string $paymentId, string $signature): void
    {
        try {
            (new Utility)->verifySignature($payment->gateway_order_id . '|' . $paymentId, $signature, $version->credentials['key_secret']);
        } catch (SignatureVerificationError) {
            throw ValidationException::withMessages(['razorpay_signature' => 'The payment signature is invalid.']);
        }
    }

    /** @return array{order: array<string, mixed>, payments: list<array<string, mixed>>} */
    public function fetchStatus(PackagePayment $payment, PlatformGatewayVersion $version, ?string $paymentId = null): array
    {
        $order = $this->request($version, 'GET', 'orders/' . rawurlencode($payment->gateway_order_id));
        $payments = $paymentId
            ? [$this->request($version, 'GET', 'payments/' . rawurlencode($paymentId))]
            : ($this->request($version, 'GET', 'orders/' . rawurlencode($payment->gateway_order_id) . '/payments')['items'] ?? null);
        if (! is_array($payments) || ! array_is_list($payments)) {
            throw new PlatformPaymentException;
        }
        if ($paymentId !== null && ($payments[0]['id'] ?? null) !== $paymentId) {
            throw new PlatformPaymentException;
        }

        return ['order' => $order, 'payments' => $payments];
    }

    /** @return array{type: string, order_id: ?string, payment_id: ?string, amount: ?int, currency: ?string} */
    public function webhook(string $raw, string $signature, PlatformGatewayVersion $version): array
    {
        abort_unless(filled($version->credentials['webhook_secret'] ?? null), 401, 'Invalid webhook configuration.');
        try {
            (new Utility)->verifyWebhookSignature($raw, $signature, $version->credentials['webhook_secret']);
        } catch (SignatureVerificationError) {
            abort(401, 'Invalid webhook signature.');
        }
        $event = json_decode($raw, true);
        abort_unless(is_array($event) && is_string($event['event'] ?? null), 422, 'Invalid webhook event.');
        $payment = $event['payload']['payment']['entity'] ?? [];

        return [
            'type' => $event['event'],
            'order_id' => $payment['order_id'] ?? $event['payload']['order']['entity']['id'] ?? null,
            'payment_id' => $payment['id'] ?? null,
            'amount' => $payment['amount'] ?? null,
            'currency' => $payment['currency'] ?? null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function request(PlatformGatewayVersion $version, string $method, string $path, array $data = []): array
    {
        $credentials = $version->credentials;
        try {
            $response = Http::baseUrl('https://api.razorpay.com/v1')->withBasicAuth($credentials['key_id'], $credentials['key_secret'])
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(20)->withoutRedirecting()
                ->send($method, $path, [$method === 'GET' ? 'query' : 'json' => $data]);
        } catch (Throwable) {
            throw new PlatformPaymentException;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new PlatformPaymentException($method === 'POST' && in_array($response->status(), [400, 401, 403, 422], true));
        }

        return $response->json();
    }
}
