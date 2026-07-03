<?php
/**
 * Auth API - Register, Login, User management
 */

function register(): void {
    $input = getJsonInput();
    
    if (empty($input['username']) || empty($input['password']) || empty($input['email'])) {
        jsonResponse(['error' => 'Email, Username and password are required'], 400);
    }

    $email = trim($input['email']);
    $username = trim($input['username']);
    $password = password_hash($input['password'], PASSWORD_DEFAULT);

    $db = Database::getInstance()->getPdo();
    
    // Check if user exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Username or Email already exists'], 409);
    }

    $stmt = $db->prepare('INSERT INTO users (email, username, password) VALUES (?, ?, ?)');
    $stmt->execute([$email, $username, $password]);
    
    $userId = (int) $db->lastInsertId();
    $token = generateToken($userId);

    jsonResponse([
        'token' => $token,
        'user' => [
            'id' => $userId,
            'email' => $email,
            'username' => $username,
            'display_name' => '',
            'avatar' => '',
            'api_key' => ''
        ]
    ], 201);
}

function login(): void {
    $input = getJsonInput();
    
    $identifier = $input['email'] ?? $input['username'] ?? '';

    if (empty($identifier) || empty($input['password'])) {
        jsonResponse(['error' => 'Email/Username and password are required'], 400);
    }

    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([trim($identifier), trim($identifier)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($input['password'], $user['password'])) {
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }

    $token = generateToken($user['id']);

    jsonResponse([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'] ?? '',
            'username' => $user['username'],
            'display_name' => $user['display_name'] ?? '',
            'avatar' => $user['avatar'] ?? '',
            'api_key' => $user['api_key'] ? '••••••' . substr($user['api_key'], -4) : ''
        ]
    ]);
}

function getUser(int $userId): void {
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT id, username, display_name, avatar, api_key, whatsapp_number, whatsapp_verified FROM users WHERE id = ?');
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

function updateProfile(int $userId): void {
    $displayName = trim($_POST['display_name'] ?? '');
    
    $db = Database::getInstance()->getPdo();
    $avatarUrl = null;

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
                $avatarUrl = '/api/uploads/avatars/' . $filename;
            }
        }
    }

    if ($avatarUrl) {
        $stmt = $db->prepare('UPDATE users SET display_name = ?, avatar = ? WHERE id = ?');
        $stmt->execute([$displayName, $avatarUrl, $userId]);
    } else {
        $stmt = $db->prepare('UPDATE users SET display_name = ? WHERE id = ?');
        $stmt->execute([$displayName, $userId]);
        
        // Fetch existing avatar
        $stmt = $db->prepare('SELECT avatar FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $avatarUrl = $stmt->fetchColumn();
    }

    jsonResponse([
        'message' => 'Profile updated successfully',
        'display_name' => $displayName,
        'avatar' => $avatarUrl
    ]);
}

function googleLogin(): void {
    $input = getJsonInput();
    $credential = $input['credential'] ?? '';
    if (!$credential) jsonResponse(['error' => 'Missing Google credential'], 400);

    // Verify token with Google's public endpoint
    $verifyUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$response || $httpCode !== 200) {
        jsonResponse(['error' => 'Invalid Google token: ' . $response], 401);
    }
    
    $payload = json_decode($response, true);
    
    if (!isset($payload['sub']) || !isset($payload['aud'])) {
        jsonResponse(['error' => 'Invalid Google payload'], 401);
    }
    
    $clientId = '117711463661-kb9kehrd1csom0ls7gophcsfb00vobma.apps.googleusercontent.com';
    if ($payload['aud'] !== $clientId) {
        jsonResponse(['error' => 'Client ID mismatch'], 401);
    }

    $googleId = $payload['sub'];
    $email = $payload['email'] ?? '';
    $name = $payload['name'] ?? 'User';
    $picture = $payload['picture'] ?? '';
    
    $db = Database::getInstance()->getPdo();
    
    // Check if user exists by google_id
    $stmt = $db->prepare('SELECT * FROM users WHERE google_id = ?');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();
    
    if (!$user && $email) {
        // Fallback: check by email
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $stmt = $db->prepare('UPDATE users SET google_id = ? WHERE id = ?');
            $stmt->execute([$googleId, $user['id']]);
        }
    }
    
    if (!$user) {
        // Create new user with temp username
        $tempUsername = '_new_google_user_' . time() . rand(100, 999);
        $stmt = $db->prepare('INSERT INTO users (username, password, display_name, avatar, google_id, email) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$tempUsername, '', $name, $picture, $googleId, $email]);
        
        $userId = (int)$db->lastInsertId();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
    
    $token = generateToken($user['id']);
    
    jsonResponse([
        'message' => 'Google Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'avatar' => $user['avatar'],
            'email' => $user['email'],
            'api_key' => $user['api_key'] ? '••••••' . substr($user['api_key'], -4) : null
        ]
    ]);
}

function setUsername(int $userId): void {
    $input = getJsonInput();
    $newUsername = trim($input['username'] ?? '');

    if (strlen($newUsername) < 3) {
        jsonResponse(['error' => 'Username minimal 3 karakter'], 400);
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
        jsonResponse(['error' => 'Username hanya boleh huruf, angka, dan underscore'], 400);
    }

    $db = Database::getInstance()->getPdo();
    
    // Pastikan user ini masih pakai username temporary
    $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $currentUsername = $stmt->fetchColumn();

    $isTemp = strpos($currentUsername, '_new_google_user_') === 0;
    $isOldAuto = preg_match('/^[a-zA-Z0-9.]+?\d{4}$/', $currentUsername);

    if (!$isTemp && !$isOldAuto) {
        jsonResponse(['error' => 'Username sudah diatur dan tidak bisa diubah'], 400);
    }

    // Cek apakah username sudah dipakai
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$newUsername]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Username sudah digunakan, pilih yang lain'], 400);
    }

    $stmt = $db->prepare('UPDATE users SET username = ? WHERE id = ?');
    $stmt->execute([$newUsername, $userId]);

    jsonResponse([
        'message' => 'Username berhasil disimpan',
        'username' => $newUsername
    ]);
}
