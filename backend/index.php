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

// Serve static files for uploads
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($uri, '/api/uploads/') === 0) {
    // allow subdirectories like avatars/
    $relativePath = substr($uri, strlen('/api/uploads/'));
    $filePath = __DIR__ . '/uploads/' . $relativePath;
    if (file_exists($filePath)) {
        $mime = mime_content_type($filePath);
        header("Content-Type: $mime");
        
        // Force download if it's not an avatar and not specifically requested inline
        if (strpos($relativePath, 'avatars/') !== 0) {
            $filename = basename($filePath);
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }
        
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    exit;
}

header('Content-Type: application/json');

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
    'POST /auth/google' => 'api/auth.php@googleLogin',
    'GET /user' => 'api/auth.php@getUser',
    'POST /user/username' => 'api/auth.php@setUsername',
    'PUT /user/apikey' => 'api/auth.php@updateApiKey',
    'POST /user/profile' => 'api/auth.php@updateProfile',
    'POST /chat' => 'api/chat.php@chat',
    'GET /chat/history' => 'api/chat.php@getHistory',
    'GET /chat/sessions' => 'api/chat.php@getSessions',
    'DELETE /chat/sessions' => 'api/chat.php@deleteSession',
    'GET /chat/recent' => 'api/chat_recent.php@getRecentChats',
    
    // Friends
    'GET /friends' => 'api/friends.php@getFriends',
    'POST /friends/request' => 'api/friends.php@sendRequest',
    'POST /friends/respond' => 'api/friends.php@respondRequest',

    // Direct Messages
    'GET /dm' => 'api/dm.php@getDms',
    'POST /dm' => 'api/dm.php@sendDm',

    // Groups
    'GET /groups' => 'api/groups.php@getGroups',
    'POST /groups' => 'api/groups.php@createGroup',
    'POST /groups/update' => 'api/groups.php@updateGroup',
    'GET /groups/messages' => 'api/groups.php@getGroupMessages',
    'POST /groups/messages' => 'api/groups.php@sendGroupMessage',
    'POST /groups/messages/approve' => 'api/groups.php@approveProgress',
    'DELETE /groups/messages' => 'api/groups.php@deleteGroupMessage',
    'POST /groups/tasks' => 'api/groups.php@createGroupTask',
    'POST /groups/reset' => 'api/groups.php@resetGroupProgress',
    'DELETE /groups' => 'api/groups.php@deleteGroup',
    'GET /groups/members' => 'api/groups.php@getGroupMembers',
    'POST /groups/members/add' => 'api/groups.php@addGroupMember',
    'DELETE /groups/members' => 'api/groups.php@leaveGroup',
    'POST /groups/members/kick' => 'api/groups.php@kickMember',
    'POST /groups/members/role' => 'api/groups.php@changeMemberRole',

    'GET /dm' => 'api/dm.php@getDms',
    'POST /dm' => 'api/dm.php@sendDm',
    'DELETE /dm' => 'api/dm.php@deleteDm',

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
    $publicRoutes = ['POST /auth/register', 'POST /auth/login', 'POST /auth/google'];
    if (!in_array($routeKey, $publicRoutes)) {
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
