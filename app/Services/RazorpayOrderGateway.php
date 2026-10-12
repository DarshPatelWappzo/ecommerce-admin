<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use Throwable;

class RazorpayOrderGateway implements OrderPaymentGateway
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function publicKey(?int $credentialId = null): string
    {
        return $this->credentials($credentialId)['key_id'];
    }

    /** @return array{key_id: string, key_secret: string, webhook_secret?: string} Backend-only credentials. */
    protected function credentials(?int $credentialId): array
    {
        $settings = app(PaymentGatewayService::class);

        return $settings->credentials($credentialId ?? $settings->activeVersion());
    }

    public function createOrder(array $data, ?int $credentialId = null): array
    {
        return $this->request('POST', 'orders', $data, $credentialId);
    }

    public function ensureConfigured(?int $credentialId = null): void
    {
        $this->credentials($credentialId);
    }

    public function findOrder(string $receipt, ?int $credentialId = null): ?array
    {
        $orders = $this->request('GET', 'orders', ['receipt' => $receipt, 'count' => 2], $credentialId);
        if (
            ! is_array($orders['items'] ?? null) || ! array_is_list($orders['items'])
            || count($orders['items']) > 1 || (isset($orders['items'][0]) && ! is_array($orders['items'][0]))
        ) {
            throw new OrderPaymentGatewayException;
        }

        return $orders['items'][0] ?? null;
    }

    public function fetchOrder(string $id, ?int $credentialId = null): array
    {
        return $this->request('GET', 'orders/'.rawurlencode($id), [], $credentialId);
    }

    public function fetchPayment(string $id, ?int $credentialId = null): array
    {
        return $this->request('GET', 'payments/'.rawurlencode($id), [], $credentialId);
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature, ?int $credentialId = null): void
    {
        if (! hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $this->credentials($credentialId)['key_secret']), $signature)) {
            throw ValidationException::withMessages(['razorpay_signature' => 'The payment signature is invalid.']);
        }
    }

    public function fetchOrderPayments(string $id, ?int $credentialId = null): array
    {
        $result = $this->request('GET', 'orders/'.rawurlencode($id).'/payments', [], $credentialId);
        if (
            ! is_array($result['items'] ?? null) || ! array_is_list($result['items'])
            || ! is_int($result['count'] ?? null) || $result['count'] !== count($result['items'])
        ) {
            throw new OrderPaymentGatewayException;
        }

        return $result['items'];
    }

    public function verifyWebhook(string $body, string $signature, ?int $credentialId = null): bool
    {
        $secret = $this->credentials($credentialId)['webhook_secret'] ?? '';
        if ($secret === '') {
            throw new OrderPaymentGatewayException;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    public function testConnection(int $credentialId): void
    {
        try {
            $response = app(RazorpayClientFactory::class)->make($credentialId)->get('orders', ['count' => 1]);
        } catch (ConnectionException $exception) {
            $message = str_contains($exception->getMessage(), 'cURL error 60:')
                ? 'The server could not verify Razorpay\'s HTTPS certificate. Configure PHP\'s trusted CA certificates and retry. A webhook secret is not required for this test.'
                : 'The server could not connect to Razorpay. Check its internet connection, DNS, firewall and TLS configuration, then retry.';

            throw ValidationException::withMessages(['connection' => $message]);
        }
        if (! $response->successful()) {
            $message = match ($response->status()) {
                401 => 'Razorpay rejected the saved Key ID and Key Secret. Save the matching pair for the selected test/live environment, then retry.',
                403 => 'Razorpay denied API access. Check the merchant account permissions and restrictions.',
                429 => 'Razorpay is rate limiting requests. Wait briefly and retry.',
                default => 'Razorpay could not complete the connection test. Retry later; this does not confirm that the saved credentials are invalid.',
            };

            throw ValidationException::withMessages(['connection' => $message]);
        }
        if (! is_array($response->json('items'))) {
            throw ValidationException::withMessages(['connection' => 'Razorpay returned an unexpected response. API authentication could not be confirmed.']);
        }
    }

    /**
     * Send one bounded request with credentials resolved from the current tenant.
     *
     * @param  array<string, mixed>  $data  Provider request payload.
     * @param  array<string, string>  $headers  Request-local headers.
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $data, ?int $credentialId, array $headers = []): array
    {
        return $this->call(function () use ($method, $path, $data, $credentialId, $headers): array {
            $credentialId ??= app(PaymentGatewayService::class)->activeVersion();
            $client = app(RazorpayClientFactory::class)->make($credentialId)->withHeaders($headers);
            $response = $method === 'GET' ? $client->get($path, $data) : $client->post($path, $data);
            if (! $response->successful() || ! is_array($response->json())) {
                throw new OrderPaymentGatewayException(creationRejected: $method === 'POST' && $path === 'orders'
                    && in_array($response->status(), [400, 401, 403, 422], true)
                    && is_array($response->json('error')));
            }

            return $response->json();
        });
    }

    protected function call(callable $operation): array
    {
        try {
            return $operation();
        } catch (OrderPaymentGatewayException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new OrderPaymentGatewayException(creationRejected: str_contains($exception->getMessage(), 'cURL error 60:'));
        } catch (Throwable) {
            throw new OrderPaymentGatewayException;
        }
    }
}
