<?php

declare(strict_types=1);

class AnalyticsController
{
    public static function overview(): void
    {
        $user = AuthMiddleware::authenticate();

        $days = min(365, max(7, (int)($_GET['days'] ?? 30)));
        $planLimit = AuthMiddleware::getPlanLimit('analytics_days');

        if ($planLimit !== -1 && $days > $planLimit) {
            $days = $planLimit;
        }

        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // Get totals
        $totals = Database::fetch(
            "SELECT
                COUNT(*) as total_posts,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_posts,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_posts
             FROM posts
             WHERE user_id = ? AND created_at >= ?",
            [$user['id'], $startDate]
        );

        // Get posts by day
        $postsByDay = Database::fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM posts
             WHERE user_id = ? AND created_at >= ?
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$user['id'], $startDate]
        );

        // Get posts by platform
        $postsByPlatform = Database::fetchAll(
            "SELECT pp.platform, COUNT(*) as count
             FROM post_platforms pp
             JOIN posts p ON p.id = pp.post_id
             WHERE p.user_id = ? AND p.created_at >= ?
             GROUP BY pp.platform",
            [$user['id'], $startDate]
        );

        // Get engagement metrics (if available)
        $engagement = Database::fetch(
            "SELECT
                SUM(likes) as total_likes,
                SUM(comments) as total_comments,
                SUM(shares) as total_shares,
                SUM(impressions) as total_impressions
             FROM post_analytics pa
             JOIN posts p ON p.id = pa.post_id
             WHERE p.user_id = ? AND pa.date >= ?",
            [$user['id'], $startDate]
        );

        Response::success([
            'period' => [
                'days' => $days,
                'startDate' => $startDate,
                'endDate' => date('Y-m-d'),
            ],
            'totals' => [
                'posts' => (int)($totals['total_posts'] ?? 0),
                'published' => (int)($totals['published_posts'] ?? 0),
                'scheduled' => (int)($totals['scheduled_posts'] ?? 0),
                'drafts' => (int)($totals['draft_posts'] ?? 0),
            ],
            'engagement' => [
                'likes' => (int)($engagement['total_likes'] ?? 0),
                'comments' => (int)($engagement['total_comments'] ?? 0),
                'shares' => (int)($engagement['total_shares'] ?? 0),
                'impressions' => (int)($engagement['total_impressions'] ?? 0),
            ],
            'postsByDay' => array_map(function ($row) {
                return [
                    'date' => $row['date'],
                    'count' => (int)$row['count'],
                ];
            }, $postsByDay),
            'postsByPlatform' => array_map(function ($row) {
                return [
                    'platform' => $row['platform'],
                    'count' => (int)$row['count'],
                ];
            }, $postsByPlatform),
        ]);
    }

    public static function posts(): void
    {
        $user = AuthMiddleware::authenticate();

        $days = min(365, max(7, (int)($_GET['days'] ?? 30)));
        $planLimit = AuthMiddleware::getPlanLimit('analytics_days');

        if ($planLimit !== -1 && $days > $planLimit) {
            $days = $planLimit;
        }

        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $posts = Database::fetchAll(
            "SELECT p.id, p.content, p.status, p.published_at,
                    GROUP_CONCAT(pp.platform) as platforms,
                    COALESCE(SUM(pa.likes), 0) as likes,
                    COALESCE(SUM(pa.comments), 0) as comments,
                    COALESCE(SUM(pa.shares), 0) as shares,
                    COALESCE(SUM(pa.impressions), 0) as impressions
             FROM posts p
             LEFT JOIN post_platforms pp ON pp.post_id = p.id
             LEFT JOIN post_analytics pa ON pa.post_id = p.id
             WHERE p.user_id = ? AND p.status = 'published' AND p.published_at >= ?
             GROUP BY p.id
             ORDER BY p.published_at DESC
             LIMIT 100",
            [$user['id'], $startDate]
        );

        $formatted = array_map(function ($post) {
            $engagement = ($post['likes'] ?? 0) + ($post['comments'] ?? 0) + ($post['shares'] ?? 0);
            $impressions = (int)($post['impressions'] ?? 0);
            $engagementRate = $impressions > 0 ? round(($engagement / $impressions) * 100, 2) : 0;

            return [
                'id' => (int)$post['id'],
                'content' => substr($post['content'], 0, 100) . (strlen($post['content']) > 100 ? '...' : ''),
                'platforms' => $post['platforms'] ? explode(',', $post['platforms']) : [],
                'publishedAt' => $post['published_at'],
                'metrics' => [
                    'likes' => (int)($post['likes'] ?? 0),
                    'comments' => (int)($post['comments'] ?? 0),
                    'shares' => (int)($post['shares'] ?? 0),
                    'impressions' => $impressions,
                    'engagementRate' => $engagementRate,
                ],
            ];
        }, $posts);

        Response::success($formatted);
    }

    public static function platforms(): void
    {
        $user = AuthMiddleware::authenticate();

        $days = min(365, max(7, (int)($_GET['days'] ?? 30)));
        $planLimit = AuthMiddleware::getPlanLimit('analytics_days');

        if ($planLimit !== -1 && $days > $planLimit) {
            $days = $planLimit;
        }

        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $platforms = Database::fetchAll(
            "SELECT
                pp.platform,
                COUNT(DISTINCT p.id) as post_count,
                COALESCE(SUM(pa.likes), 0) as total_likes,
                COALESCE(SUM(pa.comments), 0) as total_comments,
                COALESCE(SUM(pa.shares), 0) as total_shares,
                COALESCE(SUM(pa.impressions), 0) as total_impressions
             FROM post_platforms pp
             JOIN posts p ON p.id = pp.post_id
             LEFT JOIN post_analytics pa ON pa.post_id = p.id AND pa.platform = pp.platform
             WHERE p.user_id = ? AND p.published_at >= ?
             GROUP BY pp.platform",
            [$user['id'], $startDate]
        );

        $formatted = array_map(function ($platform) {
            $engagement = ($platform['total_likes'] ?? 0) + ($platform['total_comments'] ?? 0) + ($platform['total_shares'] ?? 0);
            $impressions = (int)($platform['total_impressions'] ?? 0);
            $engagementRate = $impressions > 0 ? round(($engagement / $impressions) * 100, 2) : 0;

            return [
                'platform' => $platform['platform'],
                'postCount' => (int)$platform['post_count'],
                'metrics' => [
                    'likes' => (int)($platform['total_likes'] ?? 0),
                    'comments' => (int)($platform['total_comments'] ?? 0),
                    'shares' => (int)($platform['total_shares'] ?? 0),
                    'impressions' => $impressions,
                    'engagementRate' => $engagementRate,
                ],
            ];
        }, $platforms);

        Response::success($formatted);
    }

    public static function hashtags(): void
    {
        $user = AuthMiddleware::authenticate();

        $days = min(365, max(7, (int)($_GET['days'] ?? 30)));
        $planLimit = AuthMiddleware::getPlanLimit('analytics_days');

        if ($planLimit !== -1 && $days > $planLimit) {
            $days = $planLimit;
        }

        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // Get all published posts content
        $posts = Database::fetchAll(
            "SELECT p.id, p.content,
                    COALESCE(SUM(pa.likes), 0) as likes,
                    COALESCE(SUM(pa.comments), 0) as comments,
                    COALESCE(SUM(pa.impressions), 0) as impressions
             FROM posts p
             LEFT JOIN post_analytics pa ON pa.post_id = p.id
             WHERE p.user_id = ? AND p.status = 'published' AND p.published_at >= ?
             GROUP BY p.id",
            [$user['id'], $startDate]
        );

        // Extract and aggregate hashtags
        $hashtagStats = [];

        foreach ($posts as $post) {
            preg_match_all('/#(\w+)/', $post['content'], $matches);

            foreach ($matches[1] as $hashtag) {
                $tag = strtolower($hashtag);

                if (!isset($hashtagStats[$tag])) {
                    $hashtagStats[$tag] = [
                        'hashtag' => '#' . $tag,
                        'uses' => 0,
                        'likes' => 0,
                        'comments' => 0,
                        'impressions' => 0,
                    ];
                }

                $hashtagStats[$tag]['uses']++;
                $hashtagStats[$tag]['likes'] += (int)$post['likes'];
                $hashtagStats[$tag]['comments'] += (int)$post['comments'];
                $hashtagStats[$tag]['impressions'] += (int)$post['impressions'];
            }
        }

        // Calculate averages and sort
        $result = array_values(array_map(function ($stat) {
            $engagement = $stat['likes'] + $stat['comments'];
            $avgEngagement = $stat['uses'] > 0 ? round($engagement / $stat['uses'], 1) : 0;
            $engagementRate = $stat['impressions'] > 0 ? round(($engagement / $stat['impressions']) * 100, 2) : 0;

            return [
                'hashtag' => $stat['hashtag'],
                'uses' => $stat['uses'],
                'avgLikes' => $stat['uses'] > 0 ? round($stat['likes'] / $stat['uses'], 1) : 0,
                'avgComments' => $stat['uses'] > 0 ? round($stat['comments'] / $stat['uses'], 1) : 0,
                'avgEngagement' => $avgEngagement,
                'engagementRate' => $engagementRate,
            ];
        }, $hashtagStats));

        // Sort by uses descending
        usort($result, function ($a, $b) {
            return $b['uses'] - $a['uses'];
        });

        // Return top 50
        Response::success(array_slice($result, 0, 50));
    }
}
