<?php

declare(strict_types=1);

class PlatformsController
{
    public static function oauthInitiate(string $platform): void
    {
        // Get token from query param
        $token = $_GET['token'] ?? null;

        if (!$token) {
            Response::error('Authentication token required', 401);
        }

        // Validate token first
        $payload = JWTService::validateAccessToken($token);
        if (!$payload) {
            Response::error('Invalid authentication token', 401);
        }

        if (!in_array($platform, ['youtube', 'facebook', 'instagram'])) {
            Response::error('Unsupported platform', 400);
        }

        // Check connection limit before starting OAuth
        $userId = $payload['user_id'];
        $userPlan = $payload['plan'] ?? 'free';
        $limit = PLAN_LIMITS[$userPlan]['social_accounts'] ?? 2;

        $currentCount = Database::fetch(
            "SELECT COUNT(*) as cnt FROM social_accounts WHERE user_id = ? AND is_active = 1",
            [$userId]
        )['cnt'] ?? 0;

        if ($currentCount >= $limit) {
            header('Location: ' . APP_URL . '/app/settings/platforms?error=' . urlencode('Connection limit reached. Upgrade your plan to connect more accounts.'));
            exit;
        }

        $redirectUri = APP_URL . '/api/platforms/callback/' . $platform;

        // Encode token in state parameter (will be returned by OAuth provider)
        $state = base64_encode(json_encode(['token' => $token]));

        $oauthUrl = match ($platform) {
            'youtube' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => GOOGLE_CLIENT_ID,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $state,
            ]),
            'facebook' => 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query([
                'client_id' => FACEBOOK_APP_ID,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'business_management,pages_manage_posts,pages_read_engagement,pages_read_user_content',
                'state' => $state,
            ]),
            'instagram' => 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query([
                'client_id' => FACEBOOK_APP_ID,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'business_management,instagram_basic,instagram_content_publish,pages_read_engagement',
                'state' => $state,
            ]),
        };

        header('Location: ' . $oauthUrl);
        exit;
    }

    public static function oauthCallback(string $platform): void
    {
        $code = $_GET['code'] ?? null;
        $error = $_GET['error'] ?? null;
        $state = $_GET['state'] ?? null;

        if ($error || !$code) {
            header('Location: ' . APP_URL . '/app/settings/platforms?error=oauth_denied');
            exit;
        }

        // Restore auth from state parameter
        if (!$state) {
            header('Location: ' . APP_URL . '/app/settings/platforms?error=missing_state');
            exit;
        }

        $stateData = json_decode(base64_decode($state), true);
        $token = $stateData['token'] ?? null;

        if (!$token) {
            header('Location: ' . APP_URL . '/app/settings/platforms?error=invalid_state');
            exit;
        }

        // Verify token and get user
        $payload = JWTService::validateAccessToken($token);
        if (!$payload) {
            header('Location: ' . APP_URL . '/app/settings/platforms?error=invalid_token');
            exit;
        }
        $userId = $payload['user_id'];

        // Exchange code for token
        try {
            $tokenData = match ($platform) {
                'youtube' => [self::exchangeYouTubeToken($code)], // Wrap in array for consistency
                'facebook' => self::exchangeFacebookToken($code, 'facebook'), // Returns array of pages
                'instagram' => self::exchangeInstagramToken($code), // Returns array with FB pages + Instagram accounts
                default => throw new Exception('Unsupported platform'),
            };
        } catch (Exception $e) {
            header('Location: ' . APP_URL . '/app/settings/platforms?error=' . urlencode($e->getMessage()));
            exit;
        }

        // Get user's plan limit
        $user = Database::fetch("SELECT plan FROM users WHERE id = ?", [$userId]);
        $userPlan = $user['plan'] ?? 'free';
        $limit = PLAN_LIMITS[$userPlan]['social_accounts'] ?? 2;

        // Get current active account count
        $currentCount = (int)(Database::fetch(
            "SELECT COUNT(*) as cnt FROM social_accounts WHERE user_id = ? AND is_active = 1",
            [$userId]
        )['cnt'] ?? 0);

        $savedCount = 0;
        $skippedDueToLimit = 0;

        // Save all accounts (pages/channels)
        foreach ($tokenData as $account) {
            // Use platform from account if provided (for Instagram flow which saves both FB and IG)
            // Otherwise use the $platform from the URL
            $accountPlatform = $account['platform'] ?? $platform;

            // Check if account already exists (active or inactive)
            $existing = Database::fetch(
                "SELECT id, is_active FROM social_accounts WHERE user_id = ? AND platform = ? AND platform_user_id = ?",
                [$userId, $accountPlatform, $account['userId']]
            );

            if ($existing) {
                // If reactivating an inactive account, check the limit first
                $isReactivating = !$existing['is_active'];

                if ($isReactivating && $currentCount >= $limit) {
                    $skippedDueToLimit++;
                    continue;
                }

                // Update existing account
                Database::update('social_accounts', [
                    'access_token' => $account['accessToken'],
                    'refresh_token' => $account['refreshToken'] ?? null,
                    'token_expires_at' => $account['expiresAt'] ?? null,
                    'platform_username' => $account['username'],
                    'platform_display_name' => $account['displayName'],
                    'platform_avatar' => $account['avatar'],
                    'is_active' => 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$existing['id']]);

                if ($isReactivating) {
                    $currentCount++;
                }
                $savedCount++;
            } else {
                // Check limit before adding new account
                if ($currentCount >= $limit) {
                    $skippedDueToLimit++;
                    continue;
                }

                Database::insert('social_accounts', [
                    'user_id' => $userId,
                    'platform' => $accountPlatform,
                    'platform_user_id' => $account['userId'],
                    'platform_username' => $account['username'],
                    'platform_display_name' => $account['displayName'],
                    'platform_avatar' => $account['avatar'],
                    'access_token' => $account['accessToken'],
                    'refresh_token' => $account['refreshToken'] ?? null,
                    'token_expires_at' => $account['expiresAt'] ?? null,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $currentCount++;
                $savedCount++;
            }
        }

        // Build redirect URL with appropriate message
        $redirectParams = ['success' => 'connected'];
        if ($skippedDueToLimit > 0) {
            $redirectParams = ['warning' => urlencode("Connected {$savedCount} account(s). {$skippedDueToLimit} skipped due to plan limit.")];
        }

        header('Location: ' . APP_URL . '/app/settings/platforms?' . http_build_query($redirectParams));
        exit;
    }

    public static function index(): void
    {
        $user = AuthMiddleware::authenticate();

        $accounts = Database::fetchAll(
            "SELECT id, platform, platform_user_id, platform_username, platform_display_name,
                    platform_avatar, is_active, token_expires_at, created_at
             FROM social_accounts
             WHERE user_id = ? AND is_active = 1
             ORDER BY created_at DESC",
            [$user['id']]
        );

        $formatted = array_map(function ($account) {
            return [
                'id' => (int)$account['id'],
                'platform' => $account['platform'],
                'platformUserId' => $account['platform_user_id'],
                'platformUsername' => $account['platform_username'],
                'platformDisplayName' => $account['platform_display_name'],
                'platformAvatar' => $account['platform_avatar'],
                'isActive' => (bool)$account['is_active'],
                'tokenExpiresAt' => $account['token_expires_at'],
                'createdAt' => $account['created_at'],
            ];
        }, $accounts);

        Response::success($formatted);
    }

    public static function connect(): void
    {
        $user = AuthMiddleware::authenticate();

        // Check usage limit
        if (!AuthMiddleware::checkUsageLimit('social_accounts')) {
            Response::error('Social account limit reached. Upgrade your plan for more accounts.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('platform', 'code')
            ->in('platform', ['youtube', 'facebook', 'instagram']);

        $validator->validate();

        $platform = $input['platform'];
        $code = $input['code'];

        try {
            $tokenData = match ($platform) {
                'youtube' => self::exchangeYouTubeToken($code),
                'facebook' => self::exchangeFacebookToken($code),
                'instagram' => self::exchangeInstagramToken($code),
                default => throw new Exception('Unsupported platform'),
            };
        } catch (Exception $e) {
            Response::error('Failed to connect platform: ' . $e->getMessage(), 400);
        }

        // Check if account already connected
        $existing = Database::fetch(
            "SELECT id FROM social_accounts WHERE user_id = ? AND platform = ? AND platform_user_id = ?",
            [$user['id'], $platform, $tokenData['userId']]
        );

        if ($existing) {
            // Update existing account
            Database::update('social_accounts', [
                'access_token' => $tokenData['accessToken'],
                'refresh_token' => $tokenData['refreshToken'] ?? null,
                'token_expires_at' => $tokenData['expiresAt'] ?? null,
                'platform_username' => $tokenData['username'],
                'platform_display_name' => $tokenData['displayName'],
                'platform_avatar' => $tokenData['avatar'],
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$existing['id']]);

            $accountId = $existing['id'];
        } else {
            // Create new account
            $accountId = Database::insert('social_accounts', [
                'user_id' => $user['id'],
                'platform' => $platform,
                'platform_user_id' => $tokenData['userId'],
                'platform_username' => $tokenData['username'],
                'platform_display_name' => $tokenData['displayName'],
                'platform_avatar' => $tokenData['avatar'],
                'access_token' => $tokenData['accessToken'],
                'refresh_token' => $tokenData['refreshToken'] ?? null,
                'token_expires_at' => $tokenData['expiresAt'] ?? null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $account = Database::fetch("SELECT * FROM social_accounts WHERE id = ?", [$accountId]);

        Response::success([
            'id' => (int)$account['id'],
            'platform' => $account['platform'],
            'platformUserId' => $account['platform_user_id'],
            'platformUsername' => $account['platform_username'],
            'platformDisplayName' => $account['platform_display_name'],
            'platformAvatar' => $account['platform_avatar'],
            'isActive' => true,
        ], 'Platform connected');
    }

    public static function disconnect(string $id): void
    {
        $user = AuthMiddleware::authenticate();

        $account = Database::fetch(
            "SELECT id FROM social_accounts WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$account) {
            Response::notFound('Account not found');
        }

        Database::update('social_accounts', [
            'is_active' => 0,
            'access_token' => null,
            'refresh_token' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$id]);

        Response::success(null, 'Platform disconnected');
    }

    public static function update(string $id): void
    {
        $user = AuthMiddleware::authenticate();

        $account = Database::fetch(
            "SELECT id FROM social_accounts WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$account) {
            Response::notFound('Account not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $updateData = [];

        if (isset($input['isActive'])) {
            $updateData['is_active'] = $input['isActive'] ? 1 : 0;
        }

        if (empty($updateData)) {
            Response::error('No valid fields to update', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        Database::update('social_accounts', $updateData, 'id = ?', [(int)$id]);

        Response::success(null, 'Account updated');
    }

    public static function refreshToken(string $id): void
    {
        $user = AuthMiddleware::authenticate();

        $account = Database::fetch(
            "SELECT * FROM social_accounts WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$account) {
            Response::notFound('Account not found');
        }

        if (!$account['refresh_token']) {
            Response::error('No refresh token available', 400);
        }

        try {
            $newTokenData = match ($account['platform']) {
                'youtube' => self::refreshYouTubeToken($account['refresh_token']),
                'facebook' => self::refreshFacebookToken($account['access_token']),
                default => throw new Exception('Token refresh not supported'),
            };
        } catch (Exception $e) {
            Response::error('Failed to refresh token: ' . $e->getMessage(), 400);
        }

        Database::update('social_accounts', [
            'access_token' => $newTokenData['accessToken'],
            'refresh_token' => $newTokenData['refreshToken'] ?? $account['refresh_token'],
            'token_expires_at' => $newTokenData['expiresAt'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int)$id]);

        Response::success(null, 'Token refreshed');
    }

    private static function exchangeYouTubeToken(string $code): array
    {
        $response = self::httpPost('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => APP_URL . '/api/platforms/callback/youtube',
            'grant_type' => 'authorization_code',
        ]);

        if (empty($response['access_token'])) {
            throw new Exception('Failed to get access token');
        }

        // Get channel info
        $channelResponse = self::httpGet(
            'https://www.googleapis.com/youtube/v3/channels?part=snippet&mine=true',
            ['Authorization: Bearer ' . $response['access_token']]
        );

        // Debug: check for API errors
        if (isset($channelResponse['error'])) {
            throw new Exception('YouTube API error: ' . ($channelResponse['error']['message'] ?? json_encode($channelResponse['error'])));
        }

        $channel = $channelResponse['items'][0] ?? null;
        if (!$channel) {
            // If no channel with mine=true, the account might not have a channel yet
            // or we need different scopes
            throw new Exception('No YouTube channel found. Make sure you have a YouTube channel created and granted access.');
        }

        return [
            'accessToken' => $response['access_token'],
            'refreshToken' => $response['refresh_token'] ?? null,
            'expiresAt' => isset($response['expires_in'])
                ? date('Y-m-d H:i:s', time() + $response['expires_in'])
                : null,
            'userId' => $channel['id'],
            'username' => $channel['snippet']['customUrl'] ?? $channel['id'],
            'displayName' => $channel['snippet']['title'],
            'avatar' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
        ];
    }

    private static function exchangeFacebookToken(string $code, string $platform = 'facebook'): array
    {
        $redirectUri = APP_URL . '/api/platforms/callback/' . $platform;

        $url = 'https://graph.facebook.com/v21.0/oauth/access_token?' . http_build_query([
            'client_id' => FACEBOOK_APP_ID,
            'client_secret' => FACEBOOK_APP_SECRET,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $response = self::httpGet($url);

        if (empty($response['access_token'])) {
            $errorMsg = $response['error']['message'] ?? 'Failed to get access token';
            throw new Exception($errorMsg);
        }

        // Exchange for long-lived token
        $longLivedUrl = 'https://graph.facebook.com/v21.0/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => FACEBOOK_APP_ID,
            'client_secret' => FACEBOOK_APP_SECRET,
            'fb_exchange_token' => $response['access_token'],
        ]);

        $longLivedResponse = self::httpGet($longLivedUrl);
        $accessToken = $longLivedResponse['access_token'] ?? $response['access_token'];

        // Get pages with pictures
        $pagesUrl = 'https://graph.facebook.com/v21.0/me/accounts?fields=id,name,access_token,picture&access_token=' . $accessToken;
        $pagesResponse = self::httpGet($pagesUrl);

        if (empty($pagesResponse['data'])) {
            // No pages, get user profile instead
            $userUrl = 'https://graph.facebook.com/v21.0/me?fields=id,name,picture&access_token=' . $accessToken;
            $userResponse = self::httpGet($userUrl);

            return [[
                'accessToken' => $accessToken,
                'refreshToken' => null,
                'expiresAt' => isset($longLivedResponse['expires_in'])
                    ? date('Y-m-d H:i:s', time() + $longLivedResponse['expires_in'])
                    : null,
                'userId' => $userResponse['id'],
                'username' => $userResponse['id'],
                'displayName' => $userResponse['name'],
                'avatar' => $userResponse['picture']['data']['url'] ?? null,
            ]];
        }

        // Return ALL pages
        $pages = [];
        foreach ($pagesResponse['data'] as $page) {
            $pages[] = [
                'accessToken' => $page['access_token'],
                'refreshToken' => null,
                'expiresAt' => null, // Page tokens don't expire
                'userId' => $page['id'],
                'username' => $page['id'],
                'displayName' => $page['name'],
                'avatar' => $page['picture']['data']['url'] ?? null,
            ];
        }

        return $pages;
    }

    private static function exchangeInstagramToken(string $code): array
    {
        // Instagram uses Facebook's API - get all pages first (pass 'instagram' as platform for correct redirect_uri)
        $fbPages = self::exchangeFacebookToken($code, 'instagram');

        if (empty($fbPages)) {
            throw new Exception('No Facebook Pages found');
        }

        $results = [];
        $instagramFound = false;

        // Loop through all pages, find Instagram accounts, and include FB pages too
        foreach ($fbPages as $fbPage) {
            // Add Facebook page to results (marked as facebook platform)
            $results[] = [
                'platform' => 'facebook',
                'accessToken' => $fbPage['accessToken'],
                'refreshToken' => null,
                'expiresAt' => $fbPage['expiresAt'],
                'userId' => $fbPage['userId'],
                'username' => $fbPage['username'],
                'displayName' => $fbPage['displayName'],
                'avatar' => $fbPage['avatar'],
            ];

            // Check if this page has an Instagram Business Account linked
            $igUrl = "https://graph.facebook.com/v21.0/{$fbPage['userId']}?fields=instagram_business_account&access_token={$fbPage['accessToken']}";
            $igResponse = self::httpGet($igUrl);

            if (!empty($igResponse['instagram_business_account']['id'])) {
                $igId = $igResponse['instagram_business_account']['id'];

                // Get Instagram account info
                $igInfoUrl = "https://graph.facebook.com/v21.0/{$igId}?fields=username,name,profile_picture_url&access_token={$fbPage['accessToken']}";
                $igInfo = self::httpGet($igInfoUrl);

                $results[] = [
                    'platform' => 'instagram',
                    'accessToken' => $fbPage['accessToken'],
                    'refreshToken' => null,
                    'expiresAt' => $fbPage['expiresAt'],
                    'userId' => $igId,
                    'username' => $igInfo['username'] ?? $igId,
                    'displayName' => $igInfo['name'] ?? $igInfo['username'] ?? 'Instagram',
                    'avatar' => $igInfo['profile_picture_url'] ?? null,
                ];
                $instagramFound = true;
            }
        }

        if (!$instagramFound) {
            throw new Exception('No Instagram Business Account linked to any of your Facebook Pages. Link an Instagram Business account to your Facebook Page first.');
        }

        return $results;
    }

    private static function refreshYouTubeToken(string $refreshToken): array
    {
        $response = self::httpPost('https://oauth2.googleapis.com/token', [
            'refresh_token' => $refreshToken,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'grant_type' => 'refresh_token',
        ]);

        if (empty($response['access_token'])) {
            throw new Exception('Failed to refresh token');
        }

        return [
            'accessToken' => $response['access_token'],
            'refreshToken' => $response['refresh_token'] ?? null,
            'expiresAt' => isset($response['expires_in'])
                ? date('Y-m-d H:i:s', time() + $response['expires_in'])
                : null,
        ];
    }

    private static function refreshFacebookToken(string $accessToken): array
    {
        // Facebook long-lived tokens can be refreshed by exchanging again
        $url = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'grant_type' => 'fb_exchange_token',
            'client_id' => FACEBOOK_APP_ID,
            'client_secret' => FACEBOOK_APP_SECRET,
            'fb_exchange_token' => $accessToken,
        ]);

        $response = self::httpGet($url);

        if (empty($response['access_token'])) {
            throw new Exception('Failed to refresh token');
        }

        return [
            'accessToken' => $response['access_token'],
            'expiresAt' => isset($response['expires_in'])
                ? date('Y-m-d H:i:s', time() + $response['expires_in'])
                : null,
        ];
    }

    private static function httpPost(string $url, array $data): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        return json_decode($response, true) ?? [];
    }

    private static function httpGet(string $url, array $headers = []): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        return json_decode($response, true) ?? [];
    }
}
