<?php

declare(strict_types=1);

class AuthController
{
    public static function register(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('email', 'password', 'name')
            ->email('email')
            ->minLength('password', 8)
            ->minLength('name', 2)
            ->maxLength('name', 100)
            ->unique('email', 'users');

        $validator->validate();

        $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);

        $userId = Database::insert('users', [
            'email' => strtolower(trim($input['email'])),
            'password' => $passwordHash,
            'name' => trim($input['name']),
            'plan' => 'free',
            'onboarding_completed' => 0,
            'credit_balance_cents' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]);

        $accessToken = JWTService::generateAccessToken($userId, $user['email'], $user['plan']);
        $refreshToken = JWTService::generateRefreshToken($userId);

        // Store refresh token
        Database::insert('refresh_tokens', [
            'user_id' => $userId,
            'token' => hash('sha256', $refreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::created([
            'user' => self::formatUser($user),
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ], 'Registration successful');
    }

    public static function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('email', 'password')
            ->email('email');

        $validator->validate();

        $user = Database::fetch(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL",
            [strtolower(trim($input['email']))]
        );

        if (!$user || !password_verify($input['password'], $user['password'])) {
            Response::error('Invalid email or password', 401);
        }

        $accessToken = JWTService::generateAccessToken($user['id'], $user['email'], $user['plan']);
        $refreshToken = JWTService::generateRefreshToken($user['id']);

        // Store refresh token
        Database::insert('refresh_tokens', [
            'user_id' => $user['id'],
            'token' => hash('sha256', $refreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update last login
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        Response::success([
            'user' => self::formatUser($user),
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ], 'Login successful');
    }

    public static function refresh(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input['refreshToken'])) {
            Response::error('Refresh token is required', 400);
        }

        $payload = JWTService::validateRefreshToken($input['refreshToken']);
        if (!$payload) {
            Response::error('Invalid or expired refresh token', 401);
        }

        // Verify token exists in database
        $tokenHash = hash('sha256', $input['refreshToken']);
        $storedToken = Database::fetch(
            "SELECT * FROM refresh_tokens WHERE user_id = ? AND token = ? AND expires_at > NOW()",
            [$payload['user_id'], $tokenHash]
        );

        if (!$storedToken) {
            Response::error('Refresh token has been revoked', 401);
        }

        $user = Database::fetch("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$payload['user_id']]);
        if (!$user) {
            Response::error('User not found', 404);
        }

        // Generate new tokens
        $newAccessToken = JWTService::generateAccessToken($user['id'], $user['email'], $user['plan']);
        $newRefreshToken = JWTService::generateRefreshToken($user['id']);

        // Revoke old refresh token and create new one
        Database::delete('refresh_tokens', 'token = ?', [$tokenHash]);
        Database::insert('refresh_tokens', [
            'user_id' => $user['id'],
            'token' => hash('sha256', $newRefreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::success([
            'accessToken' => $newAccessToken,
            'refreshToken' => $newRefreshToken,
        ]);
    }

    public static function logout(): void
    {
        $user = AuthMiddleware::authenticate();

        // Revoke all refresh tokens for this user
        Database::delete('refresh_tokens', 'user_id = ?', [$user['id']]);

        Response::success(null, 'Logged out successfully');
    }

    public static function me(): void
    {
        $user = AuthMiddleware::authenticate();

        // Get usage stats
        $postsThisMonth = Database::fetch(
            "SELECT COUNT(*) as cnt FROM posts WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            [$user['id']]
        )['cnt'] ?? 0;

        $socialAccounts = Database::fetch(
            "SELECT COUNT(*) as cnt FROM social_accounts WHERE user_id = ? AND is_active = 1",
            [$user['id']]
        )['cnt'] ?? 0;

        $aiGenerations = Database::fetch(
            "SELECT COUNT(*) as cnt FROM ai_generations WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            [$user['id']]
        )['cnt'] ?? 0;

        $limits = PLAN_LIMITS[$user['plan']] ?? PLAN_LIMITS['free'];

        Response::success([
            'user' => self::formatUser($user),
            'usage' => [
                'posts' => [
                    'used' => (int)$postsThisMonth,
                    'limit' => $limits['posts_per_month'],
                ],
                'socialAccounts' => [
                    'used' => (int)$socialAccounts,
                    'limit' => $limits['social_accounts'],
                ],
                'aiGenerations' => [
                    'used' => (int)$aiGenerations,
                    'limit' => $limits['ai_generations'],
                ],
            ],
            'limits' => $limits,
        ]);
    }

    public static function updateProfile(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $updateData = [];

        if (isset($input['name'])) {
            $validator = Validator::make($input)->minLength('name', 2)->maxLength('name', 100);
            $validator->validate();
            $updateData['name'] = trim($input['name']);
        }

        if (isset($input['timezone'])) {
            $updateData['timezone'] = $input['timezone'];
        }

        if (isset($input['industry'])) {
            $updateData['industry'] = $input['industry'];
        }

        if (isset($input['onboardingCompleted'])) {
            $updateData['onboarding_completed'] = $input['onboardingCompleted'] ? 1 : 0;
        }

        if (empty($updateData)) {
            Response::error('No valid fields to update', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        Database::update('users', $updateData, 'id = ?', [$user['id']]);

        $updatedUser = Database::fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);

        Response::success(['user' => self::formatUser($updatedUser)], 'Profile updated');
    }

    public static function changePassword(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('currentPassword', 'newPassword')
            ->minLength('newPassword', 8);

        $validator->validate();

        // Get full user with password
        $fullUser = Database::fetch("SELECT password FROM users WHERE id = ?", [$user['id']]);

        if (!password_verify($input['currentPassword'], $fullUser['password'])) {
            Response::error('Current password is incorrect', 400);
        }

        $newPasswordHash = password_hash($input['newPassword'], PASSWORD_DEFAULT);

        Database::update('users', [
            'password' => $newPasswordHash,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        // Revoke all refresh tokens (force re-login)
        Database::delete('refresh_tokens', 'user_id = ?', [$user['id']]);

        Response::success(null, 'Password changed successfully');
    }

    public static function forgotPassword(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('email')
            ->email('email');

        $validator->validate();

        $user = Database::fetch(
            "SELECT id, email FROM users WHERE email = ? AND deleted_at IS NULL",
            [strtolower(trim($input['email']))]
        );

        // Always return success to prevent email enumeration
        if (!$user) {
            Response::success(null, 'If an account exists, a reset link has been sent');
        }

        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // Delete any existing reset tokens
        Database::delete('password_resets', 'user_id = ?', [$user['id']]);

        // Store new reset token
        Database::insert('password_resets', [
            'user_id' => $user['id'],
            'token' => hash('sha256', $resetToken),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // TODO: Send email with reset link
        // For now, just return success
        // In production, integrate with email service

        Response::success(null, 'If an account exists, a reset link has been sent');
    }

    public static function resetPassword(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('token', 'password')
            ->minLength('password', 8);

        $validator->validate();

        $tokenHash = hash('sha256', $input['token']);
        $reset = Database::fetch(
            "SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()",
            [$tokenHash]
        );

        if (!$reset) {
            Response::error('Invalid or expired reset token', 400);
        }

        $newPasswordHash = password_hash($input['password'], PASSWORD_DEFAULT);

        Database::update('users', [
            'password' => $newPasswordHash,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$reset['user_id']]);

        // Delete reset token
        Database::delete('password_resets', 'user_id = ?', [$reset['user_id']]);

        // Revoke all refresh tokens
        Database::delete('refresh_tokens', 'user_id = ?', [$reset['user_id']]);

        Response::success(null, 'Password reset successful');
    }

    public static function oauthInitiate(string $provider): void
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            Response::error('Unsupported OAuth provider', 400);
        }

        $redirectUri = match ($provider) {
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => GOOGLE_CLIENT_ID,
                'redirect_uri' => APP_URL . '/app/auth/callback/google',
                'response_type' => 'code',
                'scope' => 'email profile',
                'access_type' => 'offline',
                'prompt' => 'consent',
            ]),
            'facebook' => 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query([
                'client_id' => FACEBOOK_LOGIN_APP_ID,
                'redirect_uri' => APP_URL . '/app/auth/callback/facebook',
                'response_type' => 'code',
                'scope' => 'email public_profile',
            ]),
        };

        Response::success(['url' => $redirectUri]);
    }

    public static function oauthCallback(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('provider', 'code')
            ->in('provider', ['google', 'facebook']);

        $validator->validate();

        $provider = $input['provider'];
        $code = $input['code'];

        try {
            $userData = match ($provider) {
                'google' => self::handleGoogleOAuth($code),
                'facebook' => self::handleFacebookOAuth($code),
                default => throw new Exception('Unsupported provider'),
            };
        } catch (Exception $e) {
            Response::error('OAuth authentication failed: ' . $e->getMessage(), 400);
        }

        // Find or create user
        $user = Database::fetch(
            "SELECT * FROM users WHERE email = ? AND deleted_at IS NULL",
            [strtolower($userData['email'])]
        );

        if (!$user) {
            // Create new user
            $userId = Database::insert('users', [
                'email' => strtolower($userData['email']),
                'name' => $userData['name'],
                'avatar' => $userData['avatar'] ?? null,
                'password' => '', // OAuth users don't have passwords
                'plan' => 'free',
                'onboarding_completed' => 0,
                'credit_balance_cents' => 0,
                'oauth_provider' => $provider,
                'oauth_id' => $userData['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        } else {
            // Update OAuth info
            Database::update('users', [
                'oauth_provider' => $provider,
                'oauth_id' => $userData['id'],
                'avatar' => $userData['avatar'] ?? $user['avatar'],
                'last_login_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$user['id']]);

            $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
        }

        $accessToken = JWTService::generateAccessToken($user['id'], $user['email'], $user['plan']);
        $refreshToken = JWTService::generateRefreshToken($user['id']);

        Database::insert('refresh_tokens', [
            'user_id' => $user['id'],
            'token' => hash('sha256', $refreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Response::success([
            'user' => self::formatUser($user),
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
        ], 'OAuth login successful');
    }

    private static function handleGoogleOAuth(string $code): array
    {
        // Exchange code for token
        $tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'code' => $code,
                    'client_id' => GOOGLE_CLIENT_ID,
                    'client_secret' => GOOGLE_CLIENT_SECRET,
                    'redirect_uri' => APP_URL . '/app/auth/callback/google',
                    'grant_type' => 'authorization_code',
                ]),
            ],
        ]));

        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            throw new Exception('Failed to get access token');
        }

        // Get user info
        $userResponse = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create([
            'http' => [
                'header' => 'Authorization: Bearer ' . $tokenData['access_token'],
            ],
        ]));

        $userData = json_decode($userResponse, true);
        if (empty($userData['email'])) {
            throw new Exception('Failed to get user info');
        }

        return [
            'id' => $userData['id'],
            'email' => $userData['email'],
            'name' => $userData['name'] ?? '',
            'avatar' => $userData['picture'] ?? null,
        ];
    }

    private static function handleFacebookOAuth(string $code): array
    {
        // Exchange code for token (using Facebook Login app credentials)
        $tokenUrl = 'https://graph.facebook.com/v21.0/oauth/access_token?' . http_build_query([
            'client_id' => FACEBOOK_LOGIN_APP_ID,
            'client_secret' => FACEBOOK_LOGIN_APP_SECRET,
            'code' => $code,
            'redirect_uri' => APP_URL . '/app/auth/callback/facebook',
        ]);

        $tokenResponse = file_get_contents($tokenUrl);
        $tokenData = json_decode($tokenResponse, true);

        if (empty($tokenData['access_token'])) {
            throw new Exception('Failed to get access token');
        }

        // Get user info
        $userUrl = 'https://graph.facebook.com/me?' . http_build_query([
            'fields' => 'id,email,name,picture.width(200).height(200)',
            'access_token' => $tokenData['access_token'],
        ]);

        $userResponse = file_get_contents($userUrl);
        $userData = json_decode($userResponse, true);

        if (empty($userData['email'])) {
            throw new Exception('Failed to get user email');
        }

        return [
            'id' => $userData['id'],
            'email' => $userData['email'],
            'name' => $userData['name'] ?? '',
            'avatar' => $userData['picture']['data']['url'] ?? null,
        ];
    }

    private static function formatUser(array $user): array
    {
        $role = $user['role'] ?? 'user';
        return [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'avatar' => $user['avatar'],
            'plan' => $user['plan'],
            'timezone' => $user['timezone'],
            'industry' => $user['industry'],
            'onboardingCompleted' => (bool)$user['onboarding_completed'],
            'role' => $role,
            'isAdmin' => $role === 'admin',
            'creditBalanceCents' => (int)($user['credit_balance_cents'] ?? 0),
            'createdAt' => $user['created_at'],
        ];
    }
}
