<?php

declare(strict_types=1);

class JWTService
{
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generateAccessToken(int $userId, string $email, string $plan = 'free'): string
    {
        return self::generate([
            'user_id' => $userId,
            'email' => $email,
            'plan' => $plan,
            'type' => 'access',
        ], JWT_ACCESS_EXPIRY);
    }

    public static function generateRefreshToken(int $userId): string
    {
        return self::generate([
            'user_id' => $userId,
            'type' => 'refresh',
        ], JWT_REFRESH_EXPIRY);
    }

    private static function generate(array $payload, int $expiry): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $payload['iss'] = APP_NAME;

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    public static function validate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET, true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!$payload) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function validateAccessToken(string $token): ?array
    {
        $payload = self::validate($token);
        if (!$payload || ($payload['type'] ?? '') !== 'access') {
            return null;
        }
        return $payload;
    }

    public static function validateRefreshToken(string $token): ?array
    {
        $payload = self::validate($token);
        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            return null;
        }
        return $payload;
    }

    public static function getTokenFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
