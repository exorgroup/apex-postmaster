<?php

declare(strict_types=1);

class ConfigController
{
    public static function plans(): void
    {
        // Format plan limits for frontend consumption
        $plans = [];

        foreach (PLAN_LIMITS as $planName => $limits) {
            $plans[$planName] = [
                'price_monthly' => $limits['price_monthly'],
                'price_annual' => $limits['price_annual'],
                'posts_per_month' => $limits['posts_per_month'],
                'social_accounts' => $limits['social_accounts'],
                'ai_generations' => $limits['ai_generations'],
                'ai_credits_monthly' => $limits['ai_credits_monthly'],
                'analytics_days' => $limits['analytics_days'],
                'streams' => $limits['streams'],
                'included_seats' => $limits['included_seats'],
                'seat_price' => $limits['seat_price'],
                'max_seats' => $limits['max_seats'],
                'bulk_import' => $limits['bulk_import'],
                'recycling' => $limits['recycling'],
                'approval' => $limits['approval'],
            ];
        }

        Response::success([
            'plans' => $plans,
            'annual_billing_enabled' => ANNUAL_BILLING_ENABLED,
            'vat' => [
                'rate' => VAT_RATE_DEFAULT,
                'country' => VAT_COUNTRY,
                'prices_stored_as' => PRICES_STORED_AS,
            ],
            'credit_packages' => CREDIT_PACKAGES,
        ]);
    }
}
