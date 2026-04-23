<?php

declare(strict_types=1);

class StreamsController
{
    public static function index(): void
    {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePlan('pro', 'team');

        $streams = Database::fetchAll(
            "SELECT id, name, type, platform, keywords, is_active, created_at
             FROM streams
             WHERE user_id = ?
             ORDER BY created_at DESC",
            [$user['id']]
        );

        $formatted = array_map(function ($stream) {
            return [
                'id' => (int)$stream['id'],
                'name' => $stream['name'],
                'type' => $stream['type'],
                'platform' => $stream['platform'],
                'keywords' => $stream['keywords'],
                'isActive' => (bool)$stream['is_active'],
                'createdAt' => $stream['created_at'],
            ];
        }, $streams);

        Response::success($formatted);
    }

    public static function create(): void
    {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePlan('pro', 'team');

        if (!AuthMiddleware::checkUsageLimit('streams')) {
            Response::error('Stream limit reached. Upgrade your plan for more streams.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('name', 'type', 'platform')
            ->in('type', ['mentions', 'hashtag', 'keywords', 'scheduled'])
            ->in('platform', ['youtube', 'facebook', 'instagram'])
            ->maxLength('name', 100)
            ->maxLength('keywords', 500);

        $validator->validate();

        $streamId = Database::insert('streams', [
            'user_id' => $user['id'],
            'name' => trim($input['name']),
            'type' => $input['type'],
            'platform' => $input['platform'],
            'keywords' => $input['keywords'] ?? null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::created([
            'id' => $streamId,
            'name' => trim($input['name']),
            'type' => $input['type'],
            'platform' => $input['platform'],
        ]);
    }

    public static function show(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePlan('pro', 'team');

        $stream = Database::fetch(
            "SELECT * FROM streams WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$stream) {
            Response::notFound('Stream not found');
        }

        Response::success([
            'id' => (int)$stream['id'],
            'name' => $stream['name'],
            'type' => $stream['type'],
            'platform' => $stream['platform'],
            'keywords' => $stream['keywords'],
            'isActive' => (bool)$stream['is_active'],
            'createdAt' => $stream['created_at'],
        ]);
    }

    public static function items(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePlan('pro', 'team');

        $stream = Database::fetch(
            "SELECT * FROM streams WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$stream) {
            Response::notFound('Stream not found');
        }

        // For scheduled posts stream type
        if ($stream['type'] === 'scheduled') {
            $posts = Database::fetchAll(
                "SELECT p.id, p.content, p.scheduled_at, pp.platform
                 FROM posts p
                 JOIN post_platforms pp ON pp.post_id = p.id
                 WHERE p.user_id = ? AND p.status = 'scheduled' AND pp.platform = ?
                 ORDER BY p.scheduled_at ASC
                 LIMIT 50",
                [$user['id'], $stream['platform']]
            );

            $items = array_map(function ($post) use ($user) {
                return [
                    'id' => 'post_' . $post['id'],
                    'type' => 'scheduled_post',
                    'content' => $post['content'],
                    'authorName' => $user['name'],
                    'authorAvatar' => $user['avatar'],
                    'platform' => $post['platform'],
                    'createdAt' => $post['scheduled_at'],
                ];
            }, $posts);

            Response::success($items);
        }

        // For other stream types, fetch from stream_items table (populated by cron)
        $items = Database::fetchAll(
            "SELECT * FROM stream_items
             WHERE stream_id = ?
             ORDER BY created_at DESC
             LIMIT 50",
            [(int)$id]
        );

        $formatted = array_map(function ($item) {
            return [
                'id' => (int)$item['id'],
                'type' => $item['type'],
                'content' => $item['content'],
                'authorName' => $item['author_name'],
                'authorAvatar' => $item['author_avatar'],
                'platform' => $item['platform'],
                'platformPostId' => $item['platform_post_id'],
                'likes' => (int)($item['likes'] ?? 0),
                'comments' => (int)($item['comments'] ?? 0),
                'createdAt' => $item['created_at'],
            ];
        }, $items);

        Response::success($formatted);
    }

    public static function delete(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        AuthMiddleware::requirePlan('pro', 'team');

        $stream = Database::fetch(
            "SELECT id FROM streams WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$stream) {
            Response::notFound('Stream not found');
        }

        // Delete stream items first
        Database::delete('stream_items', 'stream_id = ?', [(int)$id]);

        // Delete stream
        Database::delete('streams', 'id = ?', [(int)$id]);

        Response::success(null, 'Stream deleted');
    }
}
