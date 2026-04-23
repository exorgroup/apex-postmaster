<?php

/**
 * Streams Fetcher Cron Script
 * Runs every 5 minutes to fetch new items for streams
 *
 * Crontab: */5 * * * * php /path/to/api/cron/streams.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "[" . date('Y-m-d H:i:s') . "] Streams cron started\n";

try {
    // Get all active streams (excluding 'scheduled' type which is handled differently)
    $streams = Database::fetchAll(
        "SELECT s.*, sa.access_token, sa.platform_user_id
         FROM streams s
         JOIN social_accounts sa ON sa.user_id = s.user_id AND sa.platform = s.platform AND sa.is_active = 1
         WHERE s.is_active = 1 AND s.type != 'scheduled'"
    );

    echo "Found " . count($streams) . " active streams\n";

    foreach ($streams as $stream) {
        echo "Processing stream #{$stream['id']} ({$stream['type']}) for {$stream['platform']}...\n";

        try {
            $items = fetchStreamItems($stream);

            if (!empty($items)) {
                $inserted = 0;
                foreach ($items as $item) {
                    // Check if item already exists
                    $exists = Database::fetch(
                        "SELECT id FROM stream_items WHERE stream_id = ? AND platform_post_id = ?",
                        [$stream['id'], $item['platformPostId']]
                    );

                    if (!$exists) {
                        Database::insert('stream_items', [
                            'stream_id' => $stream['id'],
                            'type' => $item['type'],
                            'content' => $item['content'],
                            'author_name' => $item['authorName'],
                            'author_avatar' => $item['authorAvatar'] ?? null,
                            'platform' => $stream['platform'],
                            'platform_post_id' => $item['platformPostId'],
                            'likes' => $item['likes'] ?? 0,
                            'comments' => $item['comments'] ?? 0,
                            'created_at' => $item['createdAt'] ?? date('Y-m-d H:i:s'),
                        ]);
                        $inserted++;
                    }
                }
                echo "  Inserted $inserted new items\n";
            }
        } catch (Exception $e) {
            echo "  Error: {$e->getMessage()}\n";
        }

        // Rate limiting
        sleep(1);
    }

    echo "[" . date('Y-m-d H:i:s') . "] Streams cron completed\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function fetchStreamItems(array $stream): array
{
    switch ($stream['type']) {
        case 'mentions':
            return fetchMentions($stream);
        case 'hashtag':
            return fetchHashtagPosts($stream);
        case 'keywords':
            return fetchKeywordPosts($stream);
        default:
            return [];
    }
}

function fetchMentions(array $stream): array
{
    switch ($stream['platform']) {
        case 'youtube':
            return fetchYouTubeMentions($stream);
        case 'facebook':
            return fetchFacebookMentions($stream);
        case 'instagram':
            return fetchInstagramMentions($stream);
        default:
            return [];
    }
}

function fetchYouTubeMentions(array $stream): array
{
    // YouTube doesn't have a direct mentions API for community posts
    // This would fetch comments on the channel's videos
    $url = "https://www.googleapis.com/youtube/v3/commentThreads?" . http_build_query([
        'part' => 'snippet',
        'allThreadsRelatedToChannelId' => $stream['platform_user_id'],
        'maxResults' => 25,
        'order' => 'time',
    ]);

    $response = httpGet($url, ['Authorization: Bearer ' . $stream['access_token']]);

    $items = [];
    foreach ($response['items'] ?? [] as $comment) {
        $snippet = $comment['snippet']['topLevelComment']['snippet'];
        $items[] = [
            'type' => 'comment',
            'content' => $snippet['textDisplay'],
            'authorName' => $snippet['authorDisplayName'],
            'authorAvatar' => $snippet['authorProfileImageUrl'] ?? null,
            'platformPostId' => $comment['id'],
            'likes' => (int)($snippet['likeCount'] ?? 0),
            'comments' => 0,
            'createdAt' => date('Y-m-d H:i:s', strtotime($snippet['publishedAt'])),
        ];
    }

    return $items;
}

function fetchFacebookMentions(array $stream): array
{
    // Facebook Page mentions/tags
    $url = "https://graph.facebook.com/v18.0/{$stream['platform_user_id']}/tagged?" . http_build_query([
        'fields' => 'message,from,created_time,likes.summary(true),comments.summary(true)',
        'limit' => 25,
        'access_token' => $stream['access_token'],
    ]);

    $response = httpGet($url);

    $items = [];
    foreach ($response['data'] ?? [] as $post) {
        $items[] = [
            'type' => 'mention',
            'content' => $post['message'] ?? '',
            'authorName' => $post['from']['name'] ?? 'Unknown',
            'authorAvatar' => null,
            'platformPostId' => $post['id'],
            'likes' => (int)($post['likes']['summary']['total_count'] ?? 0),
            'comments' => (int)($post['comments']['summary']['total_count'] ?? 0),
            'createdAt' => date('Y-m-d H:i:s', strtotime($post['created_time'])),
        ];
    }

    return $items;
}

function fetchInstagramMentions(array $stream): array
{
    // Instagram mentions via Facebook Graph API
    $url = "https://graph.facebook.com/v18.0/{$stream['platform_user_id']}/tags?" . http_build_query([
        'fields' => 'caption,username,timestamp,like_count,comments_count',
        'limit' => 25,
        'access_token' => $stream['access_token'],
    ]);

    $response = httpGet($url);

    $items = [];
    foreach ($response['data'] ?? [] as $media) {
        $items[] = [
            'type' => 'mention',
            'content' => $media['caption'] ?? '',
            'authorName' => $media['username'] ?? 'Unknown',
            'authorAvatar' => null,
            'platformPostId' => $media['id'],
            'likes' => (int)($media['like_count'] ?? 0),
            'comments' => (int)($media['comments_count'] ?? 0),
            'createdAt' => date('Y-m-d H:i:s', strtotime($media['timestamp'])),
        ];
    }

    return $items;
}

function fetchHashtagPosts(array $stream): array
{
    $hashtag = ltrim($stream['keywords'], '#');

    switch ($stream['platform']) {
        case 'instagram':
            return fetchInstagramHashtag($stream, $hashtag);
        default:
            // YouTube and Facebook don't have public hashtag search APIs
            return [];
    }
}

function fetchInstagramHashtag(array $stream, string $hashtag): array
{
    // First, get hashtag ID
    $url = "https://graph.facebook.com/v18.0/ig_hashtag_search?" . http_build_query([
        'user_id' => $stream['platform_user_id'],
        'q' => $hashtag,
        'access_token' => $stream['access_token'],
    ]);

    $response = httpGet($url);
    $hashtagId = $response['data'][0]['id'] ?? null;

    if (!$hashtagId) {
        return [];
    }

    // Get recent media
    $url = "https://graph.facebook.com/v18.0/{$hashtagId}/recent_media?" . http_build_query([
        'user_id' => $stream['platform_user_id'],
        'fields' => 'caption,permalink,timestamp,like_count,comments_count',
        'limit' => 25,
        'access_token' => $stream['access_token'],
    ]);

    $response = httpGet($url);

    $items = [];
    foreach ($response['data'] ?? [] as $media) {
        $items[] = [
            'type' => 'hashtag',
            'content' => $media['caption'] ?? '',
            'authorName' => 'Instagram User',
            'authorAvatar' => null,
            'platformPostId' => $media['id'],
            'likes' => (int)($media['like_count'] ?? 0),
            'comments' => (int)($media['comments_count'] ?? 0),
            'createdAt' => date('Y-m-d H:i:s', strtotime($media['timestamp'])),
        ];
    }

    return $items;
}

function fetchKeywordPosts(array $stream): array
{
    // Keyword search is limited on most platforms
    // This is a placeholder for platforms that support it
    return [];
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
