<?php
/**
 * AntiDeadline API - Router for PHP built-in server
 * Run: php -S localhost:8080 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static files if they exist
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route /api/* requests to index.php
if (strpos($uri, '/api') === 0) {
    // Strip /api prefix for the router
    $_SERVER['REQUEST_URI'] = $uri;
    require __DIR__ . '/index.php';
    return;
}

// 404 for everything else
http_response_code(404);
echo json_encode(['error' => 'Not found']);
