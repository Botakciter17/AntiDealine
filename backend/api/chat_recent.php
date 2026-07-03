<?php
function getRecentChats(int $userId): void {
    $db = Database::getInstance()->getPdo();
    
    // Get recent DMs
    $stmt = $db->prepare('
        SELECT 
            u.id as id, 
            u.username as name, 
            "dm" as type,
            (
                SELECT content FROM direct_messages 
                WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) 
                ORDER BY created_at DESC LIMIT 1
            ) as last_message,
            (
                SELECT created_at FROM direct_messages 
                WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?) 
                ORDER BY created_at DESC LIMIT 1
            ) as last_message_time
        FROM friendships f
        JOIN users u ON (f.user_id1 = u.id OR f.user_id2 = u.id) AND u.id != ?
        WHERE (f.user_id1 = ? OR f.user_id2 = ?) AND f.status = "accepted"
    ');
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $dms = $stmt->fetchAll();

    // Get recent Groups
    $stmt = $db->prepare('
        SELECT 
            g.id as id, 
            g.name as name, 
            "group" as type,
            (
                SELECT content FROM group_messages 
                WHERE group_id = g.id 
                ORDER BY created_at DESC LIMIT 1
            ) as last_message,
            (
                SELECT created_at FROM group_messages 
                WHERE group_id = g.id 
                ORDER BY created_at DESC LIMIT 1
            ) as last_message_time
        FROM group_members gm
        JOIN groups g ON gm.group_id = g.id
        WHERE gm.user_id = ?
    ');
    $stmt->execute([$userId]);
    $groups = $stmt->fetchAll();

    $chats = array_merge($dms, $groups);

    // Sort by last_message_time descending
    usort($chats, function($a, $b) {
        $timeA = $a['last_message_time'] ? strtotime($a['last_message_time']) : 0;
        $timeB = $b['last_message_time'] ? strtotime($b['last_message_time']) : 0;
        return $timeB - $timeA;
    });

    jsonResponse(['chats' => $chats]);
}
