<?php

/**
 * Post Publisher Cron Script
 * Runs every minute to publish scheduled posts
 *
 * Crontab: * * * * * php /path/to/api/cron/publisher.php
 */

declare(strict_types=1);

// Prevent web access
if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Publisher cron started\n";

try {
    // Find posts that need to be published
    $posts = Database::fetchAll(
        "SELECT p.*, u.id as user_id
         FROM posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.status = 'scheduled'
           AND p.scheduled_at <= NOW()
           AND p.scheduled_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
         ORDER BY p.scheduled_at ASC
         LIMIT 50"
    );

    echo "Found " . count($posts) . " posts to publish\n";

    foreach ($posts as $post) {
        echo "Processing post #{$post['id']}...\n";

        // Get platforms for this post
        $platforms = Database::fetchAll(
            "SELECT pp.*, sa.access_token, sa.platform_user_id, sa.refresh_token
             FROM post_platforms pp
             JOIN social_accounts sa ON sa.user_id = ? AND sa.platform = pp.platform AND sa.is_active = 1
             WHERE pp.post_id = ? AND pp.status = 'pending'",
            [$post['user_id'], $post['id']]
        );

        if (empty($platforms)) {
            echo "  No platforms to publish to\n";
            Database::update('posts', [
                'status' => 'failed',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$post['id']]);
            continue;
        }

        $hasSuccess = false;
        $allFailed = true;

        foreach ($platforms as $platform) {
            echo "  Publishing to {$platform['platform']}...\n";

            try {
                $result = publishToPlatform($post, $platform);

                if ($result['success']) {
                    Database::update('post_platforms', [
                        'status' => 'published',
                        'platform_post_id' => $result['postId'] ?? null,
                        'published_at' => date('Y-m-d H:i:s'),
                    ], 'post_id = ? AND platform = ?', [$post['id'], $platform['platform']]);

                    echo "    Success!\n";
                    $hasSuccess = true;
                    $allFailed = false;
                } else {
                    Database::update('post_platforms', [
                        'status' => 'failed',
                        'error_message' => $result['error'],
                    ], 'post_id = ? AND platform = ?', [$post['id'], $platform['platform']]);

                    echo "    Failed: {$result['error']}\n";
                }
            } catch (Exception $e) {
                Database::update('post_platforms', [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ], 'post_id = ? AND platform = ?', [$post['id'], $platform['platform']]);

                echo "    Exception: {$e->getMessage()}\n";
            }
        }

        // Update main post status
        Database::update('posts', [
            'status' => $allFailed ? 'failed' : 'published',
            'published_at' => $allFailed ? null : date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$post['id']]);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Publisher cron completed\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function publishToPlatform(array $post, array $platform): array
{
    switch ($platform['platform']) {
        case 'youtube':
            return publishToYouTube($post, $platform);
        case 'facebook':
            return publishToFacebook($post, $platform);
        case 'instagram':
            return publishToInstagram($post, $platform);
        default:
            return ['success' => false, 'error' => 'Unsupported platform'];
    }
}

function publishToYouTube(array $post, array $platform): array
{
    $accessToken = $platform['access_token'];

    // Check if token needs refresh
    $accessToken = refreshTokenIfNeeded('youtube', $platform);

    $data = [
        'snippet' => [
            'type' => 'bulletin',
            'description' => $post['content'],
        ],
    ];

    $response = httpPost(
        'https://www.googleapis.com/youtube/v3/activities?part=snippet',
        $data,
        ['Authorization: Bearer ' . $accessToken]
    );

    if (isset($response['id'])) {
        return ['success' => true, 'postId' => $response['id']];
    }

    return ['success' => false, 'error' => $response['error']['message'] ?? 'YouTube API error'];
}

function publishToFacebook(array $post, array $platform): array
{
    $accessToken = $platform['access_token'];
    $pageId = $platform['platform_user_id'];

    $url = "https://graph.facebook.com/v18.0/{$pageId}/feed";
    $data = ['message' => $post['content']];

    $mediaUrls = $post['media_urls'] ? json_decode($post['media_urls'], true) : [];
    if (!empty($mediaUrls)) {
        $url = "https://graph.facebook.com/v18.0/{$pageId}/photos";
        $data['url'] = $mediaUrls[0];
        $data['caption'] = $post['content'];
    }

    $response = httpPost($url . '?access_token=' . $accessToken, $data);

    if (isset($response['id'])) {
        return ['success' => true, 'postId' => $response['id']];
    }

    return ['success' => false, 'error' => $response['error']['message'] ?? 'Facebook API error'];
}

function publishToInstagram(array $post, array $platform): array
{
    $accessToken = $platform['access_token'];
    $igUserId = $platform['platform_user_id'];

    $mediaUrls = $post['media_urls'] ? json_decode($post['media_urls'], true) : [];

    if (empty($mediaUrls)) {
        return ['success' => false, 'error' => 'Instagram requires media'];
    }

    // Create container
    $containerUrl = "https://graph.facebook.com/v18.0/{$igUserId}/media";
    $container = httpPost($containerUrl, [
        'image_url' => $mediaUrls[0],
        'caption' => $post['content'],
        'access_token' => $accessToken,
    ]);

    if (!isset($container['id'])) {
        return ['success' => false, 'error' => $container['error']['message'] ?? 'Failed to create container'];
    }

    // Wait for container to be ready (Instagram processing)
    sleep(5);

    // Publish
    $publishUrl = "https://graph.facebook.com/v18.0/{$igUserId}/media_publish";
    $result = httpPost($publishUrl, [
        'creation_id' => $container['id'],
        'access_token' => $accessToken,
    ]);

    if (isset($result['id'])) {
        return ['success' => true, 'postId' => $result['id']];
    }

    return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to publish'];
}

function refreshTokenIfNeeded(string $platform, array $account): string
{
    // Check if token is about to expire
    if ($account['token_expires_at'] && strtotime($account['token_expires_at']) < time() + 300) {
        if ($platform === 'youtube' && $account['refresh_token']) {
            $response = httpPost('https://oauth2.googleapis.com/token', [
                'refresh_token' => $account['refresh_token'],
                'client_id' => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'grant_type' => 'refresh_token',
            ]);

            if (isset($response['access_token'])) {
                Database::update('social_accounts', [
                    'access_token' => $response['access_token'],
                    'token_expires_at' => date('Y-m-d H:i:s', time() + ($response['expires_in'] ?? 3600)),
                ], 'id = ?', [$account['id']]);

                return $response['access_token'];
            }
        }
    }

    return $account['access_token'];
}

function httpPost(string $url, array $data, array $headers = []): array
{
    $isJson = !empty($headers);

    if ($isJson) {
        $headers[] = 'Content-Type: application/json';
        $content = json_encode($data);
    } else {
        $content = http_build_query($data);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);

    $response = file_get_contents($url, false, $context);
    return json_decode($response, true) ?? [];
}
