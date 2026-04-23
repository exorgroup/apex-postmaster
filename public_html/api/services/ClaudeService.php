<?php

declare(strict_types=1);

class ClaudeService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public static function createPost(string $topic, string $platform, ?string $tone = null, ?int $maxLength = null): array
    {
        $systemPrompt = self::getSystemPrompt('create', $platform);

        $userPrompt = "Create a social media post about: $topic";
        if ($tone) {
            $userPrompt .= "\nTone: $tone";
        }
        if ($maxLength) {
            $userPrompt .= "\nMaximum length: $maxLength characters";
        }

        return self::chat($systemPrompt, $userPrompt);
    }

    public static function optimizePost(string $content, string $platform, ?string $goal = null): array
    {
        $systemPrompt = self::getSystemPrompt('optimize', $platform);

        $userPrompt = "Optimize this post for better engagement:\n\n$content";
        if ($goal) {
            $userPrompt .= "\n\nOptimization goal: $goal";
        }

        return self::chat($systemPrompt, $userPrompt);
    }

    public static function generateHashtags(string $content, string $platform, int $count = 10): array
    {
        $systemPrompt = self::getSystemPrompt('hashtags', $platform);

        $userPrompt = "Generate $count relevant hashtags for this content:\n\n$content";

        $result = self::chat($systemPrompt, $userPrompt);

        // Parse hashtags from response
        if ($result['success']) {
            preg_match_all('/#\w+/', $result['content'], $matches);
            $result['hashtags'] = $matches[0] ?? [];
        }

        return $result;
    }

    public static function scorePost(string $content, string $platform): array
    {
        $systemPrompt = self::getSystemPrompt('score', $platform);

        $userPrompt = "Score this post and provide improvement suggestions:\n\n$content";

        $result = self::chat($systemPrompt, $userPrompt);

        // Try to extract score from response
        if ($result['success']) {
            preg_match('/(\d+)(?:\/100|%| out of 100)/', $result['content'], $matches);
            $result['score'] = isset($matches[1]) ? (int)$matches[1] : null;
        }

        return $result;
    }

    public static function campaignChat(array $messages, ?string $context = null): array
    {
        $systemPrompt = "You are an expert social media campaign strategist. Help users plan and execute effective social media campaigns.

Key responsibilities:
- Understand their business goals and target audience
- Suggest content strategies and posting schedules
- Recommend platform-specific tactics
- Help create content calendars
- Provide analytics insights and optimization tips

Be conversational but professional. Ask clarifying questions when needed. Provide actionable advice.";

        if ($context) {
            $systemPrompt .= "\n\nAdditional context: $context";
        }

        return self::chatWithHistory($systemPrompt, $messages);
    }

    private static function chat(string $systemPrompt, string $userPrompt): array
    {
        return self::chatWithHistory($systemPrompt, [
            ['role' => 'user', 'content' => $userPrompt]
        ]);
    }

    private static function chatWithHistory(string $systemPrompt, array $messages): array
    {
        if (empty(CLAUDE_API_KEY)) {
            return [
                'success' => false,
                'error' => 'Claude API key not configured',
            ];
        }

        $payload = [
            'model' => CLAUDE_MODEL,
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'x-api-key: ' . CLAUDE_API_KEY,
                    'anthropic-version: ' . self::API_VERSION,
                ]),
                'content' => json_encode($payload),
                'ignore_errors' => true,
                'timeout' => 60,
            ],
        ]);

        $response = file_get_contents(self::API_URL, false, $context);
        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'API error',
            ];
        }

        if (!isset($data['content'][0]['text'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        // Calculate tokens for cost estimation
        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        return [
            'success' => true,
            'content' => $data['content'][0]['text'],
            'inputTokens' => $inputTokens,
            'outputTokens' => $outputTokens,
            'model' => CLAUDE_MODEL,
        ];
    }

    private static function getSystemPrompt(string $type, string $platform): string
    {
        $platformGuidelines = self::getPlatformGuidelines($platform);

        return match ($type) {
            'create' => "You are an expert social media content creator. Create engaging, platform-appropriate content.

Platform: $platform
$platformGuidelines

Requirements:
- Write in a natural, engaging tone
- Include relevant emojis where appropriate
- Follow character limits and best practices
- Make content shareable and engaging
- DO NOT include hashtags unless specifically asked

Respond with ONLY the post content, nothing else.",

            'optimize' => "You are an expert social media optimizer. Improve posts for maximum engagement.

Platform: $platform
$platformGuidelines

Optimization focus:
- Improve hook/opening line
- Enhance call-to-action
- Optimize for platform algorithm
- Improve readability and engagement
- Keep the core message intact

Respond with ONLY the optimized post, nothing else.",

            'hashtags' => "You are a social media hashtag expert. Generate relevant, effective hashtags.

Platform: $platform
$platformGuidelines

Hashtag strategy:
- Mix popular and niche hashtags
- Ensure relevance to content
- Consider searchability
- Avoid banned or spammy hashtags

Respond with ONLY the hashtags, one per line.",

            'score' => "You are a social media content analyst. Score and analyze posts.

Platform: $platform
$platformGuidelines

Scoring criteria (out of 100):
- Engagement potential (25 points)
- Clarity and readability (25 points)
- Call-to-action strength (20 points)
- Platform optimization (15 points)
- Visual/emoji usage (15 points)

Provide:
1. Overall score (X/100)
2. Breakdown by category
3. 3 specific improvement suggestions",

            default => "You are a helpful social media assistant.",
        };
    }

    private static function getPlatformGuidelines(string $platform): string
    {
        return match ($platform) {
            'youtube' => "YouTube Guidelines:
- Community posts: max 500 characters
- Encourage subscriptions and engagement
- Use questions to drive comments
- Include relevant call-to-actions",

            'facebook' => "Facebook Guidelines:
- Optimal post length: 40-80 characters for engagement
- Questions and polls perform well
- Native video gets more reach
- Avoid excessive links in posts",

            'instagram' => "Instagram Guidelines:
- Caption length: up to 2,200 characters
- First 125 characters are crucial (shown before 'more')
- Use line breaks for readability
- Hashtags: 3-5 in caption, rest in comments
- Stories and Reels get priority reach",

            default => "General social media best practices apply.",
        };
    }
}
