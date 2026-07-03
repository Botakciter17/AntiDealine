<?php
/**
 * Direct Messages API
 */

function getDms(int $userId): void {
    $targetId = $_GET['user_id'] ?? null;
    if (!$targetId) jsonResponse(['error' => 'user_id required'], 400);
    
    $db = Database::getInstance()->getPdo();
    
    $stmt = $db->prepare('
        SELECT id, sender_id, receiver_id, content, created_at
        FROM direct_messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY id ASC
    ');
    $stmt->execute([$userId, $targetId, $targetId, $userId]);
    jsonResponse(['messages' => $stmt->fetchAll()]);
}

function sendDm(int $userId): void {
    $input = getJsonInput();
    $targetId = $input['receiver_id'] ?? null;
    $content = $input['content'] ?? '';
    
    if (!$targetId || empty($content)) {
        jsonResponse(['error' => 'Data tidak lengkap'], 400);
    }
    
    $db = Database::getInstance()->getPdo();
    
    // Validate friendship
    $stmt = $db->prepare('
        SELECT status FROM friendships 
        WHERE ((user_id1 = ? AND user_id2 = ?) OR (user_id1 = ? AND user_id2 = ?))
        AND status = "accepted"
    ');
    $stmt->execute([$userId, $targetId, $targetId, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Hanya bisa mengirim pesan ke teman'], 403);
    }
    
    $stmt = $db->prepare('INSERT INTO direct_messages (sender_id, receiver_id, content) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $targetId, $content]);
    
    jsonResponse(['message' => 'Pesan terkirim', 'message_id' => $db->lastInsertId()]);
}

function deleteDm(int $userId): void {
    $messageId = $_GET['id'] ?? null;
    if (!$messageId) jsonResponse(['error' => 'Message ID required'], 400);

    $db = Database::getInstance()->getPdo();
    
    // Check if message belongs to user and was sent less than 5 minutes ago
    $stmt = $db->prepare("SELECT sender_id, created_at FROM direct_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    
    if (!$msg) jsonResponse(['error' => 'Pesan tidak ditemukan'], 404);
    if ($msg['sender_id'] != $userId) jsonResponse(['error' => 'Hanya bisa menghapus pesan sendiri'], 403);
    
    $createdAt = strtotime($msg['created_at']);
    $now = time();
    $diffMinutes = ($now - $createdAt) / 60;
    
    if ($diffMinutes > 5) {
        jsonResponse(['error' => 'Pesan sudah lebih dari 5 menit, tidak bisa dihapus'], 403);
    }
    
    // Delete message
    $stmt = $db->prepare('DELETE FROM direct_messages WHERE id = ?');
    $stmt->execute([$messageId]);
    
    jsonResponse(['message' => 'Pesan berhasil dihapus']);
}
