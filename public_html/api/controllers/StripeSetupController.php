<?php

declare(strict_types=1);

/**
 * StripeSetupController
 *
 * Creates and updates Stripe products and prices based on .env configuration.
 * This should be run once during initial setup or when plan configuration changes.
 */
class StripeSetupController
{
    private static string $apiUrl = 'https://api.stripe.com/v1';

    /**
     * Sync all products and prices to Stripe
     */
    public static function sync(): void
    {
        // Require admin authentication
        $user = AuthMiddleware::authenticate();
        if (!$user || $user['role'] !== 'admin') {
            Response::error('Admin access required', 403);
            return;
        }

        if (empty(STRIPE_SECRET_KEY)) {
            Response::error('Stripe API key not configured', 500);
            return;
        }

        $results = [
            'products' => [],
            'prices' => [],
            'env_updates' => [],
            'errors' => [],
            'debug' => [],
        ];

        // Plans to sync (skip free - no payment needed)
        $plansToSync = ['creator', 'pro', 'team', 'agency'];

        foreach ($plansToSync as $planKey) {
            $planConfig = PLAN_LIMITS[$planKey] ?? null;
            if (!$planConfig) {
                $results['errors'][$planKey] = 'Plan config not found';
                continue;
            }

            // Debug: log plan config
            $results['debug'][$planKey] = [
                'price_monthly' => $planConfig['price_monthly'] ?? 'not set',
                'price_annual' => $planConfig['price_annual'] ?? 'not set',
                'annual_billing_enabled' => ANNUAL_BILLING_ENABLED,
            ];

            try {
                // Create or update the product
                $product = self::syncProduct($planKey, $planConfig);
                $results['products'][$planKey] = [
                    'id' => $product['id'],
                    'name' => $product['name'] ?? $planKey,
                ];

                // Create or update prices
                $prices = self::syncPrices($planKey, $product['id'], $planConfig);
                $results['prices'][$planKey] = $prices;

                // Generate .env entries
                $envKey = strtoupper($planKey);
                $results['env_updates']["STRIPE_PRODUCT_{$envKey}"] = $product['id'];
                if (isset($prices['monthly']['id'])) {
                    $results['env_updates']["STRIPE_PRICE_{$envKey}_MONTHLY"] = $prices['monthly']['id'];
                }
                if (isset($prices['annual']['id'])) {
                    $results['env_updates']["STRIPE_PRICE_{$envKey}_ANNUAL"] = $prices['annual']['id'];
                }
                if (isset($prices['seat']['id'])) {
                    $results['env_updates']["STRIPE_PRICE_{$envKey}_SEAT"] = $prices['seat']['id'];
                }

            } catch (Exception $e) {
                $results['errors'][$planKey] = $e->getMessage();
            }
        }

        // Sync credit packages
        foreach (CREDIT_PACKAGES as $packageKey => $packageConfig) {
            try {
                $product = self::syncCreditProduct($packageKey, $packageConfig);
                $results['products']["credits_{$packageKey}"] = $product;

                $price = self::syncCreditPrice($packageKey, $product['id'], $packageConfig);
                $results['prices']["credits_{$packageKey}"] = $price;

                $envKey = strtoupper($packageKey);
                $results['env_updates']["STRIPE_PRODUCT_CREDITS_{$envKey}"] = $product['id'];
                $results['env_updates']["STRIPE_PRICE_CREDITS_{$envKey}"] = $price['id'];

            } catch (Exception $e) {
                $results['errors']["credits_{$packageKey}"] = $e->getMessage();
            }
        }

        Response::success($results);
    }

    /**
     * Create or update a subscription product
     */
    private static function syncProduct(string $planKey, array $config): array
    {
        $productName = APP_NAME . ' - ' . ucfirst($planKey) . ' Plan';
        $metadata = [
            'plan_key' => $planKey,
            'social_accounts' => (string)$config['social_accounts'],
            'ai_generations' => (string)$config['ai_generations'],
            'streams' => (string)$config['streams'],
        ];

        // Check if product already exists via metadata lookup
        $existingProduct = self::findProductByMetadata('plan_key', $planKey);

        if ($existingProduct) {
            // Update existing product
            return self::stripeRequest('POST', "/products/{$existingProduct['id']}", [
                'name' => $productName,
                'metadata' => $metadata,
                'active' => 'true',
            ]);
        }

        // Create new product
        return self::stripeRequest('POST', '/products', [
            'name' => $productName,
            'metadata' => $metadata,
            'type' => 'service',
        ]);
    }

    /**
     * Create or update prices for a plan
     */
    private static function syncPrices(string $planKey, string $productId, array $config): array
    {
        $prices = [];
        $currency = 'eur';

        // Monthly price
        if ($config['price_monthly'] > 0) {
            $prices['monthly'] = self::syncPrice(
                $productId,
                $config['price_monthly'],
                $currency,
                'month',
                "{$planKey}_monthly"
            );
        }

        // Annual price
        if ($config['price_annual'] > 0 && ANNUAL_BILLING_ENABLED) {
            $prices['annual'] = self::syncPrice(
                $productId,
                $config['price_annual'],
                $currency,
                'year',
                "{$planKey}_annual"
            );
        }

        // Per-seat price (if seats are available beyond included)
        if ($config['seat_price'] > 0 && $config['max_seats'] > $config['included_seats']) {
            $prices['seat'] = self::syncPrice(
                $productId,
                $config['seat_price'],
                $currency,
                'month',
                "{$planKey}_seat",
                true // metered/quantity price
            );
        }

        return $prices;
    }

    /**
     * Create or update a single price
     */
    private static function syncPrice(
        string $productId,
        int $amount,
        string $currency,
        string $interval,
        string $lookupKey,
        bool $isQuantityBased = false
    ): array {
        // Look for existing price with this lookup key
        $existingPrice = self::findPriceByLookupKey($lookupKey);

        // Prices can't be updated in Stripe - we need to create new ones if amount changed
        if ($existingPrice) {
            // Check if amount matches
            if ($existingPrice['unit_amount'] === $amount &&
                $existingPrice['recurring']['interval'] === $interval) {
                return $existingPrice; // No change needed
            }

            // Deactivate old price
            self::stripeRequest('POST', "/prices/{$existingPrice['id']}", [
                'active' => 'false',
            ]);
        }

        // Create new price
        $priceData = [
            'product' => $productId,
            'unit_amount' => $amount,
            'currency' => $currency,
            'recurring' => [
                'interval' => $interval,
            ],
            'lookup_key' => $lookupKey,
            'transfer_lookup_key' => 'true',
            'tax_behavior' => PRICES_STORED_AS === 'inclusive' ? 'inclusive' : 'exclusive',
        ];

        return self::stripeRequest('POST', '/prices', $priceData);
    }

    /**
     * Create or update a credit package product
     */
    private static function syncCreditProduct(string $packageKey, array $config): array
    {
        $totalCredits = $config['amount'] + $config['bonus'];
        $productName = APP_NAME . ' - AI Credits (' . ucfirst($packageKey) . ')';
        $metadata = [
            'package_key' => $packageKey,
            'credits' => (string)$totalCredits,
            'type' => 'credits',
        ];

        $existingProduct = self::findProductByMetadata('package_key', $packageKey);

        if ($existingProduct) {
            return self::stripeRequest('POST', "/products/{$existingProduct['id']}", [
                'name' => $productName,
                'metadata' => $metadata,
                'active' => 'true',
            ]);
        }

        return self::stripeRequest('POST', '/products', [
            'name' => $productName,
            'metadata' => $metadata,
            'type' => 'service',
        ]);
    }

    /**
     * Create or update a credit package price (one-time)
     */
    private static function syncCreditPrice(string $packageKey, string $productId, array $config): array
    {
        $lookupKey = "credits_{$packageKey}";
        $existingPrice = self::findPriceByLookupKey($lookupKey);

        if ($existingPrice && $existingPrice['unit_amount'] === $config['price']) {
            return $existingPrice;
        }

        if ($existingPrice) {
            self::stripeRequest('POST', "/prices/{$existingPrice['id']}", [
                'active' => 'false',
            ]);
        }

        return self::stripeRequest('POST', '/prices', [
            'product' => $productId,
            'unit_amount' => $config['price'],
            'currency' => 'eur',
            'lookup_key' => $lookupKey,
            'transfer_lookup_key' => 'true',
            'tax_behavior' => PRICES_STORED_AS === 'inclusive' ? 'inclusive' : 'exclusive',
        ]);
    }

    /**
     * Find a product by metadata value
     */
    private static function findProductByMetadata(string $key, string $value): ?array
    {
        $response = self::stripeRequest('GET', '/products', [
            'active' => 'true',
            'limit' => 100,
        ]);

        foreach ($response['data'] ?? [] as $product) {
            if (($product['metadata'][$key] ?? null) === $value) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Find a price by lookup key
     */
    private static function findPriceByLookupKey(string $lookupKey): ?array
    {
        $response = self::stripeRequest('GET', '/prices', [
            'lookup_keys' => [$lookupKey],
            'limit' => 1,
        ]);

        return $response['data'][0] ?? null;
    }

    /**
     * Make a request to the Stripe API
     */
    private static function stripeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = self::$apiUrl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $ch = curl_init();

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(self::flattenArray($data)));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Curl error: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown Stripe error';
            throw new Exception("Stripe API error: {$errorMessage}");
        }

        return $decoded;
    }

    /**
     * Flatten nested array for Stripe API format
     * e.g., ['recurring' => ['interval' => 'month']] becomes ['recurring[interval]' => 'month']
     */
    private static function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result = array_merge($result, self::flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Get current Stripe configuration status
     */
    public static function status(): void
    {
        // Require admin authentication
        $user = AuthMiddleware::authenticate();
        if (!$user || $user['role'] !== 'admin') {
            Response::error('Admin access required', 403);
            return;
        }

        if (empty(STRIPE_SECRET_KEY)) {
            Response::error('Stripe API key not configured', 500);
            return;
        }

        $status = [
            'products' => [],
            'prices' => [],
            'missing' => [],
        ];

        $plansToCheck = ['creator', 'pro', 'team', 'agency'];

        foreach ($plansToCheck as $planKey) {
            $product = self::findProductByMetadata('plan_key', $planKey);

            if ($product) {
                $status['products'][$planKey] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'active' => $product['active'],
                ];

                // Check prices
                $monthlyPrice = self::findPriceByLookupKey("{$planKey}_monthly");
                $annualPrice = self::findPriceByLookupKey("{$planKey}_annual");
                $seatPrice = self::findPriceByLookupKey("{$planKey}_seat");

                $status['prices'][$planKey] = [
                    'monthly' => $monthlyPrice ? ['id' => $monthlyPrice['id'], 'amount' => $monthlyPrice['unit_amount']] : null,
                    'annual' => $annualPrice ? ['id' => $annualPrice['id'], 'amount' => $annualPrice['unit_amount']] : null,
                    'seat' => $seatPrice ? ['id' => $seatPrice['id'], 'amount' => $seatPrice['unit_amount']] : null,
                ];
            } else {
                $status['missing'][] = $planKey;
            }
        }

        // Check credit packages
        foreach (array_keys(CREDIT_PACKAGES) as $packageKey) {
            $product = self::findProductByMetadata('package_key', $packageKey);

            if ($product) {
                $price = self::findPriceByLookupKey("credits_{$packageKey}");
                $status['products']["credits_{$packageKey}"] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                ];
                $status['prices']["credits_{$packageKey}"] = $price ? [
                    'id' => $price['id'],
                    'amount' => $price['unit_amount'],
                ] : null;
            } else {
                $status['missing'][] = "credits_{$packageKey}";
            }
        }

        Response::success($status);
    }
}
