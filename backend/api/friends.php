<?php
/**
 * Friends API - Manage friendships
 */

function getFriends(int $userId): void {
    $db = Database::getInstance()->getPdo();
    
    // Get accepted friends
    $stmt = $db->prepare('
        SELECT u.id, u.username, u.display_name, u.avatar, f.status 
        FROM friendships f
        JOIN users u ON (f.user_id1 = u.id OR f.user_id2 = u.id)
        WHERE (f.user_id1 = ? OR f.user_id2 = ?) 
        AND u.id != ? AND f.status = "accepted"
    ');
    $stmt->execute([$userId, $userId, $userId]);
    $friends = $stmt->fetchAll();
    
    // Get pending incoming requests
    $stmt = $db->prepare('
        SELECT u.id, u.username, u.display_name, u.avatar, f.id as request_id 
        FROM friendships f
        JOIN users u ON f.user_id1 = u.id
        WHERE f.user_id2 = ? AND f.status = "pending"
    ');
    $stmt->execute([$userId]);
    $pendingRequests = $stmt->fetchAll();
    
    jsonResponse([
        'friends' => $friends,
        'pending_requests' => $pendingRequests
    ]);
}

function sendRequest(int $userId): void {
    $input = getJsonInput();
    $targetUsername = $input['username'] ?? '';
    
    if (empty($targetUsername)) {
        jsonResponse(['error' => 'Username tidak boleh kosong'], 400);
    }
    
    $db = Database::getInstance()->getPdo();
    
    // Find target user
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$targetUsername]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        jsonResponse(['error' => 'Pengguna tidak ditemukan'], 404);
    }
    
    $targetId = $targetUser['id'];
    
    if ($targetId === $userId) {
        jsonResponse(['error' => 'Tidak bisa menambahkan diri sendiri'], 400);
    }
    
    // Check if friendship already exists
    $stmt = $db->prepare('SELECT status FROM friendships WHERE (user_id1 = ? AND user_id2 = ?) OR (user_id1 = ? AND user_id2 = ?)');
    $stmt->execute([$userId, $targetId, $targetId, $userId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['status'] === 'accepted') {
            jsonResponse(['error' => 'Sudah berteman'], 400);
        } else {
            jsonResponse(['error' => 'Permintaan pertemanan sudah ada (pending)'], 400);
        }
    }
    
    // Insert new request
    $stmt = $db->prepare('INSERT INTO friendships (user_id1, user_id2, status) VALUES (?, ?, "pending")');
    $stmt->execute([$userId, $targetId]);
    
    jsonResponse(['message' => 'Permintaan pertemanan berhasil dikirim']);
}

function respondRequest(int $userId): void {
    $input = getJsonInput();
    $requestId = $input['request_id'] ?? null;
    $action = $input['action'] ?? ''; // 'accept' or 'reject'
    
    if (!$requestId || !in_array($action, ['accept', 'reject'])) {
        jsonResponse(['error' => 'Data tidak valid'], 400);
    }
    
    $db = Database::getInstance()->getPdo();
    
    // Verify request is for this user
    $stmt = $db->prepare('SELECT id FROM friendships WHERE id = ? AND user_id2 = ? AND status = "pending"');
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        jsonResponse(['error' => 'Permintaan tidak ditemukan'], 404);
    }
    
    if ($action === 'accept') {
        $stmt = $db->prepare('UPDATE friendships SET status = "accepted" WHERE id = ?');
        $stmt->execute([$requestId]);
        jsonResponse(['message' => 'Permintaan diterima']);
    } else {
        $stmt = $db->prepare('DELETE FROM friendships WHERE id = ?');
        $stmt->execute([$requestId]);
        jsonResponse(['message' => 'Permintaan ditolak']);
    }
}
