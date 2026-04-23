<?php

declare(strict_types=1);

class StripeClient
{
    private string $apiKey;
    private string $apiBase = 'https://api.stripe.com/v1';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? STRIPE_SECRET_KEY;
    }

    public function createCheckoutSession(array $params): array
    {
        return $this->request('POST', '/checkout/sessions', $params);
    }

    public function createPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->request('POST', '/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    public function createCustomer(string $email, string $name): array
    {
        return $this->request('POST', '/customers', [
            'email' => $email,
            'name' => $name,
        ]);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->request('GET', "/subscriptions/{$subscriptionId}");
    }

    public function cancelSubscription(string $subscriptionId, bool $immediately = false): array
    {
        if ($immediately) {
            return $this->request('DELETE', "/subscriptions/{$subscriptionId}");
        }

        return $this->request('POST', "/subscriptions/{$subscriptionId}", [
            'cancel_at_period_end' => 'true',
        ]);
    }

    public function constructWebhookEvent(string $payload, string $signature): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signature) as $item) {
            [$key, $value] = explode('=', $item, 2);
            if ($key === 't') {
                $timestamp = (int)$value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || empty($signatures)) {
            throw new Exception('Invalid signature header');
        }

        // Verify timestamp (allow 5 minute tolerance)
        if (abs(time() - $timestamp) > 300) {
            throw new Exception('Webhook timestamp too old');
        }

        // Verify signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new Exception('Webhook signature verification failed');
        }

        return json_decode($payload, true);
    }

    private function request(string $method, string $endpoint, array $params = []): array
    {
        $url = $this->apiBase . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2023-10-16',
        ];

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ];

        if ($method === 'POST' && !empty($params)) {
            $options['http']['content'] = $this->buildQueryString($params);
        } elseif ($method === 'GET' && !empty($params)) {
            $url .= '?' . $this->buildQueryString($params);
        }

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        $result = json_decode($response, true);

        if (isset($result['error'])) {
            throw new Exception($result['error']['message'] ?? 'Stripe API error');
        }

        return $result ?? [];
    }

    private function buildQueryString(array $params, string $prefix = ''): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $parts[] = $this->buildQueryString($value, $fullKey);
            } else {
                $parts[] = urlencode($fullKey) . '=' . urlencode((string)$value);
            }
        }

        return implode('&', $parts);
    }
}
