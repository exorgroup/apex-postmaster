<?php

declare(strict_types=1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load configuration and dependencies
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/services/JWTService.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Error handler
set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());

    if (APP_DEBUG) {
        Response::error($e->getMessage(), 500);
    } else {
        Response::serverError();
    }
});

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Remove query string and base path
$uri = parse_url($uri, PHP_URL_PATH);
// Only strip /api if it's present (when called directly, not through main router)
if (str_starts_with($uri, '/api')) {
    $uri = substr($uri, 4);
}
$uri = trim($uri, '/');

// Route definitions
$routes = [
    // Auth routes
    'POST auth/register' => ['AuthController', 'register'],
    'POST auth/login' => ['AuthController', 'login'],
    'POST auth/refresh' => ['AuthController', 'refresh'],
    'POST auth/logout' => ['AuthController', 'logout'],
    'GET auth/me' => ['AuthController', 'me'],
    'PUT auth/profile' => ['AuthController', 'updateProfile'],
    'PUT auth/password' => ['AuthController', 'changePassword'],
    'POST auth/forgot-password' => ['AuthController', 'forgotPassword'],
    'POST auth/reset-password' => ['AuthController', 'resetPassword'],
    'GET auth/{provider}' => ['AuthController', 'oauthInitiate'],
    'POST auth/oauth' => ['AuthController', 'oauthCallback'],

    // Posts routes
    'GET posts' => ['PostsController', 'index'],
    'POST posts' => ['PostsController', 'create'],
    'GET posts/{id}' => ['PostsController', 'show'],
    'PUT posts/{id}' => ['PostsController', 'update'],
    'DELETE posts/{id}' => ['PostsController', 'delete'],
    'POST posts/{id}/schedule' => ['PostsController', 'schedule'],
    'POST posts/{id}/publish' => ['PostsController', 'publishNow'],
    'GET posts/calendar' => ['PostsController', 'calendar'],

    // Platforms routes
    'GET platforms' => ['PlatformsController', 'index'],
    'GET platforms/connect/{platform}' => ['PlatformsController', 'oauthInitiate'],
    'GET platforms/callback/{platform}' => ['PlatformsController', 'oauthCallback'],
    'POST platforms/connect' => ['PlatformsController', 'connect'],
    'DELETE platforms/{id}' => ['PlatformsController', 'disconnect'],
    'PUT platforms/{id}' => ['PlatformsController', 'update'],
    'POST platforms/{id}/refresh' => ['PlatformsController', 'refreshToken'],

    // AI routes
    'POST ai/create' => ['AIController', 'createPost'],
    'POST ai/optimize' => ['AIController', 'optimizePost'],
    'POST ai/hashtags' => ['AIController', 'generateHashtags'],
    'POST ai/score' => ['AIController', 'scorePost'],

    // Campaigns routes
    'GET campaigns/chats' => ['CampaignsController', 'listChats'],
    'POST campaigns/chats' => ['CampaignsController', 'createChat'],
    'GET campaigns/chats/{id}' => ['CampaignsController', 'getChat'],
    'GET campaigns/chats/{id}/messages' => ['CampaignsController', 'getMessages'],
    'POST campaigns/chats/{id}/messages' => ['CampaignsController', 'sendMessage'],
    'DELETE campaigns/chats/{id}' => ['CampaignsController', 'deleteChat'],

    // Credits routes
    'GET credits/balance' => ['CreditsController', 'balance'],
    'GET credits/transactions' => ['CreditsController', 'transactions'],
    'POST credits/topup' => ['CreditsController', 'topup'],

    // Billing routes
    'GET billing/subscription' => ['BillingController', 'subscription'],
    'GET billing/details' => ['BillingController', 'getSubscription'],
    'POST billing/checkout' => ['BillingController', 'createCheckout'],
    'POST billing/portal' => ['BillingController', 'createPortal'],
    'POST billing/sync' => ['BillingController', 'syncSubscription'],
    'POST billing/seats/add' => ['BillingController', 'addSeats'],
    'POST billing/seats/remove' => ['BillingController', 'removeSeats'],
    'POST billing/upgrade' => ['BillingController', 'upgradePlan'],
    'POST billing/downgrade' => ['BillingController', 'downgradePlan'],
    'POST billing/cancel' => ['BillingController', 'cancelSubscription'],
    'POST billing/reactivate' => ['BillingController', 'reactivateSubscription'],
    'POST billing/process-scheduled' => ['BillingController', 'processScheduledChanges'],
    'POST billing/webhook' => ['BillingController', 'webhook'],

    // Coupons routes (admin)
    'GET coupons' => ['CouponsController', 'index'],
    'POST coupons' => ['CouponsController', 'create'],
    'PUT coupons/{id}' => ['CouponsController', 'update'],
    'DELETE coupons/{id}' => ['CouponsController', 'delete'],
    'POST coupons/redeem' => ['CouponsController', 'redeem'],

    // Analytics routes
    'GET analytics/overview' => ['AnalyticsController', 'overview'],
    'GET analytics/posts' => ['AnalyticsController', 'posts'],
    'GET analytics/platforms' => ['AnalyticsController', 'platforms'],
    'GET analytics/hashtags' => ['AnalyticsController', 'hashtags'],

    // Streams routes
    'GET streams' => ['StreamsController', 'index'],
    'POST streams' => ['StreamsController', 'create'],
    'GET streams/{id}' => ['StreamsController', 'show'],
    'GET streams/{id}/items' => ['StreamsController', 'items'],
    'DELETE streams/{id}' => ['StreamsController', 'delete'],

    // Contact route
    'POST contact' => ['ContactController', 'submit'],

    // Public config route
    'GET config/plans' => ['ConfigController', 'plans'],

    // Stripe setup routes (admin only)
    'POST stripe/sync' => ['StripeSetupController', 'sync'],
    'GET stripe/status' => ['StripeSetupController', 'status'],
];

// Find matching route
$matchedRoute = null;
$params = [];

foreach ($routes as $route => $handler) {
    [$routeMethod, $routePath] = explode(' ', $route, 2);

    if ($method !== $routeMethod) {
        continue;
    }

    // Convert route pattern to regex
    $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $uri, $matches)) {
        $matchedRoute = $handler;
        array_shift($matches); // Remove full match
        $params = $matches;
        break;
    }
}

if (!$matchedRoute) {
    Response::notFound('Endpoint not found');
}

// Load controller
[$controllerName, $methodName] = $matchedRoute;
$controllerFile = __DIR__ . '/controllers/' . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    Response::serverError('Controller not found');
}

require_once $controllerFile;

if (!class_exists($controllerName) || !method_exists($controllerName, $methodName)) {
    Response::serverError('Method not found');
}

// Call controller method with params
call_user_func_array([$controllerName, $methodName], $params);
