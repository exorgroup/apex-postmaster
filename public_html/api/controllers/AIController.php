<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/ClaudeService.php';

class AIController
{
    public static function createPost(): void
    {
        $user = AuthMiddleware::authenticate();

        // Check usage limit
        if (!AuthMiddleware::checkUsageLimit('ai_generations')) {
            Response::error('Monthly AI generation limit reached. Upgrade your plan for more.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('topic', 'platform')
            ->in('platform', ['youtube', 'facebook', 'instagram'])
            ->maxLength('topic', 500);

        $validator->validate();

        $result = ClaudeService::createPost(
            $input['topic'],
            $input['platform'],
            $input['tone'] ?? null,
            $input['maxLength'] ?? null
        );

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        // Log AI generation
        self::logGeneration($user['id'], 'create', $input, $result);

        // Deduct credits if using credits system
        self::deductCredits($user['id'], AI_COST_CREATE_POST);

        Response::success([
            'content' => $result['content'],
            'platform' => $input['platform'],
            'costCents' => AI_COST_CREATE_POST,
        ]);
    }

    public static function optimizePost(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!AuthMiddleware::checkUsageLimit('ai_generations')) {
            Response::error('Monthly AI generation limit reached. Upgrade your plan for more.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('content', 'platform')
            ->in('platform', ['youtube', 'facebook', 'instagram'])
            ->maxLength('content', 5000);

        $validator->validate();

        $result = ClaudeService::optimizePost(
            $input['content'],
            $input['platform'],
            $input['goal'] ?? null
        );

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        self::logGeneration($user['id'], 'optimize', $input, $result);
        self::deductCredits($user['id'], AI_COST_OPTIMIZE_POST);

        Response::success([
            'content' => $result['content'],
            'original' => $input['content'],
            'platform' => $input['platform'],
            'costCents' => AI_COST_OPTIMIZE_POST,
        ]);
    }

    public static function generateHashtags(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!AuthMiddleware::checkUsageLimit('ai_generations')) {
            Response::error('Monthly AI generation limit reached. Upgrade your plan for more.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('content', 'platform')
            ->in('platform', ['youtube', 'facebook', 'instagram'])
            ->maxLength('content', 5000);

        $validator->validate();

        $count = min(30, max(5, (int)($input['count'] ?? 10)));

        $result = ClaudeService::generateHashtags(
            $input['content'],
            $input['platform'],
            $count
        );

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        self::logGeneration($user['id'], 'hashtags', $input, $result);
        self::deductCredits($user['id'], AI_COST_HASHTAGS);

        Response::success([
            'hashtags' => $result['hashtags'] ?? [],
            'raw' => $result['content'],
            'platform' => $input['platform'],
            'costCents' => AI_COST_HASHTAGS,
        ]);
    }

    public static function scorePost(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!AuthMiddleware::checkUsageLimit('ai_generations')) {
            Response::error('Monthly AI generation limit reached. Upgrade your plan for more.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('content', 'platform')
            ->in('platform', ['youtube', 'facebook', 'instagram'])
            ->maxLength('content', 5000);

        $validator->validate();

        $result = ClaudeService::scorePost(
            $input['content'],
            $input['platform']
        );

        if (!$result['success']) {
            Response::error($result['error'], 500);
        }

        self::logGeneration($user['id'], 'score', $input, $result);
        self::deductCredits($user['id'], AI_COST_SCORE_POST);

        Response::success([
            'score' => $result['score'],
            'analysis' => $result['content'],
            'platform' => $input['platform'],
            'costCents' => AI_COST_SCORE_POST,
        ]);
    }

    private static function logGeneration(int $userId, string $type, array $input, array $result): void
    {
        Database::insert('ai_generations', [
            'user_id' => $userId,
            'type' => $type,
            'input' => json_encode($input),
            'output' => $result['content'] ?? null,
            'input_tokens' => $result['inputTokens'] ?? 0,
            'output_tokens' => $result['outputTokens'] ?? 0,
            'model' => $result['model'] ?? CLAUDE_MODEL,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function deductCredits(int $userId, int $costCents): void
    {
        // Only deduct credits if user is on free plan (paid plans have included generations)
        $user = Database::fetch("SELECT plan, credit_balance_cents FROM users WHERE id = ?", [$userId]);

        if ($user['plan'] === 'free' && $user['credit_balance_cents'] > 0) {
            $newBalance = max(0, $user['credit_balance_cents'] - $costCents);

            Database::update('users', [
                'credit_balance_cents' => $newBalance,
            ], 'id = ?', [$userId]);

            // Log transaction
            Database::insert('credit_transactions', [
                'user_id' => $userId,
                'amount_cents' => -$costCents,
                'type' => 'ai_usage',
                'description' => 'AI generation',
                'balance_after' => $newBalance,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
