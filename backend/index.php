<?php
/**
 * AntiDeadline API - Main Router
 * Simple PHP router for the task management API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Initialize database
$db = Database::getInstance();

// Get request info
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove base path if behind a subdirectory
$basePath = '/api';
if (strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Simple router
$routes = [
    'POST /auth/register' => 'api/auth.php@register',
    'POST /auth/login' => 'api/auth.php@login',
    'GET /user' => 'api/auth.php@getUser',
    'PUT /user/apikey' => 'api/auth.php@updateApiKey',
    'POST /chat' => 'api/chat.php@chat',
    'GET /chat/history' => 'api/chat.php@getHistory',
    'GET /chat/sessions' => 'api/chat.php@getSessions',
    'DELETE /chat/sessions' => 'api/chat.php@deleteSession',
    'GET /tasks' => 'api/tasks.php@getTasks',
    'POST /tasks' => 'api/tasks.php@createTask',
    'PUT /tasks' => 'api/tasks.php@updateTask',
    'DELETE /tasks' => 'api/tasks.php@deleteTask',
];

$routeKey = "$method $uri";

if (isset($routes[$routeKey])) {
    [$file, $function] = explode('@', $routes[$routeKey]);
    require_once __DIR__ . '/' . $file;
    
    // Auth middleware for protected routes
    if ($routeKey !== 'POST /auth/register' && $routeKey !== 'POST /auth/login') {
        $userId = authenticateRequest();
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $function($userId);
    } else {
        $function();
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Route not found', 'path' => $uri]);
}
