<?php

declare(strict_types=1);

class Response
{
    public static function json(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function created(mixed $data = null, string $message = 'Created'): never
    {
        self::success($data, $message, 201);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): never
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    public static function notFound(string $message = 'Resource not found'): never
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error($message, 403);
    }

    public static function validationError(array $errors): never
    {
        self::error('Validation failed', 422, $errors);
    }

    public static function serverError(string $message = 'Internal server error'): never
    {
        self::error($message, 500);
    }

    public static function paginated(array $data, int $total, int $page, int $perPage): never
    {
        self::json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }
}
