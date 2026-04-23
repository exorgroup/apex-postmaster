<?php

declare(strict_types=1);

require_once __DIR__ . '/CreditsController.php';

class BillingController
{
    public static function subscription(): void
    {
        $user = AuthMiddleware::authenticate();

        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status IN ('active', 'trialing', 'past_due') ORDER BY created_at DESC LIMIT 1",
            [$user['id']]
        );

        if (!$subscription) {
            Response::success([
                'plan' => $user['plan'],
                'status' => 'none',
                'currentPeriodEnd' => null,
            ]);
        }

        Response::success([
            'plan' => $user['plan'],
            'status' => $subscription['status'],
            'stripeSubscriptionId' => $subscription['stripe_subscription_id'],
            'currentPeriodEnd' => $subscription['current_period_end'],
            'cancelAtPeriodEnd' => (bool)$subscription['cancel_at_period_end'],
        ]);
    }

    public static function createCheckout(): void
    {
        $user = AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('plan')
            ->in('plan', ['creator', 'pro', 'team', 'agency']);

        $validator->validate();

        $plan = $input['plan'];
        $billingPeriod = $input['billing_period'] ?? 'monthly';
        $seats = (int)($input['seats'] ?? 1);
        $couponCode = $input['coupon_code'] ?? null;

        // Validate billing period
        if (!in_array($billingPeriod, ['monthly', 'annual'])) {
            Response::error('Invalid billing period', 400);
        }

        // Get plan limits
        $planLimits = PLAN_LIMITS[$plan] ?? null;
        if (!$planLimits) {
            Response::error('Invalid plan', 400);
        }

        // Validate seat count
        $includedSeats = $planLimits['included_seats'];
        $maxSeats = $planLimits['max_seats'];
        if ($seats < 1 || $seats > $maxSeats) {
            Response::error("Seat count must be between 1 and {$maxSeats}", 400);
        }

        // Get price IDs from lookup keys
        $priceKey = "{$plan}_" . ($billingPeriod === 'annual' ? 'annual' : 'monthly');
        $seatPriceKey = "{$plan}_seat";

        try {
            // Find prices using lookup keys
            $mainPrice = self::findStripePrice($priceKey);
            if (!$mainPrice) {
                Response::error('Plan pricing not configured. Run stripe/sync first.', 500);
            }

            $stripe = new StripeClient();

            // Get or create Stripe customer
            $customerId = self::getOrCreateStripeCustomer($user);

            // Build line items
            $lineItems = [[
                'price' => $mainPrice['id'],
                'quantity' => 1,
            ]];

            // Add additional seats if needed
            $additionalSeats = max(0, $seats - $includedSeats);
            if ($additionalSeats > 0 && $planLimits['seat_price'] > 0) {
                $seatPrice = self::findStripePrice($seatPriceKey);
                if ($seatPrice) {
                    $lineItems[] = [
                        'price' => $seatPrice['id'],
                        'quantity' => $additionalSeats,
                    ];
                }
            }

            // Build checkout session params
            $sessionParams = [
                'mode' => 'subscription',
                'customer' => $customerId,
                'line_items' => $lineItems,
                'success_url' => APP_URL . '/app/settings/billing?success=true',
                'cancel_url' => APP_URL . '/app/settings/billing?cancelled=true',
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => $user['id'],
                        'plan' => $plan,
                        'seats' => $seats,
                    ],
                ],
            ];

            // Enable Stripe Tax if configured
            if (STRIPE_TAX_ENABLED) {
                $sessionParams['automatic_tax'] = ['enabled' => 'true'];
                $sessionParams['tax_id_collection'] = ['enabled' => 'true'];
                // Customer update needed for automatic tax to calculate properly
                $sessionParams['customer_update'] = [
                    'address' => 'auto',
                    'name' => 'auto',
                ];
            }

            // Add coupon if provided
            if ($couponCode) {
                // Validate coupon
                $coupon = Database::fetch(
                    "SELECT * FROM coupons WHERE code = ? AND active = 1 AND (expires_at IS NULL OR expires_at > NOW())",
                    [$couponCode]
                );
                if ($coupon) {
                    // Check usage limits
                    if ($coupon['max_uses'] > 0) {
                        if ($coupon['times_used'] >= $coupon['max_uses']) {
                            Response::error('Coupon has reached its usage limit', 400);
                        }
                    }
                    // Apply Stripe coupon/promotion code if configured
                    if (!empty($coupon['stripe_coupon_id'])) {
                        $sessionParams['discounts'] = [['coupon' => $coupon['stripe_coupon_id']]];
                    }
                }
            }

            $session = $stripe->createCheckoutSession($sessionParams);

            Response::success([
                'checkoutUrl' => $session['url'],
                'sessionId' => $session['id'],
            ]);
        } catch (Exception $e) {
            Response::error('Failed to create checkout: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Find a Stripe price by lookup key
     */
    private static function findStripePrice(string $lookupKey): ?array
    {
        // Stripe expects lookup_keys[]=value format
        $url = 'https://api.stripe.com/v1/prices?lookup_keys[]=' . urlencode($lookupKey) . '&limit=1';

        $headers = [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);

        return $result['data'][0] ?? null;
    }

    /**
     * Sync subscription status from Stripe (for when webhooks don't work locally)
     */
    public static function syncSubscription(): void
    {
        $authUser = AuthMiddleware::authenticate();

        // Fetch fresh user data from database (JWT might have stale stripe_customer_id)
        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        if (!$user) {
            Response::error('User not found', 404);
        }

        $customerId = $user['stripe_customer_id'] ?? null;

        if (!$customerId) {
            Response::error('No Stripe customer found. Please complete a checkout first.', 400);
        }

        try {
            // Get active subscriptions for this customer from Stripe
            $url = 'https://api.stripe.com/v1/subscriptions?' . http_build_query([
                'customer' => $customerId,
                'status' => 'active',
                'limit' => 1,
            ]);

            $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY];
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'ignore_errors' => true,
                ],
            ]);

            $response = file_get_contents($url, false, $context);
            $result = json_decode($response, true);

            if (empty($result['data'])) {
                Response::error('No active subscription found in Stripe', 404);
            }

            $subscription = $result['data'][0];
            $metadata = $subscription['metadata'] ?? [];

            $plan = $metadata['plan'] ?? 'creator';
            $seats = (int)($metadata['seats'] ?? 1);
            $status = $subscription['status'];

            // Update or create subscription record
            $existing = Database::fetch(
                "SELECT id FROM subscriptions WHERE stripe_subscription_id = ?",
                [$subscription['id']]
            );

            $subscriptionData = [
                'user_id' => $user['id'],
                'stripe_subscription_id' => $subscription['id'],
                'stripe_price_id' => $subscription['items']['data'][0]['price']['id'] ?? null,
                'plan' => $plan,
                'seats' => $seats,
                'status' => $status,
                'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
                'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end']),
                'cancel_at_period_end' => $subscription['cancel_at_period_end'] ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                Database::update('subscriptions', $subscriptionData, 'id = ?', [$existing['id']]);
            } else {
                $subscriptionData['created_at'] = date('Y-m-d H:i:s');
                Database::insert('subscriptions', $subscriptionData);
            }

            // Update user plan
            Database::update('users', [
                'plan' => $plan,
                'seats' => $seats,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$user['id']]);

            Response::success([
                'plan' => $plan,
                'seats' => $seats,
                'status' => $status,
                'subscription_id' => $subscription['id'],
            ], 'Subscription synced successfully');

        } catch (Exception $e) {
            Response::error('Failed to sync subscription: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get current subscription details
     */
    public static function getSubscription(): void
    {
        $authUser = AuthMiddleware::authenticate();

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        if (!$user) {
            Response::error('User not found', 404);
        }

        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status IN ('active', 'trialing', 'past_due') ORDER BY created_at DESC LIMIT 1",
            [$user['id']]
        );

        // Get team member count
        $teamMemberCount = Database::fetch(
            "SELECT COUNT(*) as cnt FROM team_members WHERE team_owner_id = ? AND status = 'active'",
            [$user['id']]
        )['cnt'] ?? 0;

        // Add owner as a seat
        $activeSeats = (int)$teamMemberCount + 1;

        $planLimits = PLAN_LIMITS[$user['plan']] ?? PLAN_LIMITS['free'];

        Response::success([
            'plan' => $user['plan'],
            'seats' => (int)($user['seats'] ?? 1),
            'activeSeats' => $activeSeats,
            'includedSeats' => $planLimits['included_seats'],
            'maxSeats' => $planLimits['max_seats'],
            'seatPrice' => $planLimits['seat_price'],
            'subscription' => $subscription ? [
                'id' => $subscription['id'],
                'stripeSubscriptionId' => $subscription['stripe_subscription_id'],
                'status' => $subscription['status'],
                'currentPeriodStart' => $subscription['current_period_start'],
                'currentPeriodEnd' => $subscription['current_period_end'],
                'cancelAtPeriodEnd' => (bool)$subscription['cancel_at_period_end'],
                'cancelledAt' => $subscription['cancelled_at'] ?? null,
            ] : null,
        ]);
    }

    /**
     * Add seats to subscription
     */
    public static function addSeats(): void
    {
        $authUser = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $seatsToAdd = (int)($input['seats'] ?? 1);
        if ($seatsToAdd < 1) {
            Response::error('Must add at least 1 seat', 400);
        }

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1",
            [$user['id']]
        );

        if (!$subscription) {
            Response::error('No active subscription found', 400);
        }

        $planLimits = PLAN_LIMITS[$user['plan']] ?? null;
        if (!$planLimits) {
            Response::error('Invalid plan', 400);
        }

        $currentSeats = (int)($user['seats'] ?? 1);
        $newSeats = $currentSeats + $seatsToAdd;

        if ($newSeats > $planLimits['max_seats']) {
            Response::error("Cannot exceed {$planLimits['max_seats']} seats for this plan", 400);
        }

        // Calculate prorated charge
        $periodEnd = strtotime($subscription['current_period_end'] ?? 'now');
        $periodStart = strtotime($subscription['current_period_start'] ?? 'now');
        $now = time();
        $totalDays = ($periodEnd - $periodStart) / 86400;
        $remainingDays = max(0, ($periodEnd - $now) / 86400);
        // Avoid division by zero - if totalDays is 0 or negative, assume full charge
        $prorationFactor = ($totalDays > 0) ? ($remainingDays / $totalDays) : 1;

        // Only charge for seats beyond included
        $includedSeats = $planLimits['included_seats'];
        $currentPaidSeats = max(0, $currentSeats - $includedSeats);
        $newPaidSeats = max(0, $newSeats - $includedSeats);
        $additionalPaidSeats = $newPaidSeats - $currentPaidSeats;

        $seatPrice = $planLimits['seat_price']; // in cents
        $proratedAmount = round($additionalPaidSeats * $seatPrice * $prorationFactor);

        // Update Stripe subscription quantity
        try {
            $stripeSubscriptionId = $subscription['stripe_subscription_id'];
            $seatPriceKey = "{$user['plan']}_seat";
            $seatPrice = self::findStripePrice($seatPriceKey);

            if ($seatPrice && $stripeSubscriptionId) {
                // Get subscription items
                $url = "https://api.stripe.com/v1/subscriptions/{$stripeSubscriptionId}";
                $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY];
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => implode("\r\n", $headers),
                        'ignore_errors' => true,
                    ],
                ]);
                $response = file_get_contents($url, false, $context);
                $stripeSubscription = json_decode($response, true);

                // Find seat item or add new one
                $seatItemId = null;
                foreach ($stripeSubscription['items']['data'] ?? [] as $item) {
                    if ($item['price']['id'] === $seatPrice['id']) {
                        $seatItemId = $item['id'];
                        break;
                    }
                }

                if ($seatItemId) {
                    // Update existing seat item quantity
                    $updateUrl = "https://api.stripe.com/v1/subscription_items/{$seatItemId}";
                    $updateData = http_build_query([
                        'quantity' => $newPaidSeats,
                        'proration_behavior' => 'create_prorations',
                    ]);
                } else {
                    // Add seat item to subscription
                    $updateUrl = "https://api.stripe.com/v1/subscriptions/{$stripeSubscriptionId}";
                    $updateData = http_build_query([
                        'items' => [
                            ['price' => $seatPrice['id'], 'quantity' => $newPaidSeats]
                        ],
                        'proration_behavior' => 'create_prorations',
                    ]);
                }

                $updateContext = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", array_merge($headers, ['Content-Type: application/x-www-form-urlencoded'])),
                        'content' => $updateData,
                        'ignore_errors' => true,
                    ],
                ]);
                file_get_contents($updateUrl, false, $updateContext);
            }
        } catch (Exception $e) {
            // Log error but continue - local DB is source of truth
            error_log("Stripe seat update failed: " . $e->getMessage());
        }

        // Update local database
        Database::update('users', [
            'seats' => $newSeats,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        Database::update('subscriptions', [
            'seats' => $newSeats,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$subscription['id']]);

        Response::success([
            'seats' => $newSeats,
            'seatsAdded' => $seatsToAdd,
            'proratedCharge' => $proratedAmount,
        ], 'Seats added successfully');
    }

    /**
     * Remove seats from subscription (effective at end of period)
     */
    public static function removeSeats(): void
    {
        $authUser = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $seatsToRemove = (int)($input['seats'] ?? 1);
        if ($seatsToRemove < 1) {
            Response::error('Must remove at least 1 seat', 400);
        }

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1",
            [$user['id']]
        );

        if (!$subscription) {
            Response::error('No active subscription found', 400);
        }

        $planLimits = PLAN_LIMITS[$user['plan']] ?? null;
        if (!$planLimits) {
            Response::error('Invalid plan', 400);
        }

        $currentSeats = (int)($user['seats'] ?? 1);
        $newSeats = $currentSeats - $seatsToRemove;

        // Can't go below 1 seat
        if ($newSeats < 1) {
            Response::error('Cannot have less than 1 seat', 400);
        }

        // Check active team members
        $teamMemberCount = Database::fetch(
            "SELECT COUNT(*) as cnt FROM team_members WHERE team_owner_id = ? AND status = 'active'",
            [$user['id']]
        )['cnt'] ?? 0;

        $activeSeats = (int)$teamMemberCount + 1; // +1 for owner

        if ($newSeats < $activeSeats) {
            Response::error("Cannot reduce below {$activeSeats} seats (you have {$teamMemberCount} active team members)", 400);
        }

        // Schedule seat reduction for end of period
        Database::update('subscriptions', [
            'seats_scheduled' => $newSeats,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$subscription['id']]);

        Response::success([
            'currentSeats' => $currentSeats,
            'scheduledSeats' => $newSeats,
            'effectiveDate' => $subscription['current_period_end'],
        ], 'Seat reduction scheduled for end of billing period');
    }

    /**
     * Upgrade plan
     */
    public static function upgradePlan(): void
    {
        $authUser = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $newPlan = $input['plan'] ?? null;
        if (!$newPlan || !isset(PLAN_LIMITS[$newPlan])) {
            Response::error('Invalid plan', 400);
        }

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1",
            [$user['id']]
        );

        if (!$subscription) {
            Response::error('No active subscription found', 400);
        }

        $currentPlan = $user['plan'];
        $planOrder = ['free', 'creator', 'pro', 'team', 'agency'];
        $currentIndex = array_search($currentPlan, $planOrder);
        $newIndex = array_search($newPlan, $planOrder);

        if ($newIndex <= $currentIndex) {
            Response::error('Can only upgrade to a higher plan. Use downgrade for lower plans.', 400);
        }

        $newPlanLimits = PLAN_LIMITS[$newPlan];
        $currentSeats = (int)($user['seats'] ?? 1);

        // Check if current seats exceed new plan max
        if ($currentSeats > $newPlanLimits['max_seats']) {
            Response::error("Current seat count ({$currentSeats}) exceeds maximum for {$newPlan} plan ({$newPlanLimits['max_seats']})", 400);
        }

        // Calculate prorated amount
        $periodEnd = strtotime($subscription['current_period_end'] ?? 'now');
        $periodStart = strtotime($subscription['current_period_start'] ?? 'now');
        $now = time();
        $totalDays = ($periodEnd - $periodStart) / 86400;
        $remainingDays = max(0, ($periodEnd - $now) / 86400);
        // Avoid division by zero - if totalDays is 0 or negative, assume full charge
        $prorationFactor = ($totalDays > 0) ? ($remainingDays / $totalDays) : 1;

        $currentPlanLimits = PLAN_LIMITS[$currentPlan];
        $priceDifference = $newPlanLimits['price_monthly'] - $currentPlanLimits['price_monthly'];
        $proratedAmount = round($priceDifference * $prorationFactor);

        // Update Stripe subscription
        try {
            $stripeSubscriptionId = $subscription['stripe_subscription_id'];
            if ($stripeSubscriptionId) {
                // Get new price
                $billingPeriod = strpos($subscription['stripe_price_id'] ?? '', 'annual') !== false ? 'annual' : 'monthly';
                $newPriceKey = "{$newPlan}_{$billingPeriod}";
                $newPrice = self::findStripePrice($newPriceKey);

                if ($newPrice) {
                    // Get current subscription to find main item
                    $url = "https://api.stripe.com/v1/subscriptions/{$stripeSubscriptionId}";
                    $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY];
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'header' => implode("\r\n", $headers),
                            'ignore_errors' => true,
                        ],
                    ]);
                    $response = file_get_contents($url, false, $context);
                    $stripeSubscription = json_decode($response, true);

                    // Find main plan item (not seat item)
                    $mainItemId = null;
                    foreach ($stripeSubscription['items']['data'] ?? [] as $item) {
                        if (strpos($item['price']['lookup_key'] ?? '', '_seat') === false) {
                            $mainItemId = $item['id'];
                            break;
                        }
                    }

                    if ($mainItemId) {
                        // Update subscription with new price
                        // Use 'always_invoice' to immediately charge/credit the proration difference
                        // This keeps proration separate from the next recurring charge
                        $updateUrl = "https://api.stripe.com/v1/subscriptions/{$stripeSubscriptionId}";
                        $updateData = http_build_query([
                            'items' => [
                                [
                                    'id' => $mainItemId,
                                    'price' => $newPrice['id'],
                                ]
                            ],
                            'proration_behavior' => 'always_invoice',
                        ]);

                        $updateContext = stream_context_create([
                            'http' => [
                                'method' => 'POST',
                                'header' => implode("\r\n", array_merge($headers, ['Content-Type: application/x-www-form-urlencoded'])),
                                'content' => $updateData,
                                'ignore_errors' => true,
                            ],
                        ]);
                        file_get_contents($updateUrl, false, $updateContext);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Stripe plan upgrade failed: " . $e->getMessage());
        }

        // Update Stripe subscription metadata
        try {
            if ($stripeSubscriptionId) {
                $metadataUrl = "https://api.stripe.com/v1/subscriptions/{$stripeSubscriptionId}";
                $metadataData = http_build_query([
                    'metadata' => [
                        'plan' => $newPlan,
                        'seats' => $currentSeats,
                    ],
                ]);
                $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY, 'Content-Type: application/x-www-form-urlencoded'];
                $metadataContext = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", $headers),
                        'content' => $metadataData,
                        'ignore_errors' => true,
                    ],
                ]);
                file_get_contents($metadataUrl, false, $metadataContext);
            }
        } catch (Exception $e) {
            error_log("Stripe metadata update failed: " . $e->getMessage());
        }

        // Update local database
        Database::update('users', [
            'plan' => $newPlan,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        Database::update('subscriptions', [
            'plan' => $newPlan,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$subscription['id']]);

        Response::success([
            'previousPlan' => $currentPlan,
            'newPlan' => $newPlan,
            'proratedCharge' => $proratedAmount,
        ], 'Plan upgraded successfully');
    }

    /**
     * Downgrade plan (effective at end of period)
     */
    public static function downgradePlan(): void
    {
        $authUser = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $newPlan = $input['plan'] ?? null;
        if (!$newPlan || !isset(PLAN_LIMITS[$newPlan])) {
            Response::error('Invalid plan', 400);
        }

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1",
            [$user['id']]
        );

        if (!$subscription) {
            Response::error('No active subscription found', 400);
        }

        $currentPlan = $user['plan'];
        $planOrder = ['free', 'creator', 'pro', 'team', 'agency'];
        $currentIndex = array_search($currentPlan, $planOrder);
        $newIndex = array_search($newPlan, $planOrder);

        if ($newIndex >= $currentIndex) {
            Response::error('Can only downgrade to a lower plan. Use upgrade for higher plans.', 400);
        }

        $newPlanLimits = PLAN_LIMITS[$newPlan];
        $currentSeats = (int)($user['seats'] ?? 1);

        // Check if current seats exceed new plan max
        if ($currentSeats > $newPlanLimits['max_seats']) {
            Response::error("Current seat count ({$currentSeats}) exceeds maximum for {$newPlan} plan ({$newPlanLimits['max_seats']}). Please reduce seats first.", 400);
        }

        // Schedule downgrade for end of period
        Database::update('subscriptions', [
            'plan_scheduled' => $newPlan,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$subscription['id']]);

        Response::success([
            'currentPlan' => $currentPlan,
            'scheduledPlan' => $newPlan,
            'effectiveDate' => $subscription['current_period_end'],
        ], 'Plan downgrade scheduled for end of billing period');
    }

    /**
     * Cancel subscription (access continues until end of period)
     */
    public static function cancelSubscription(): void
    {
        $authUser = AuthMiddleware::authenticate();

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1",
            [$user['id']]
        );

        if (!$subscription) {
            Response::error('No active subscription found', 400);
        }

        // Cancel in Stripe (at period end)
        try {
            $stripeSubscriptionId = $subscription['stripe_subscription_id'];
            if ($stripeSubscriptionId) {
                $url = "https://api.stripe.com/v1/subscriptions/{$stripeSubscriptionId}";
                $data = http_build_query(['cancel_at_period_end' => 'true']);
                $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY, 'Content-Type: application/x-www-form-urlencoded'];
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", $headers),
                        'content' => $data,
                        'ignore_errors' => true,
                    ],
                ]);
                file_get_contents($url, false, $context);
            }
        } catch (Exception $e) {
            error_log("Stripe cancellation failed: " . $e->getMessage());
        }

        // Update local database
        Database::update('subscriptions', [
            'cancel_at_period_end' => 1,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$subscription['id']]);

        Response::success([
            'cancelledAt' => date('Y-m-d H:i:s'),
            'accessUntil' => $subscription['current_period_end'],
        ], 'Subscription cancelled. You will have access until ' . date('F j, Y', strtotime($subscription['current_period_end'])));
    }

    /**
     * Reactivate a cancelled subscription (before period ends)
     */
    public static function reactivateSubscription(): void
    {
        $authUser = AuthMiddleware::authenticate();

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        $subscription = Database::fetch(
            "SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND cancel_at_period_end = 1 LIMIT 1",
            [$user['id']]
        );

        if (!$subscription) {
            Response::error('No cancelled subscription found to reactivate', 400);
        }

        // Reactivate in Stripe
        try {
            $stripeSubscriptionId = $subscription['stripe_subscription_id'];
            if ($stripeSubscriptionId) {
                $url = "https://api.stripe.com/v1/subscriptions/{$stripeSubscriptionId}";
                $data = http_build_query(['cancel_at_period_end' => 'false']);
                $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY, 'Content-Type: application/x-www-form-urlencoded'];
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", $headers),
                        'content' => $data,
                        'ignore_errors' => true,
                    ],
                ]);
                file_get_contents($url, false, $context);
            }
        } catch (Exception $e) {
            error_log("Stripe reactivation failed: " . $e->getMessage());
        }

        // Update local database
        Database::update('subscriptions', [
            'cancel_at_period_end' => 0,
            'cancelled_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$subscription['id']]);

        Response::success([
            'reactivated' => true,
        ], 'Subscription reactivated successfully');
    }

    /**
     * Process scheduled subscription changes (run via cron)
     * Call this daily to process end-of-period changes
     */
    public static function processScheduledChanges(): void
    {
        // This should be called by a cron job, not directly by users
        // For security, check for a secret key or admin auth
        $authUser = AuthMiddleware::authenticate();
        if (!$authUser || ($authUser['role'] ?? '') !== 'admin') {
            Response::error('Admin access required', 403);
        }

        $now = date('Y-m-d H:i:s');
        $processed = [
            'cancellations' => 0,
            'downgrades' => 0,
            'seat_reductions' => 0,
        ];

        // Process cancellations (subscriptions that have ended)
        $cancelledSubscriptions = Database::fetchAll(
            "SELECT s.*, u.id as user_id FROM subscriptions s
             JOIN users u ON s.user_id = u.id
             WHERE s.cancel_at_period_end = 1
             AND s.current_period_end < ?
             AND s.status = 'active'",
            [$now]
        );

        foreach ($cancelledSubscriptions as $sub) {
            Database::update('subscriptions', [
                'status' => 'cancelled',
                'updated_at' => $now,
            ], 'id = ?', [$sub['id']]);

            Database::update('users', [
                'plan' => 'free',
                'seats' => 1,
                'updated_at' => $now,
            ], 'id = ?', [$sub['user_id']]);

            $processed['cancellations']++;
        }

        // Process scheduled downgrades
        $downgrades = Database::fetchAll(
            "SELECT s.*, u.id as user_id FROM subscriptions s
             JOIN users u ON s.user_id = u.id
             WHERE s.plan_scheduled IS NOT NULL
             AND s.current_period_end < ?
             AND s.status = 'active'",
            [$now]
        );

        foreach ($downgrades as $sub) {
            $newPlan = $sub['plan_scheduled'];

            Database::update('subscriptions', [
                'plan' => $newPlan,
                'plan_scheduled' => null,
                'updated_at' => $now,
            ], 'id = ?', [$sub['id']]);

            Database::update('users', [
                'plan' => $newPlan,
                'updated_at' => $now,
            ], 'id = ?', [$sub['user_id']]);

            $processed['downgrades']++;
        }

        // Process scheduled seat reductions
        $seatReductions = Database::fetchAll(
            "SELECT s.*, u.id as user_id FROM subscriptions s
             JOIN users u ON s.user_id = u.id
             WHERE s.seats_scheduled IS NOT NULL
             AND s.current_period_end < ?
             AND s.status = 'active'",
            [$now]
        );

        foreach ($seatReductions as $sub) {
            $newSeats = $sub['seats_scheduled'];

            Database::update('subscriptions', [
                'seats' => $newSeats,
                'seats_scheduled' => null,
                'updated_at' => $now,
            ], 'id = ?', [$sub['id']]);

            Database::update('users', [
                'seats' => $newSeats,
                'updated_at' => $now,
            ], 'id = ?', [$sub['user_id']]);

            $processed['seat_reductions']++;
        }

        Response::success($processed, 'Scheduled changes processed');
    }

    public static function createPortal(): void
    {
        $authUser = AuthMiddleware::authenticate();

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$authUser['id']]);
        $customerId = $user['stripe_customer_id'] ?? null;

        if (!$customerId) {
            Response::error('No billing account found', 400);
        }

        $stripe = new StripeClient();

        try {
            $session = $stripe->createPortalSession(
                $customerId,
                APP_URL . '/app/settings/billing'
            );

            Response::success([
                'portalUrl' => $session['url'],
            ]);
        } catch (Exception $e) {
            Response::error('Failed to create portal session: ' . $e->getMessage(), 500);
        }
    }

    public static function webhook(): void
    {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        $stripe = new StripeClient();

        try {
            $event = $stripe->constructWebhookEvent($payload, $signature);
        } catch (Exception $e) {
            Response::error('Webhook signature verification failed', 400);
        }

        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                self::handleCheckoutComplete($object);
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                self::handleSubscriptionUpdate($object);
                break;

            case 'customer.subscription.deleted':
                self::handleSubscriptionDeleted($object);
                break;

            case 'invoice.payment_succeeded':
                self::handlePaymentSucceeded($object);
                break;

            case 'invoice.payment_failed':
                self::handlePaymentFailed($object);
                break;
        }

        Response::success(null, 'Webhook processed');
    }

    private static function handleCheckoutComplete(array $session): void
    {
        $metadata = $session['metadata'] ?? [];

        // Handle credit top-up
        if (($metadata['type'] ?? '') === 'credit_topup') {
            $userId = (int)$metadata['user_id'];
            $amountCents = (int)$metadata['amount_cents'];

            CreditsController::addCredits(
                $userId,
                $amountCents,
                'topup',
                'Credit top-up via Stripe'
            );
        }

        // Handle subscription
        if (!empty($session['subscription'])) {
            // Subscription will be handled by subscription.created event
        }
    }

    private static function handleSubscriptionUpdate(array $subscription): void
    {
        $metadata = $subscription['metadata'] ?? [];
        $userId = (int)($metadata['user_id'] ?? 0);

        if (!$userId) {
            // Try to find user by Stripe customer ID
            $user = Database::fetch(
                "SELECT id FROM users WHERE stripe_customer_id = ?",
                [$subscription['customer']]
            );
            $userId = $user['id'] ?? 0;
        }

        if (!$userId) {
            return;
        }

        $plan = $metadata['plan'] ?? 'creator';
        $seats = (int)($metadata['seats'] ?? 1);
        $status = $subscription['status'];

        // Update or create subscription record
        $existing = Database::fetch(
            "SELECT id FROM subscriptions WHERE stripe_subscription_id = ?",
            [$subscription['id']]
        );

        $subscriptionData = [
            'user_id' => $userId,
            'stripe_subscription_id' => $subscription['id'],
            'stripe_price_id' => $subscription['items']['data'][0]['price']['id'] ?? null,
            'plan' => $plan,
            'seats' => $seats,
            'status' => $status,
            'current_period_start' => date('Y-m-d H:i:s', $subscription['current_period_start']),
            'current_period_end' => date('Y-m-d H:i:s', $subscription['current_period_end']),
            'cancel_at_period_end' => $subscription['cancel_at_period_end'] ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Database::update('subscriptions', $subscriptionData, 'id = ?', [$existing['id']]);
        } else {
            $subscriptionData['created_at'] = date('Y-m-d H:i:s');
            Database::insert('subscriptions', $subscriptionData);
        }

        // Update user plan and seats if subscription is active
        if (in_array($status, ['active', 'trialing'])) {
            Database::update('users', [
                'plan' => $plan,
                'seats' => $seats,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$userId]);
        }
    }

    private static function handleSubscriptionDeleted(array $subscription): void
    {
        $userId = null;

        // Find user by subscription
        $sub = Database::fetch(
            "SELECT user_id FROM subscriptions WHERE stripe_subscription_id = ?",
            [$subscription['id']]
        );

        if ($sub) {
            $userId = $sub['user_id'];

            // Update subscription status
            Database::update('subscriptions', [
                'status' => 'canceled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'stripe_subscription_id = ?', [$subscription['id']]);

            // Downgrade user to free
            Database::update('users', [
                'plan' => 'free',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$userId]);
        }
    }

    private static function handlePaymentSucceeded(array $invoice): void
    {
        // Could send confirmation email or add bonus credits
    }

    private static function handlePaymentFailed(array $invoice): void
    {
        // Could send failed payment notification email
        $customerId = $invoice['customer'] ?? '';

        $user = Database::fetch(
            "SELECT id, email FROM users WHERE stripe_customer_id = ?",
            [$customerId]
        );

        if ($user) {
            // Log payment failure
            Database::insert('payment_failures', [
                'user_id' => $user['id'],
                'stripe_invoice_id' => $invoice['id'] ?? '',
                'amount_cents' => $invoice['amount_due'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private static function getOrCreateStripeCustomer(array $user): string
    {
        if (!empty($user['stripe_customer_id'])) {
            return $user['stripe_customer_id'];
        }

        // Create new Stripe customer
        $stripe = new StripeClient();

        $customer = self::createStripeCustomer($user['email'], $user['name']);

        Database::update('users', [
            'stripe_customer_id' => $customer['id'],
        ], 'id = ?', [$user['id']]);

        return $customer['id'];
    }

    private static function createStripeCustomer(string $email, string $name): array
    {
        $url = 'https://api.stripe.com/v1/customers';

        $headers = [
            'Authorization: Bearer ' . STRIPE_SECRET_KEY,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $data = http_build_query([
            'email' => $email,
            'name' => $name,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $data,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $result = json_decode($response, true);

        if (isset($result['error'])) {
            throw new Exception($result['error']['message'] ?? 'Failed to create customer');
        }

        return $result;
    }
}
