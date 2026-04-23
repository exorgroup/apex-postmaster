<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/JWTService.php';

class AuthMiddleware
{
    private static ?array $currentUser = null;

    public static function authenticate(): array
    {
        $token = JWTService::getTokenFromHeader();

        if (!$token) {
            Response::error('Unauthorized: No token provided', 401);
        }

        $payload = JWTService::validateAccessToken($token);

        if (!$payload) {
            Response::error('Unauthorized: Invalid or expired token', 401);
        }

        // Fetch full user data
        $user = Database::fetch(
            "SELECT id, email, name, plan, avatar, timezone, industry, onboarding_completed, role, credit_balance_cents, created_at
             FROM users WHERE id = ? AND deleted_at IS NULL",
            [$payload['user_id']]
        );

        if (!$user) {
            Response::error('Unauthorized: User not found', 401);
        }

        self::$currentUser = $user;
        return $user;
    }

    public static function optional(): ?array
    {
        $token = JWTService::getTokenFromHeader();

        if (!$token) {
            return null;
        }

        $payload = JWTService::validateAccessToken($token);

        if (!$payload) {
            return null;
        }

        $user = Database::fetch(
            "SELECT id, email, name, plan, avatar, timezone, industry, onboarding_completed, role, credit_balance_cents, created_at
             FROM users WHERE id = ? AND deleted_at IS NULL",
            [$payload['user_id']]
        );

        self::$currentUser = $user;
        return $user;
    }

    public static function requirePlan(string ...$plans): void
    {
        $user = self::$currentUser ?? self::authenticate();

        if (!in_array($user['plan'], $plans)) {
            Response::error('This feature requires a ' . implode(' or ', $plans) . ' plan', 403);
        }
    }

    public static function requireAdmin(): void
    {
        $user = self::$currentUser ?? self::authenticate();

        if (($user['role'] ?? 'user') !== 'admin') {
            Response::error('Admin access required', 403);
        }
    }

    public static function getCurrentUser(): ?array
    {
        return self::$currentUser;
    }

    public static function getUserId(): ?int
    {
        return self::$currentUser['id'] ?? null;
    }

    public static function getPlan(): string
    {
        return self::$currentUser['plan'] ?? 'free';
    }

    public static function getPlanLimit(string $key): mixed
    {
        $plan = self::getPlan();
        return PLAN_LIMITS[$plan][$key] ?? null;
    }

    public static function checkUsageLimit(string $type): bool
    {
        $userId = self::getUserId();
        $limit = self::getPlanLimit($type);

        if ($limit === -1) {
            return true; // Unlimited
        }

        switch ($type) {
            case 'posts_per_month':
                $count = Database::fetch(
                    "SELECT COUNT(*) as cnt FROM posts
                     WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
                    [$userId]
                )['cnt'] ?? 0;
                return $count < $limit;

            case 'social_accounts':
                $count = Database::fetch(
                    "SELECT COUNT(*) as cnt FROM social_accounts
                     WHERE user_id = ? AND is_active = 1",
                    [$userId]
                )['cnt'] ?? 0;
                return $count < $limit;

            case 'ai_generations':
                $count = Database::fetch(
                    "SELECT COUNT(*) as cnt FROM ai_generations
                     WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
                    [$userId]
                )['cnt'] ?? 0;
                return $count < $limit;

            case 'streams':
                $count = Database::fetch(
                    "SELECT COUNT(*) as cnt FROM streams WHERE user_id = ?",
                    [$userId]
                )['cnt'] ?? 0;
                return $count < $limit;

            default:
                return true;
        }
    }
}
