<?php

/**
 * APEX PostMaster - Main Entry Point
 *
 * Routes requests to either the API or serves the Vue SPA
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// API requests - route to API handler
if (str_starts_with($path, '/api')) {
    $apiPath = substr($path, 4) ?: '/';
    $queryString = parse_url($uri, PHP_URL_QUERY);
    $_SERVER['REQUEST_URI'] = $queryString ? $apiPath . '?' . $queryString : $apiPath;
    require __DIR__ . '/api/index.php';
    exit;
}

// Static files in /app - serve directly if they exist
if (str_starts_with($path, '/app/')) {
    $filePath = __DIR__ . $path;
    if (is_file($filePath)) {
        // Get mime type
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];

        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }

        readfile($filePath);
        exit;
    }

    // SPA fallback - serve index.html for all /app/* routes
    $indexPath = __DIR__ . '/app/index.html';
    if (is_file($indexPath)) {
        header('Content-Type: text/html');
        readfile($indexPath);
        exit;
    }
}

// Root path - redirect to /app/ (Vue SPA)
if ($path === '/' || $path === '') {
    header('Location: /app/');
    exit;
}

// 404 for everything else
http_response_code(404);
echo "Not Found";
