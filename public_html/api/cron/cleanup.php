<?php

/**
 * Cleanup Cron Script
 * Runs daily to clean up old data
 *
 * Crontab: 0 3 * * * php /path/to/api/cron/cleanup.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Cleanup cron started\n";

try {
    // 1. Delete expired refresh tokens
    $deleted = Database::delete(
        'refresh_tokens',
        'expires_at < NOW()'
    );
    echo "Deleted $deleted expired refresh tokens\n";

    // 2. Delete expired password reset tokens
    $deleted = Database::delete(
        'password_resets',
        'expires_at < NOW()'
    );
    echo "Deleted $deleted expired password reset tokens\n";

    // 3. Delete old stream items (keep 7 days)
    $deleted = Database::delete(
        'stream_items',
        'created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)'
    );
    echo "Deleted $deleted old stream items\n";

    // 4. Delete old AI generation logs (keep 90 days)
    $deleted = Database::delete(
        'ai_generations',
        'created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)'
    );
    echo "Deleted $deleted old AI generation logs\n";

    // 5. Delete old analytics data based on plan limits
    // Free users: 7 days, Pro: 90 days, Team: 365 days
    $users = Database::fetchAll("SELECT id, plan FROM users WHERE deleted_at IS NULL");

    foreach ($users as $user) {
        $days = match ($user['plan']) {
            'team' => 365,
            'pro' => 90,
            default => 7,
        };

        $deleted = Database::delete(
            'post_analytics',
            'post_id IN (SELECT id FROM posts WHERE user_id = ?) AND date < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$user['id'], $days]
        );

        if ($deleted > 0) {
            echo "Deleted $deleted old analytics records for user #{$user['id']}\n";
        }
    }

    // 6. Soft delete users who requested deletion 30 days ago
    $users = Database::fetchAll(
        "SELECT id FROM users WHERE deletion_requested_at IS NOT NULL AND deletion_requested_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND deleted_at IS NULL"
    );

    foreach ($users as $user) {
        // Anonymize user data
        Database::update('users', [
            'email' => 'deleted_' . $user['id'] . '@deleted.local',
            'name' => 'Deleted User',
            'password' => '',
            'avatar' => null,
            'stripe_customer_id' => null,
            'oauth_provider' => null,
            'oauth_id' => null,
            'deleted_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        // Revoke all tokens
        Database::delete('refresh_tokens', 'user_id = ?', [$user['id']]);

        // Deactivate social accounts
        Database::update('social_accounts', [
            'access_token' => null,
            'refresh_token' => null,
            'is_active' => 0,
        ], 'user_id = ?', [$user['id']]);

        echo "Permanently deleted user #{$user['id']}\n";
    }

    // 7. Clean up failed posts older than 30 days
    $deleted = Database::delete(
        'posts',
        "status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    echo "Deleted $deleted old failed posts\n";

    // 8. Clean up orphaned post_platforms records
    $deleted = Database::query(
        "DELETE pp FROM post_platforms pp
         LEFT JOIN posts p ON p.id = pp.post_id
         WHERE p.id IS NULL"
    )->rowCount();
    echo "Deleted $deleted orphaned post_platforms records\n";

    // 9. Update subscription statuses for past_due subscriptions older than 30 days
    Database::query(
        "UPDATE subscriptions SET status = 'canceled'
         WHERE status = 'past_due' AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );

    // Downgrade users with canceled subscriptions
    Database::query(
        "UPDATE users u
         JOIN subscriptions s ON s.user_id = u.id
         SET u.plan = 'free'
         WHERE s.status = 'canceled' AND u.plan != 'free'"
    );

    echo "[" . date('Y-m-d H:i:s') . "] Cleanup cron completed\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
