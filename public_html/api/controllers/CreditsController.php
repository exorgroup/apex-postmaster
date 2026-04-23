<?php

declare(strict_types=1);

class CreditsController
{
    public static function balance(): void
    {
        $user = AuthMiddleware::authenticate();

        Response::success([
            'balanceCents' => (int)$user['credit_balance_cents'],
            'balanceFormatted' => number_format($user['credit_balance_cents'] / 100, 2),
        ]);
    }

    public static function transactions(): void
    {
        $user = AuthMiddleware::authenticate();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $total = Database::fetch(
            "SELECT COUNT(*) as cnt FROM credit_transactions WHERE user_id = ?",
            [$user['id']]
        )['cnt'] ?? 0;

        $transactions = Database::fetchAll(
            "SELECT id, amount_cents, type, description, balance_after, created_at
             FROM credit_transactions
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT $perPage OFFSET $offset",
            [$user['id']]
        );

        $formatted = array_map(function ($tx) {
            return [
                'id' => (int)$tx['id'],
                'amountCents' => (int)$tx['amount_cents'],
                'type' => $tx['type'],
                'description' => $tx['description'],
                'balanceAfter' => (int)$tx['balance_after'],
                'createdAt' => $tx['created_at'],
            ];
        }, $transactions);

        Response::paginated($formatted, (int)$total, $page, $perPage);
    }

    public static function topup(): void
    {
        $user = AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('amountCents')
            ->integer('amountCents')
            ->min('amountCents', 500); // Minimum €5

        $validator->validate();

        $amountCents = (int)$input['amountCents'];

        if (empty(STRIPE_SECRET_KEY)) {
            Response::error('Payment system not configured', 500);
        }

        // Create Stripe Checkout Session
        $stripe = new StripeClient();

        try {
            $session = $stripe->createCheckoutSession([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => 'APEX PostMaster Credits',
                            'description' => 'Credit top-up for AI features',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => APP_URL . '/app/settings/credits?success=true',
                'cancel_url' => APP_URL . '/app/settings/credits?cancelled=true',
                'metadata' => [
                    'user_id' => $user['id'],
                    'type' => 'credit_topup',
                    'amount_cents' => $amountCents,
                ],
            ]);

            Response::success([
                'checkoutUrl' => $session['url'],
                'sessionId' => $session['id'],
            ]);
        } catch (Exception $e) {
            Response::error('Failed to create checkout session: ' . $e->getMessage(), 500);
        }
    }

    public static function addCredits(int $userId, int $amountCents, string $type, string $description): void
    {
        $user = Database::fetch("SELECT credit_balance_cents FROM users WHERE id = ?", [$userId]);

        if (!$user) {
            return;
        }

        $newBalance = $user['credit_balance_cents'] + $amountCents;

        Database::update('users', ['credit_balance_cents' => $newBalance], 'id = ?', [$userId]);

        Database::insert('credit_transactions', [
            'user_id' => $userId,
            'amount_cents' => $amountCents,
            'type' => $type,
            'description' => $description,
            'balance_after' => $newBalance,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

class StripeClient
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = STRIPE_SECRET_KEY;
    }

    public function createCheckoutSession(array $params): array
    {
        return $this->request('POST', '/v1/checkout/sessions', $params);
    }

    public function createPortalSession(string $customerId, string $returnUrl): array
    {
        return $this->request('POST', '/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->request('GET', '/v1/subscriptions/' . $subscriptionId);
    }

    public function constructWebhookEvent(string $payload, string $signature): array
    {
        // Verify webhook signature
        $timestamp = null;
        $v1Signature = null;

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') $timestamp = $value;
            if ($key === 'v1') $v1Signature = $value;
        }

        if (!$timestamp || !$v1Signature) {
            throw new Exception('Invalid signature');
        }

        $signedPayload = "$timestamp.$payload";
        $expectedSignature = hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET);

        if (!hash_equals($expectedSignature, $v1Signature)) {
            throw new Exception('Signature verification failed');
        }

        // Check timestamp (within 5 minutes)
        if (abs(time() - (int)$timestamp) > 300) {
            throw new Exception('Webhook timestamp too old');
        }

        return json_decode($payload, true);
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = 'https://api.stripe.com' . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ];

        if ($method === 'POST' && !empty($data)) {
            $options['http']['content'] = http_build_query($this->flattenArray($data));
        }

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);

        if (isset($result['error'])) {
            throw new Exception($result['error']['message'] ?? 'Stripe API error');
        }

        return $result;
    }

    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}[$key]" : $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        return $result;
    }
}
