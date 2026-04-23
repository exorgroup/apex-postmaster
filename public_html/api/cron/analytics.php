<?php

/**
 * Analytics Fetcher Cron Script
 * Runs every hour to fetch analytics for published posts
 *
 * Crontab: 0 * * * * php /path/to/api/cron/analytics.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Analytics cron started\n";

try {
    // Get all active social accounts
    $accounts = Database::fetchAll(
        "SELECT sa.*, u.id as user_id
         FROM social_accounts sa
         JOIN users u ON u.id = sa.user_id
         WHERE sa.is_active = 1"
    );

    echo "Found " . count($accounts) . " active accounts\n";

    foreach ($accounts as $account) {
        echo "Processing {$account['platform']} account for user #{$account['user_id']}...\n";

        // Get published posts for this platform in last 30 days
        $posts = Database::fetchAll(
            "SELECT p.id, pp.platform_post_id
             FROM posts p
             JOIN post_platforms pp ON pp.post_id = p.id
             WHERE p.user_id = ?
               AND pp.platform = ?
               AND pp.status = 'published'
               AND pp.platform_post_id IS NOT NULL
               AND p.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             LIMIT 100",
            [$account['user_id'], $account['platform']]
        );

        echo "  Found " . count($posts) . " posts to fetch analytics for\n";

        foreach ($posts as $post) {
            try {
                $metrics = fetchPostMetrics($account, $post['platform_post_id']);

                if ($metrics) {
                    // Upsert analytics
                    $existing = Database::fetch(
                        "SELECT id FROM post_analytics WHERE post_id = ? AND platform = ? AND date = CURDATE()",
                        [$post['id'], $account['platform']]
                    );

                    if ($existing) {
                        Database::update('post_analytics', [
                            'likes' => $metrics['likes'] ?? 0,
                            'comments' => $metrics['comments'] ?? 0,
                            'shares' => $metrics['shares'] ?? 0,
                            'impressions' => $metrics['impressions'] ?? 0,
                            'reach' => $metrics['reach'] ?? 0,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ], 'id = ?', [$existing['id']]);
                    } else {
                        Database::insert('post_analytics', [
                            'post_id' => $post['id'],
                            'platform' => $account['platform'],
                            'date' => date('Y-m-d'),
                            'likes' => $metrics['likes'] ?? 0,
                            'comments' => $metrics['comments'] ?? 0,
                            'shares' => $metrics['shares'] ?? 0,
                            'impressions' => $metrics['impressions'] ?? 0,
                            'reach' => $metrics['reach'] ?? 0,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            } catch (Exception $e) {
                echo "    Error fetching post #{$post['id']}: {$e->getMessage()}\n";
            }

            // Rate limiting
            usleep(200000); // 200ms between requests
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Analytics cron completed\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function fetchPostMetrics(array $account, string $platformPostId): ?array
{
    switch ($account['platform']) {
        case 'youtube':
            return fetchYouTubeMetrics($account, $platformPostId);
        case 'facebook':
            return fetchFacebookMetrics($account, $platformPostId);
        case 'instagram':
            return fetchInstagramMetrics($account, $platformPostId);
        default:
            return null;
    }
}

function fetchYouTubeMetrics(array $account, string $videoId): ?array
{
    $url = "https://www.googleapis.com/youtube/v3/videos?" . http_build_query([
        'part' => 'statistics',
        'id' => $videoId,
    ]);

    $response = httpGet($url, ['Authorization: Bearer ' . $account['access_token']]);

    $stats = $response['items'][0]['statistics'] ?? null;
    if (!$stats) {
        return null;
    }

    return [
        'likes' => (int)($stats['likeCount'] ?? 0),
        'comments' => (int)($stats['commentCount'] ?? 0),
        'shares' => 0, // YouTube doesn't provide share count
        'impressions' => (int)($stats['viewCount'] ?? 0),
    ];
}

function fetchFacebookMetrics(array $account, string $postId): ?array
{
    $url = "https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
        'fields' => 'likes.summary(true),comments.summary(true),shares,insights.metric(post_impressions,post_reach)',
        'access_token' => $account['access_token'],
    ]);

    $response = httpGet($url);

    if (isset($response['error'])) {
        return null;
    }

    $impressions = 0;
    $reach = 0;

    if (isset($response['insights']['data'])) {
        foreach ($response['insights']['data'] as $insight) {
            if ($insight['name'] === 'post_impressions') {
                $impressions = (int)($insight['values'][0]['value'] ?? 0);
            }
            if ($insight['name'] === 'post_reach') {
                $reach = (int)($insight['values'][0]['value'] ?? 0);
            }
        }
    }

    return [
        'likes' => (int)($response['likes']['summary']['total_count'] ?? 0),
        'comments' => (int)($response['comments']['summary']['total_count'] ?? 0),
        'shares' => (int)($response['shares']['count'] ?? 0),
        'impressions' => $impressions,
        'reach' => $reach,
    ];
}

function fetchInstagramMetrics(array $account, string $mediaId): ?array
{
    $url = "https://graph.facebook.com/v18.0/{$mediaId}/insights?" . http_build_query([
        'metric' => 'likes,comments,shares,impressions,reach',
        'access_token' => $account['access_token'],
    ]);

    $response = httpGet($url);

    if (isset($response['error'])) {
        // Try basic fields instead
        $url = "https://graph.facebook.com/v18.0/{$mediaId}?" . http_build_query([
            'fields' => 'like_count,comments_count',
            'access_token' => $account['access_token'],
        ]);

        $response = httpGet($url);

        if (isset($response['error'])) {
            return null;
        }

        return [
            'likes' => (int)($response['like_count'] ?? 0),
            'comments' => (int)($response['comments_count'] ?? 0),
            'shares' => 0,
            'impressions' => 0,
        ];
    }

    $metrics = [];
    foreach ($response['data'] ?? [] as $insight) {
        $metrics[$insight['name']] = (int)($insight['values'][0]['value'] ?? 0);
    }

    return [
        'likes' => $metrics['likes'] ?? 0,
        'comments' => $metrics['comments'] ?? 0,
        'shares' => $metrics['shares'] ?? 0,
        'impressions' => $metrics['impressions'] ?? 0,
        'reach' => $metrics['reach'] ?? 0,
    ];
}

function httpGet(string $url, array $headers = []): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);

    $response = file_get_contents($url, false, $context);
    return json_decode($response, true) ?? [];
}
