<?php

declare(strict_types=1);

class PostsController
{
    public static function index(): void
    {
        $user = AuthMiddleware::authenticate();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $status = $_GET['status'] ?? null;
        $platform = $_GET['platform'] ?? null;
        $search = $_GET['search'] ?? null;

        $where = ['p.user_id = ?'];
        $params = [$user['id']];

        if ($status) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        if ($platform) {
            $where[] = 'EXISTS (SELECT 1 FROM post_platforms pp WHERE pp.post_id = p.id AND pp.platform = ?)';
            $params[] = $platform;
        }

        if ($search) {
            $where[] = 'p.content LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $total = Database::fetch(
            "SELECT COUNT(*) as cnt FROM posts p WHERE $whereClause",
            $params
        )['cnt'] ?? 0;

        // Get posts
        $posts = Database::fetchAll(
            "SELECT p.*, GROUP_CONCAT(pp.platform) as platforms
             FROM posts p
             LEFT JOIN post_platforms pp ON pp.post_id = p.id
             WHERE $whereClause
             GROUP BY p.id
             ORDER BY p.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        $formattedPosts = array_map([self::class, 'formatPost'], $posts);

        Response::paginated($formattedPosts, (int)$total, $page, $perPage);
    }

    public static function create(): void
    {
        $user = AuthMiddleware::authenticate();

        // Check usage limit
        if (!AuthMiddleware::checkUsageLimit('posts_per_month')) {
            Response::error('Monthly post limit reached. Upgrade your plan for more posts.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('content')
            ->maxLength('content', 5000)
            ->array('platforms');

        if (isset($input['scheduledAt'])) {
            $validator->datetime('scheduledAt');
        }

        $validator->validate();

        $status = $input['status'] ?? 'draft';
        if ($status === 'scheduled' && empty($input['scheduledAt'])) {
            Response::error('Scheduled posts require a scheduledAt datetime', 400);
        }

        $postId = Database::insert('posts', [
            'user_id' => $user['id'],
            'content' => $input['content'],
            'status' => $status,
            'scheduled_at' => $input['scheduledAt'] ?? null,
            'media_urls' => isset($input['mediaUrls']) ? json_encode($input['mediaUrls']) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Add platforms
        if (!empty($input['platforms'])) {
            foreach ($input['platforms'] as $platform) {
                Database::insert('post_platforms', [
                    'post_id' => $postId,
                    'platform' => $platform,
                    'status' => 'pending',
                ]);
            }
        }

        $post = self::getPostById($postId);

        Response::created(self::formatPost($post), 'Post created');
    }

    public static function show(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        $post = self::getPostById((int)$id);

        if (!$post || $post['user_id'] !== $user['id']) {
            Response::notFound('Post not found');
        }

        Response::success(self::formatPost($post));
    }

    public static function update(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        $post = self::getPostById((int)$id);

        if (!$post || $post['user_id'] !== $user['id']) {
            Response::notFound('Post not found');
        }

        if ($post['status'] === 'published') {
            Response::error('Cannot edit published posts', 400);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $updateData = [];

        if (isset($input['content'])) {
            $validator = Validator::make($input)->maxLength('content', 5000);
            $validator->validate();
            $updateData['content'] = $input['content'];
        }

        if (isset($input['status'])) {
            $validator = Validator::make($input)->in('status', ['draft', 'scheduled', 'published']);
            $validator->validate();
            $updateData['status'] = $input['status'];
        }

        if (isset($input['scheduledAt'])) {
            $validator = Validator::make($input)->datetime('scheduledAt');
            $validator->validate();
            $updateData['scheduled_at'] = $input['scheduledAt'];
        }

        if (isset($input['mediaUrls'])) {
            $updateData['media_urls'] = json_encode($input['mediaUrls']);
        }

        if (empty($updateData)) {
            Response::error('No valid fields to update', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        Database::update('posts', $updateData, 'id = ?', [(int)$id]);

        // Update platforms if provided
        if (isset($input['platforms'])) {
            Database::delete('post_platforms', 'post_id = ?', [(int)$id]);
            foreach ($input['platforms'] as $platform) {
                Database::insert('post_platforms', [
                    'post_id' => (int)$id,
                    'platform' => $platform,
                    'status' => 'pending',
                ]);
            }
        }

        $post = self::getPostById((int)$id);

        Response::success(self::formatPost($post), 'Post updated');
    }

    public static function delete(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        $post = self::getPostById((int)$id);

        if (!$post || $post['user_id'] !== $user['id']) {
            Response::notFound('Post not found');
        }

        Database::delete('post_platforms', 'post_id = ?', [(int)$id]);
        Database::delete('posts', 'id = ?', [(int)$id]);

        Response::success(null, 'Post deleted');
    }

    public static function schedule(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        $post = self::getPostById((int)$id);

        if (!$post || $post['user_id'] !== $user['id']) {
            Response::notFound('Post not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('scheduledAt')
            ->datetime('scheduledAt');

        $validator->validate();

        $scheduledTime = strtotime($input['scheduledAt']);
        if ($scheduledTime < time()) {
            Response::error('Scheduled time must be in the future', 400);
        }

        Database::update('posts', [
            'status' => 'scheduled',
            'scheduled_at' => $input['scheduledAt'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$id]);

        $post = self::getPostById((int)$id);

        Response::success(self::formatPost($post), 'Post scheduled');
    }

    public static function publishNow(string $id): void
    {
        $user = AuthMiddleware::authenticate();
        $post = self::getPostById((int)$id);

        if (!$post || $post['user_id'] !== $user['id']) {
            Response::notFound('Post not found');
        }

        if ($post['status'] === 'published') {
            Response::error('Post is already published', 400);
        }

        // Get platforms for this post
        $platforms = Database::fetchAll(
            "SELECT pp.*, sa.access_token, sa.platform_user_id
             FROM post_platforms pp
             JOIN social_accounts sa ON sa.user_id = ? AND sa.platform = pp.platform AND sa.is_active = 1
             WHERE pp.post_id = ?",
            [$user['id'], (int)$id]
        );

        if (empty($platforms)) {
            Response::error('No connected platforms for this post', 400);
        }

        $errors = [];
        $success = [];

        foreach ($platforms as $platform) {
            try {
                $result = self::publishToPlatform($post, $platform);
                if ($result['success']) {
                    Database::update('post_platforms', [
                        'status' => 'published',
                        'platform_post_id' => $result['postId'] ?? null,
                        'published_at' => date('Y-m-d H:i:s'),
                    ], 'post_id = ? AND platform = ?', [(int)$id, $platform['platform']]);
                    $success[] = $platform['platform'];
                } else {
                    Database::update('post_platforms', [
                        'status' => 'failed',
                        'error_message' => $result['error'],
                    ], 'post_id = ? AND platform = ?', [(int)$id, $platform['platform']]);
                    $errors[$platform['platform']] = $result['error'];
                }
            } catch (Exception $e) {
                Database::update('post_platforms', [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ], 'post_id = ? AND platform = ?', [(int)$id, $platform['platform']]);
                $errors[$platform['platform']] = $e->getMessage();
            }
        }

        // Update main post status
        $allSuccess = empty($errors);
        $allFailed = empty($success);

        Database::update('posts', [
            'status' => $allFailed ? 'failed' : 'published',
            'published_at' => $allFailed ? null : date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$id]);

        $post = self::getPostById((int)$id);

        if ($allFailed) {
            Response::error('Publishing failed on all platforms', 500, $errors);
        }

        Response::success([
            'post' => self::formatPost($post),
            'success' => $success,
            'errors' => $errors,
        ], $allSuccess ? 'Post published' : 'Post partially published');
    }

    public static function calendar(): void
    {
        $user = AuthMiddleware::authenticate();

        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-t');

        $posts = Database::fetchAll(
            "SELECT p.*, GROUP_CONCAT(pp.platform) as platforms
             FROM posts p
             LEFT JOIN post_platforms pp ON pp.post_id = p.id
             WHERE p.user_id = ?
               AND ((p.scheduled_at BETWEEN ? AND ?) OR (p.published_at BETWEEN ? AND ?))
             GROUP BY p.id
             ORDER BY COALESCE(p.scheduled_at, p.published_at) ASC",
            [$user['id'], $start, $end, $start, $end]
        );

        $events = array_map(function ($post) {
            return [
                'id' => (int)$post['id'],
                'title' => substr($post['content'], 0, 50) . (strlen($post['content']) > 50 ? '...' : ''),
                'start' => $post['scheduled_at'] ?? $post['published_at'],
                'status' => $post['status'],
                'platforms' => $post['platforms'] ? explode(',', $post['platforms']) : [],
            ];
        }, $posts);

        Response::success($events);
    }

    private static function getPostById(int $id): ?array
    {
        return Database::fetch(
            "SELECT p.*, GROUP_CONCAT(pp.platform) as platforms
             FROM posts p
             LEFT JOIN post_platforms pp ON pp.post_id = p.id
             WHERE p.id = ?
             GROUP BY p.id",
            [$id]
        );
    }

    private static function formatPost(array $post): array
    {
        return [
            'id' => (int)$post['id'],
            'content' => $post['content'],
            'status' => $post['status'],
            'platforms' => $post['platforms'] ? explode(',', $post['platforms']) : [],
            'mediaUrls' => $post['media_urls'] ? json_decode($post['media_urls'], true) : [],
            'scheduledAt' => $post['scheduled_at'],
            'publishedAt' => $post['published_at'],
            'createdAt' => $post['created_at'],
            'updatedAt' => $post['updated_at'],
        ];
    }

    private static function publishToPlatform(array $post, array $platform): array
    {
        $platformName = $platform['platform'];

        switch ($platformName) {
            case 'youtube':
                return self::publishToYouTube($post, $platform);
            case 'facebook':
                return self::publishToFacebook($post, $platform);
            case 'instagram':
                return self::publishToInstagram($post, $platform);
            default:
                return ['success' => false, 'error' => 'Unsupported platform'];
        }
    }

    private static function publishToYouTube(array $post, array $platform): array
    {
        // YouTube community posts API
        // Note: This requires YouTube Partner Program membership
        $accessToken = $platform['access_token'];

        $response = self::httpPost(
            'https://www.googleapis.com/youtube/v3/activities?part=snippet',
            [
                'snippet' => [
                    'type' => 'bulletin',
                    'description' => $post['content'],
                ],
            ],
            ['Authorization: Bearer ' . $accessToken]
        );

        if (isset($response['id'])) {
            return ['success' => true, 'postId' => $response['id']];
        }

        return ['success' => false, 'error' => $response['error']['message'] ?? 'Unknown error'];
    }

    private static function publishToFacebook(array $post, array $platform): array
    {
        $accessToken = $platform['access_token'];
        $pageId = $platform['platform_user_id'];

        $url = "https://graph.facebook.com/v18.0/{$pageId}/feed";

        $data = ['message' => $post['content']];

        // Add media if present
        $mediaUrls = $post['media_urls'] ? json_decode($post['media_urls'], true) : [];
        if (!empty($mediaUrls)) {
            // For photos, use /photos endpoint instead
            $url = "https://graph.facebook.com/v18.0/{$pageId}/photos";
            $data['url'] = $mediaUrls[0];
            $data['caption'] = $post['content'];
        }

        $response = self::httpPost($url . '?access_token=' . $accessToken, $data);

        if (isset($response['id'])) {
            return ['success' => true, 'postId' => $response['id']];
        }

        return ['success' => false, 'error' => $response['error']['message'] ?? 'Unknown error'];
    }

    private static function publishToInstagram(array $post, array $platform): array
    {
        $accessToken = $platform['access_token'];
        $igUserId = $platform['platform_user_id'];

        $mediaUrls = $post['media_urls'] ? json_decode($post['media_urls'], true) : [];

        if (empty($mediaUrls)) {
            return ['success' => false, 'error' => 'Instagram requires media'];
        }

        // Step 1: Create media container
        $containerUrl = "https://graph.facebook.com/v18.0/{$igUserId}/media";
        $containerData = [
            'image_url' => $mediaUrls[0],
            'caption' => $post['content'],
            'access_token' => $accessToken,
        ];

        $container = self::httpPost($containerUrl, $containerData);

        if (!isset($container['id'])) {
            return ['success' => false, 'error' => $container['error']['message'] ?? 'Failed to create container'];
        }

        // Step 2: Publish the container
        $publishUrl = "https://graph.facebook.com/v18.0/{$igUserId}/media_publish";
        $publishData = [
            'creation_id' => $container['id'],
            'access_token' => $accessToken,
        ];

        $result = self::httpPost($publishUrl, $publishData);

        if (isset($result['id'])) {
            return ['success' => true, 'postId' => $result['id']];
        }

        return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to publish'];
    }

    private static function httpPost(string $url, array $data, array $headers = []): array
    {
        $headers[] = 'Content-Type: application/json';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        return json_decode($response, true) ?? [];
    }
}
