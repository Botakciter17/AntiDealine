<?php
/**
 * AntiDeadline Configuration
 */

define('DB_PATH', __DIR__ . '/data/antideadline.db');
define('TOKEN_SECRET', 'antideadline_secret_' . md5(__DIR__));
define('OPENROUTER_API_URL', 'http://localhost:20128/v1/chat/completions');

/**
 * Get JSON input from request body
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Generate a simple token
 */
function generateToken(int $userId): string {
    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'exp' => time() + (86400 * 30), // 30 days
        'sig' => hash('sha256', $userId . TOKEN_SECRET)
    ]));
    return $payload;
}

/**
 * Verify and decode token
 */
function verifyToken(string $token): ?int {
    $payload = json_decode(base64_decode($token), true);
    if (!$payload) return null;
    
    if ($payload['exp'] < time()) return null;
    
    $expectedSig = hash('sha256', $payload['user_id'] . TOKEN_SECRET);
    if (!hash_equals($expectedSig, $payload['sig'])) return null;
    
    return $payload['user_id'];
}

/**
 * Authenticate request from Authorization header
 */
function authenticateRequest(): ?int {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    
    $token = substr($authHeader, 7);
    return verifyToken($token);
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
