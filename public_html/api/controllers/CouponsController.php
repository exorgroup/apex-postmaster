<?php

declare(strict_types=1);

require_once __DIR__ . '/CreditsController.php';

class CouponsController
{
    public static function index(): void
    {
        AuthMiddleware::requireAdmin();

        $coupons = Database::fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = c.id) as redemption_count
             FROM coupons c
             ORDER BY c.created_at DESC"
        );

        $formatted = array_map(function ($coupon) {
            return [
                'id' => (int)$coupon['id'],
                'code' => $coupon['code'],
                'type' => $coupon['type'],
                'valueCents' => (int)$coupon['value_cents'],
                'maxRedemptions' => $coupon['max_redemptions'] ? (int)$coupon['max_redemptions'] : null,
                'redemptionCount' => (int)$coupon['redemption_count'],
                'expiresAt' => $coupon['expires_at'],
                'isActive' => (bool)$coupon['is_active'],
                'createdAt' => $coupon['created_at'],
            ];
        }, $coupons);

        Response::success($formatted);
    }

    public static function create(): void
    {
        AuthMiddleware::requireAdmin();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('code', 'type', 'valueCents')
            ->in('type', ['credits', 'discount_percent', 'discount_fixed', 'trial_days'])
            ->integer('valueCents')
            ->min('valueCents', 1)
            ->unique('code', 'coupons');

        $validator->validate();

        $couponId = Database::insert('coupons', [
            'code' => strtoupper(trim($input['code'])),
            'type' => $input['type'],
            'value_cents' => (int)$input['valueCents'],
            'max_redemptions' => $input['maxRedemptions'] ?? null,
            'expires_at' => $input['expiresAt'] ?? null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $coupon = Database::fetch("SELECT * FROM coupons WHERE id = ?", [$couponId]);

        Response::created([
            'id' => (int)$coupon['id'],
            'code' => $coupon['code'],
            'type' => $coupon['type'],
            'valueCents' => (int)$coupon['value_cents'],
        ]);
    }

    public static function update(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $coupon = Database::fetch("SELECT id FROM coupons WHERE id = ?", [(int)$id]);

        if (!$coupon) {
            Response::notFound('Coupon not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $updateData = [];

        if (isset($input['isActive'])) {
            $updateData['is_active'] = $input['isActive'] ? 1 : 0;
        }

        if (isset($input['maxRedemptions'])) {
            $updateData['max_redemptions'] = $input['maxRedemptions'] ?: null;
        }

        if (isset($input['expiresAt'])) {
            $updateData['expires_at'] = $input['expiresAt'] ?: null;
        }

        if (empty($updateData)) {
            Response::error('No valid fields to update', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        Database::update('coupons', $updateData, 'id = ?', [(int)$id]);

        Response::success(null, 'Coupon updated');
    }

    public static function delete(string $id): void
    {
        AuthMiddleware::requireAdmin();

        $coupon = Database::fetch("SELECT id FROM coupons WHERE id = ?", [(int)$id]);

        if (!$coupon) {
            Response::notFound('Coupon not found');
        }

        Database::delete('coupons', 'id = ?', [(int)$id]);

        Response::success(null, 'Coupon deleted');
    }

    public static function redeem(): void
    {
        $user = AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)->required('code');
        $validator->validate();

        $code = strtoupper(trim($input['code']));

        $coupon = Database::fetch(
            "SELECT * FROM coupons WHERE code = ? AND is_active = 1",
            [$code]
        );

        if (!$coupon) {
            Response::error('Invalid coupon code', 400);
        }

        // Check expiration
        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
            Response::error('This coupon has expired', 400);
        }

        // Check max redemptions
        if ($coupon['max_redemptions']) {
            $redemptionCount = Database::fetch(
                "SELECT COUNT(*) as cnt FROM coupon_redemptions WHERE coupon_id = ?",
                [$coupon['id']]
            )['cnt'] ?? 0;

            if ($redemptionCount >= $coupon['max_redemptions']) {
                Response::error('This coupon has reached its maximum redemptions', 400);
            }
        }

        // Check if user already redeemed
        $alreadyRedeemed = Database::fetch(
            "SELECT id FROM coupon_redemptions WHERE coupon_id = ? AND user_id = ?",
            [$coupon['id'], $user['id']]
        );

        if ($alreadyRedeemed) {
            Response::error('You have already redeemed this coupon', 400);
        }

        // Apply coupon based on type
        switch ($coupon['type']) {
            case 'credits':
                CreditsController::addCredits(
                    $user['id'],
                    (int)$coupon['value_cents'],
                    'coupon',
                    "Coupon: {$coupon['code']}"
                );
                $message = 'Credits added to your account!';
                break;

            case 'discount_percent':
            case 'discount_fixed':
                // Store for use during checkout
                Database::insert('user_discounts', [
                    'user_id' => $user['id'],
                    'coupon_id' => $coupon['id'],
                    'discount_type' => $coupon['type'],
                    'discount_value' => $coupon['value_cents'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $message = 'Discount applied! It will be used on your next purchase.';
                break;

            case 'trial_days':
                // Extend trial period
                $currentTrialEnd = $user['trial_ends_at'] ?? date('Y-m-d H:i:s');
                $newTrialEnd = date('Y-m-d H:i:s', strtotime($currentTrialEnd) + ($coupon['value_cents'] * 86400));

                Database::update('users', [
                    'trial_ends_at' => $newTrialEnd,
                ], 'id = ?', [$user['id']]);

                $message = "Trial extended by {$coupon['value_cents']} days!";
                break;

            default:
                Response::error('Unknown coupon type', 400);
        }

        // Record redemption
        Database::insert('coupon_redemptions', [
            'coupon_id' => $coupon['id'],
            'user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::success([
            'type' => $coupon['type'],
            'valueCents' => (int)$coupon['value_cents'],
        ], $message);
    }
}
