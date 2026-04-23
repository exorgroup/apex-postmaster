<?php

declare(strict_types=1);

// Load environment variables from .env file if it exists
$envFile = dirname(__DIR__, 2) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, '"\'');
    }
}

// Application config
define('APP_NAME', 'APEX PostMaster');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'false') === 'true');
define('APP_URL', $_ENV['APP_URL'] ?? 'https://localhost');
define('API_URL', APP_URL . '/api');

// JWT Config
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'change-this-secret-in-production');
define('JWT_ACCESS_EXPIRY', 900); // 15 minutes
define('JWT_REFRESH_EXPIRY', 604800); // 7 days

// OAuth Credentials
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// Facebook Business App (for connecting pages/Instagram)
define('FACEBOOK_APP_ID', $_ENV['FACEBOOK_APP_ID'] ?? '');
define('FACEBOOK_APP_SECRET', $_ENV['FACEBOOK_APP_SECRET'] ?? '');
define('INSTAGRAM_APP_ID', FACEBOOK_APP_ID);
define('INSTAGRAM_APP_SECRET', FACEBOOK_APP_SECRET);

// Facebook Login App (for user authentication)
// Falls back to Business App if not set separately
define('FACEBOOK_LOGIN_APP_ID', $_ENV['FACEBOOK_LOGIN_APP_ID'] ?? FACEBOOK_APP_ID);
define('FACEBOOK_LOGIN_APP_SECRET', $_ENV['FACEBOOK_LOGIN_APP_SECRET'] ?? FACEBOOK_APP_SECRET);

// Claude API
define('CLAUDE_API_KEY', $_ENV['CLAUDE_API_KEY'] ?? '');
define('CLAUDE_MODEL', 'claude-sonnet-4-20250514');

// Stripe
define('STRIPE_SECRET_KEY', $_ENV['STRIPE_SECRET_KEY'] ?? '');
define('STRIPE_WEBHOOK_SECRET', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');
define('STRIPE_PRICE_PRO', $_ENV['STRIPE_PRICE_PRO'] ?? '');
define('STRIPE_PRICE_TEAM', $_ENV['STRIPE_PRICE_TEAM'] ?? '');

// VAT Configuration
define('VAT_RATE_DEFAULT', (int)($_ENV['VAT_RATE_DEFAULT'] ?? 18));
define('VAT_COUNTRY', $_ENV['VAT_COUNTRY'] ?? 'MT');
define('PRICES_STORED_AS', $_ENV['PRICES_STORED_AS'] ?? 'exclusive');
define('STRIPE_TAX_ENABLED', ($_ENV['STRIPE_TAX_ENABLED'] ?? '1') === '1');
define('VIES_VALIDATION_ENABLED', ($_ENV['VIES_VALIDATION_ENABLED'] ?? '1') === '1');

// Plan limits and pricing (all values can be overridden via .env)
// Prices are in cents, use -1 for unlimited features, 0 for not available
// 1 = feature available (boolean), 0 = not available
define('PLAN_LIMITS', [
    'free' => [
        'price_monthly' => (int)($_ENV['PLAN_FREE_PRICE_MONTHLY'] ?? 0),
        'price_annual' => (int)($_ENV['PLAN_FREE_PRICE_ANNUAL'] ?? 0),
        'posts_per_month' => (int)($_ENV['PLAN_FREE_POSTS_PER_MONTH'] ?? 15),
        'social_accounts' => (int)($_ENV['PLAN_FREE_CONNECTIONS'] ?? 2),
        'ai_generations' => (int)($_ENV['PLAN_FREE_AI_GENERATIONS'] ?? 10),
        'ai_credits_monthly' => (int)($_ENV['PLAN_FREE_AI_CREDITS_MONTHLY'] ?? 0),
        'analytics_days' => (int)($_ENV['PLAN_FREE_ANALYTICS_DAYS'] ?? 7),
        'streams' => (int)($_ENV['PLAN_FREE_STREAMS'] ?? 0),
        'included_seats' => (int)($_ENV['PLAN_FREE_INCLUDED_SEATS'] ?? 1),
        'seat_price' => (int)($_ENV['PLAN_FREE_SEAT_PRICE'] ?? 0),
        'max_seats' => (int)($_ENV['PLAN_FREE_MAX_SEATS'] ?? 1),
        'bulk_import' => (int)($_ENV['PLAN_FREE_BULK_IMPORT'] ?? 0) === 1,
        'recycling' => (int)($_ENV['PLAN_FREE_RECYCLING'] ?? 0) === 1,
        'approval' => (int)($_ENV['PLAN_FREE_APPROVAL'] ?? 0) === 1,
    ],
    'creator' => [
        'price_monthly' => (int)($_ENV['PLAN_CREATOR_PRICE_MONTHLY'] ?? 2000),
        'price_annual' => (int)($_ENV['PLAN_CREATOR_PRICE_ANNUAL'] ?? 19200),
        'posts_per_month' => (int)($_ENV['PLAN_CREATOR_POSTS_PER_MONTH'] ?? -1),
        'social_accounts' => (int)($_ENV['PLAN_CREATOR_CONNECTIONS'] ?? 3),
        'ai_generations' => (int)($_ENV['PLAN_CREATOR_AI_GENERATIONS'] ?? 50),
        'ai_credits_monthly' => (int)($_ENV['PLAN_CREATOR_AI_CREDITS_MONTHLY'] ?? 200),
        'analytics_days' => (int)($_ENV['PLAN_CREATOR_ANALYTICS_DAYS'] ?? 30),
        'streams' => (int)($_ENV['PLAN_CREATOR_STREAMS'] ?? 0),
        'included_seats' => (int)($_ENV['PLAN_CREATOR_INCLUDED_SEATS'] ?? 1),
        'seat_price' => (int)($_ENV['PLAN_CREATOR_SEAT_PRICE'] ?? 800),
        'max_seats' => (int)($_ENV['PLAN_CREATOR_MAX_SEATS'] ?? 5),
        'bulk_import' => (int)($_ENV['PLAN_CREATOR_BULK_IMPORT'] ?? 0) === 1,
        'recycling' => (int)($_ENV['PLAN_CREATOR_RECYCLING'] ?? 0) === 1,
        'approval' => (int)($_ENV['PLAN_CREATOR_APPROVAL'] ?? 0) === 1,
    ],
    'pro' => [
        'price_monthly' => (int)($_ENV['PLAN_PRO_PRICE_MONTHLY'] ?? 3900),
        'price_annual' => (int)($_ENV['PLAN_PRO_PRICE_ANNUAL'] ?? 37400),
        'posts_per_month' => (int)($_ENV['PLAN_PRO_POSTS_PER_MONTH'] ?? -1),
        'social_accounts' => (int)($_ENV['PLAN_PRO_CONNECTIONS'] ?? 8),
        'ai_generations' => (int)($_ENV['PLAN_PRO_AI_GENERATIONS'] ?? 200),
        'ai_credits_monthly' => (int)($_ENV['PLAN_PRO_AI_CREDITS_MONTHLY'] ?? 500),
        'analytics_days' => (int)($_ENV['PLAN_PRO_ANALYTICS_DAYS'] ?? 90),
        'streams' => (int)($_ENV['PLAN_PRO_STREAMS'] ?? 5),
        'included_seats' => (int)($_ENV['PLAN_PRO_INCLUDED_SEATS'] ?? 1),
        'seat_price' => (int)($_ENV['PLAN_PRO_SEAT_PRICE'] ?? 1200),
        'max_seats' => (int)($_ENV['PLAN_PRO_MAX_SEATS'] ?? 10),
        'bulk_import' => (int)($_ENV['PLAN_PRO_BULK_IMPORT'] ?? 1) === 1,
        'recycling' => (int)($_ENV['PLAN_PRO_RECYCLING'] ?? 1) === 1,
        'approval' => (int)($_ENV['PLAN_PRO_APPROVAL'] ?? 0) === 1,
    ],
    'team' => [
        'price_monthly' => (int)($_ENV['PLAN_TEAM_PRICE_MONTHLY'] ?? 9800),
        'price_annual' => (int)($_ENV['PLAN_TEAM_PRICE_ANNUAL'] ?? 94000),
        'posts_per_month' => (int)($_ENV['PLAN_TEAM_POSTS_PER_MONTH'] ?? -1),
        'social_accounts' => (int)($_ENV['PLAN_TEAM_CONNECTIONS'] ?? 20),
        'ai_generations' => (int)($_ENV['PLAN_TEAM_AI_GENERATIONS'] ?? 500),
        'ai_credits_monthly' => (int)($_ENV['PLAN_TEAM_AI_CREDITS_MONTHLY'] ?? 1000),
        'analytics_days' => (int)($_ENV['PLAN_TEAM_ANALYTICS_DAYS'] ?? 365),
        'streams' => (int)($_ENV['PLAN_TEAM_STREAMS'] ?? 20),
        'included_seats' => (int)($_ENV['PLAN_TEAM_INCLUDED_SEATS'] ?? 5),
        'seat_price' => (int)($_ENV['PLAN_TEAM_SEAT_PRICE'] ?? 1500),
        'max_seats' => (int)($_ENV['PLAN_TEAM_MAX_SEATS'] ?? 25),
        'bulk_import' => (int)($_ENV['PLAN_TEAM_BULK_IMPORT'] ?? 1) === 1,
        'recycling' => (int)($_ENV['PLAN_TEAM_RECYCLING'] ?? 1) === 1,
        'approval' => (int)($_ENV['PLAN_TEAM_APPROVAL'] ?? 1) === 1,
    ],
    'agency' => [
        'price_monthly' => (int)($_ENV['PLAN_AGENCY_PRICE_MONTHLY'] ?? 19300),
        'price_annual' => (int)($_ENV['PLAN_AGENCY_PRICE_ANNUAL'] ?? 185200),
        'posts_per_month' => (int)($_ENV['PLAN_AGENCY_POSTS_PER_MONTH'] ?? -1),
        'social_accounts' => (int)($_ENV['PLAN_AGENCY_CONNECTIONS'] ?? 50),
        'ai_generations' => (int)($_ENV['PLAN_AGENCY_AI_GENERATIONS'] ?? 1000),
        'ai_credits_monthly' => (int)($_ENV['PLAN_AGENCY_AI_CREDITS_MONTHLY'] ?? 2500),
        'analytics_days' => (int)($_ENV['PLAN_AGENCY_ANALYTICS_DAYS'] ?? -1),
        'streams' => (int)($_ENV['PLAN_AGENCY_STREAMS'] ?? 50),
        'included_seats' => (int)($_ENV['PLAN_AGENCY_INCLUDED_SEATS'] ?? 15),
        'seat_price' => (int)($_ENV['PLAN_AGENCY_SEAT_PRICE'] ?? 1800),
        'max_seats' => (int)($_ENV['PLAN_AGENCY_MAX_SEATS'] ?? 50),
        'bulk_import' => (int)($_ENV['PLAN_AGENCY_BULK_IMPORT'] ?? 1) === 1,
        'recycling' => (int)($_ENV['PLAN_AGENCY_RECYCLING'] ?? 1) === 1,
        'approval' => (int)($_ENV['PLAN_AGENCY_APPROVAL'] ?? 1) === 1,
    ],
]);

// Annual billing enabled
define('ANNUAL_BILLING_ENABLED', (int)($_ENV['ANNUAL_BILLING_ENABLED'] ?? 1) === 1);

// AI Credit Packages (one-time top-ups)
define('CREDIT_PACKAGES', [
    'starter' => [
        'price' => (int)($_ENV['CREDIT_STARTER_PRICE'] ?? 1000),
        'amount' => (int)($_ENV['CREDIT_STARTER_AMOUNT'] ?? 1000),
        'bonus' => (int)($_ENV['CREDIT_STARTER_BONUS'] ?? 0),
    ],
    'builder' => [
        'price' => (int)($_ENV['CREDIT_BUILDER_PRICE'] ?? 2500),
        'amount' => (int)($_ENV['CREDIT_BUILDER_AMOUNT'] ?? 2500),
        'bonus' => (int)($_ENV['CREDIT_BUILDER_BONUS'] ?? 200),
    ],
    'growth' => [
        'price' => (int)($_ENV['CREDIT_GROWTH_PRICE'] ?? 5000),
        'amount' => (int)($_ENV['CREDIT_GROWTH_AMOUNT'] ?? 5000),
        'bonus' => (int)($_ENV['CREDIT_GROWTH_BONUS'] ?? 500),
    ],
    'scale' => [
        'price' => (int)($_ENV['CREDIT_SCALE_PRICE'] ?? 10000),
        'amount' => (int)($_ENV['CREDIT_SCALE_AMOUNT'] ?? 10000),
        'bonus' => (int)($_ENV['CREDIT_SCALE_BONUS'] ?? 1500),
    ],
]);

// Credit expiry behavior
define('AI_CREDITS_MONTHLY_EXPIRE', (int)($_ENV['AI_CREDITS_MONTHLY_EXPIRE'] ?? 1) === 1);
define('AI_CREDITS_TOPUP_EXPIRE', (int)($_ENV['AI_CREDITS_TOPUP_EXPIRE'] ?? 0) === 1);

// AI credit costs (in cents)
define('AI_COST_CREATE_POST', 5);
define('AI_COST_OPTIMIZE_POST', 3);
define('AI_COST_HASHTAGS', 2);
define('AI_COST_SCORE_POST', 2);
define('AI_COST_CAMPAIGN_MESSAGE', 10);

// Error handler
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone
date_default_timezone_set('UTC');
