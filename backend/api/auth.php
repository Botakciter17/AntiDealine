<?php
/**
 * Auth API - Register, Login, User management
 */

function register(): void {
    $input = getJsonInput();
    
    if (empty($input['username']) || empty($input['password'])) {
        jsonResponse(['error' => 'Username and password are required'], 400);
    }

    $username = trim($input['username']);
    $password = password_hash($input['password'], PASSWORD_DEFAULT);

    $db = Database::getInstance()->getPdo();
    
    // Check if user exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Username already exists'], 409);
    }

    $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
    $stmt->execute([$username, $password]);
    
    $userId = (int) $db->lastInsertId();
    $token = generateToken($userId);

    jsonResponse([
        'token' => $token,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'api_key' => ''
        ]
    ], 201);
}

function login(): void {
    $input = getJsonInput();
    
    if (empty($input['username']) || empty($input['password'])) {
        jsonResponse(['error' => 'Username and password are required'], 400);
    }

    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([trim($input['username'])]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($input['password'], $user['password'])) {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }

    $token = generateToken($user['id']);

    jsonResponse([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'api_key' => $user['api_key'] ? '••••••' . substr($user['api_key'], -4) : ''
        ]
    ]);
}

function getUser(int $userId): void {
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT id, username, api_key FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['error' => 'User not found'], 404);
    }

    $user['api_key'] = $user['api_key'] ? '••••••' . substr($user['api_key'], -4) : '';
    jsonResponse(['user' => $user]);
}

function updateApiKey(int $userId): void {
    $input = getJsonInput();
    
    if (!isset($input['api_key'])) {
        jsonResponse(['error' => 'API key is required'], 400);
    }

    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('UPDATE users SET api_key = ? WHERE id = ?');
    $stmt->execute([$input['api_key'], $userId]);

    jsonResponse(['message' => 'API key updated successfully']);
}
