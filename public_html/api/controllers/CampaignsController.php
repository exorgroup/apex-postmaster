<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/ClaudeService.php';

class CampaignsController
{
    public static function listChats(): void
    {
        $user = AuthMiddleware::authenticate();

        $chats = Database::fetchAll(
            "SELECT id, title, created_at, updated_at
             FROM campaign_chats
             WHERE user_id = ?
             ORDER BY updated_at DESC",
            [$user['id']]
        );

        $formatted = array_map(function ($chat) {
            return [
                'id' => (int)$chat['id'],
                'title' => $chat['title'],
                'createdAt' => $chat['created_at'],
                'updatedAt' => $chat['updated_at'],
            ];
        }, $chats);

        Response::success($formatted);
    }

    public static function createChat(): void
    {
        $user = AuthMiddleware::authenticate();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $chatId = Database::insert('campaign_chats', [
            'user_id' => $user['id'],
            'title' => $input['title'] ?? 'New Campaign',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Response::created([
            'id' => $chatId,
            'title' => $input['title'] ?? 'New Campaign',
            'createdAt' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function getChat(string $id): void
    {
        $user = AuthMiddleware::authenticate();

        $chat = Database::fetch(
            "SELECT * FROM campaign_chats WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$chat) {
            Response::notFound('Chat not found');
        }

        Response::success([
            'id' => (int)$chat['id'],
            'title' => $chat['title'],
            'createdAt' => $chat['created_at'],
            'updatedAt' => $chat['updated_at'],
        ]);
    }

    public static function getMessages(string $id): void
    {
        $user = AuthMiddleware::authenticate();

        // Verify chat ownership
        $chat = Database::fetch(
            "SELECT id FROM campaign_chats WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$chat) {
            Response::notFound('Chat not found');
        }

        $messages = Database::fetchAll(
            "SELECT id, role, content, cost_cents, created_at
             FROM campaign_messages
             WHERE chat_id = ?
             ORDER BY created_at ASC",
            [(int)$id]
        );

        $formatted = array_map(function ($msg) {
            return [
                'id' => (int)$msg['id'],
                'role' => $msg['role'],
                'content' => $msg['content'],
                'costCents' => (int)$msg['cost_cents'],
                'createdAt' => $msg['created_at'],
            ];
        }, $messages);

        Response::success($formatted);
    }

    public static function sendMessage(string $id): void
    {
        $user = AuthMiddleware::authenticate();

        // Verify chat ownership
        $chat = Database::fetch(
            "SELECT id, title FROM campaign_chats WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$chat) {
            Response::notFound('Chat not found');
        }

        // Check credits
        if ($user['credit_balance_cents'] < AI_COST_CAMPAIGN_MESSAGE) {
            Response::error('Insufficient credits. Please top up to continue.', 403);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validator = Validator::make($input)
            ->required('content')
            ->maxLength('content', 5000);

        $validator->validate();

        // Save user message
        $userMessageId = Database::insert('campaign_messages', [
            'chat_id' => (int)$id,
            'role' => 'user',
            'content' => $input['content'],
            'cost_cents' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Get conversation history
        $history = Database::fetchAll(
            "SELECT role, content FROM campaign_messages WHERE chat_id = ? ORDER BY created_at ASC",
            [(int)$id]
        );

        // Format for Claude API
        $messages = array_map(function ($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }, $history);

        // Get AI response
        $result = ClaudeService::campaignChat($messages);

        if (!$result['success']) {
            Response::error('Failed to generate response: ' . $result['error'], 500);
        }

        // Save assistant message
        $assistantMessageId = Database::insert('campaign_messages', [
            'chat_id' => (int)$id,
            'role' => 'assistant',
            'content' => $result['content'],
            'cost_cents' => AI_COST_CAMPAIGN_MESSAGE,
            'input_tokens' => $result['inputTokens'] ?? 0,
            'output_tokens' => $result['outputTokens'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Deduct credits
        $newBalance = $user['credit_balance_cents'] - AI_COST_CAMPAIGN_MESSAGE;
        Database::update('users', ['credit_balance_cents' => $newBalance], 'id = ?', [$user['id']]);

        // Log credit transaction
        Database::insert('credit_transactions', [
            'user_id' => $user['id'],
            'amount_cents' => -AI_COST_CAMPAIGN_MESSAGE,
            'type' => 'campaign_chat',
            'description' => 'Campaign planner message',
            'balance_after' => $newBalance,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update chat title if first message
        if ($chat['title'] === 'New Campaign' && count($messages) <= 2) {
            $newTitle = substr($input['content'], 0, 50);
            if (strlen($input['content']) > 50) {
                $newTitle .= '...';
            }
            Database::update('campaign_chats', [
                'title' => $newTitle,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int)$id]);
        } else {
            Database::update('campaign_chats', [
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [(int)$id]);
        }

        Response::success([
            'message' => [
                'id' => $assistantMessageId,
                'role' => 'assistant',
                'content' => $result['content'],
                'costCents' => AI_COST_CAMPAIGN_MESSAGE,
                'createdAt' => date('Y-m-d H:i:s'),
            ],
            'balanceCents' => $newBalance,
        ]);
    }

    public static function deleteChat(string $id): void
    {
        $user = AuthMiddleware::authenticate();

        $chat = Database::fetch(
            "SELECT id FROM campaign_chats WHERE id = ? AND user_id = ?",
            [(int)$id, $user['id']]
        );

        if (!$chat) {
            Response::notFound('Chat not found');
        }

        // Delete messages first
        Database::delete('campaign_messages', 'chat_id = ?', [(int)$id]);

        // Delete chat
        Database::delete('campaign_chats', 'id = ?', [(int)$id]);

        Response::success(null, 'Chat deleted');
    }
}
