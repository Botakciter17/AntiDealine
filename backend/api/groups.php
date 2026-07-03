<?php
/**
 * Groups API - Manage groups and group chat
 */

function getGroups(int $userId): void {
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('
        SELECT g.id, g.name, g.avatar, g.created_at, g.progress, gm.role
        FROM groups g
        JOIN group_members gm ON g.id = gm.group_id
        WHERE gm.user_id = ?
    ');
    $stmt->execute([$userId]);
    jsonResponse(['groups' => $stmt->fetchAll()]);
}

function createGroup(int $userId): void {
    $input = getJsonInput();
    $name = $input['name'] ?? '';
    $members = $input['members'] ?? []; // array of user_ids
    
    if (empty($name)) {
        jsonResponse(['error' => 'Nama grup tidak boleh kosong'], 400);
    }
    
    $db = Database::getInstance()->getPdo();
    $db->beginTransaction();
    
    try {
        $stmt = $db->prepare('INSERT INTO groups (name, created_by) VALUES (?, ?)');
        $stmt->execute([$name, $userId]);
        $groupId = $db->lastInsertId();
        
        // Add creator as admin
        $stmt = $db->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, "admin")');
        $stmt->execute([$groupId, $userId]);
        
        // Add other members
        if (is_array($members)) {
            $stmt = $db->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, "member")');
            foreach ($members as $memberId) {
                if ($memberId != $userId) {
                    $stmt->execute([$groupId, $memberId]);
                }
            }
        }
        
        // Add system message
        $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
        $stmt->execute([$groupId, "Grup '$name' telah dibuat."]);
        
        $db->commit();
        jsonResponse(['message' => 'Grup berhasil dibuat', 'group_id' => $groupId]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Gagal membuat grup: ' . $e->getMessage()], 500);
    }
}

function updateGroup(int $userId): void {
    $groupId = $_POST['id'] ?? null;
    $name = isset($_POST['name']) ? trim($_POST['name']) : null;
    
    if (!$groupId) {
        jsonResponse(['error' => 'group_id required'], 400);
    }

    $avatar = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'group_' . $groupId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
                $avatar = '/api/uploads/avatars/' . $filename;
            }
        }
    }
    
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $member = $stmt->fetch();
    
    if (!$member || $member['role'] !== 'admin') {
        jsonResponse(['error' => 'Hanya admin yang bisa mengubah pengaturan grup'], 403);
    }
    
    $updates = [];
    $params = [];
    
    if ($name !== null) {
        $updates[] = 'name = ?';
        $params[] = $name;
    }
    
    if ($avatar !== null) {
        $updates[] = 'avatar = ?';
        $params[] = $avatar;
    }
    
    if (empty($updates)) {
        jsonResponse(['message' => 'Tidak ada perubahan']);
    }
    
    $params[] = $groupId;
    $sql = 'UPDATE groups SET ' . implode(', ', $updates) . ' WHERE id = ?';
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Add system message
        $changes = [];
        if ($name !== null) $changes[] = "nama";
        if ($avatar !== null) $changes[] = "foto profil";
        
        if (!empty($changes)) {
            $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
            $stmt->execute([$groupId, "Admin mengubah " . implode(" dan ", $changes) . " grup."]);
        }
        
        jsonResponse(['message' => 'Pengaturan grup berhasil disimpan']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Gagal update grup: ' . $e->getMessage()], 500);
    }
}

function getGroupMessages(int $userId): void {
    $groupId = $_GET['group_id'] ?? null;
    if (!$groupId) jsonResponse(['error' => 'group_id required'], 400);
    
    $db = Database::getInstance()->getPdo();
    
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Akses ditolak'], 403);
    }
    
    // Fetch messages with approvals
    $stmt = $db->prepare('
        SELECT m.id, m.content, m.is_system, m.created_at, u.username as sender_name, m.sender_id, m.msg_type, m.attachment, m.original_filename,
        (SELECT GROUP_CONCAT(user_id) FROM group_message_approvals WHERE message_id = m.id) as approval_ids,
        (SELECT GROUP_CONCAT(progress_percent) FROM group_message_approvals WHERE message_id = m.id) as approval_percents
        FROM group_messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.group_id = ?
        ORDER BY m.id ASC
    ');
    $stmt->execute([$groupId]);
    $messages = $stmt->fetchAll();
    
    // Get current group progress
    $stmtProg = $db->prepare('SELECT progress FROM groups WHERE id = ?');
    $stmtProg->execute([$groupId]);
    $currentProgress = $stmtProg->fetch()['progress'] ?? 0;
    
    // Process approval_ids into arrays
    foreach ($messages as &$msg) {
        $msg['approvals'] = $msg['approval_ids'] ? explode(',', $msg['approval_ids']) : [];
        $msg['approval_percents'] = $msg['approval_percents'] ? array_map('intval', explode(',', $msg['approval_percents'])) : [];
        unset($msg['approval_ids']);
    }

    if (!empty($messages)) {
        $lastMsgId = end($messages)['id'];
        $db->prepare('UPDATE group_members SET last_read_message_id = ? WHERE group_id = ? AND user_id = ?')
           ->execute([$lastMsgId, $groupId, $userId]);
    }

    $stmtMin = $db->prepare('SELECT MIN(last_read_message_id) as min_read FROM group_members WHERE group_id = ?');
    $stmtMin->execute([$groupId]);
    $minRead = $stmtMin->fetch()['min_read'] ?? 0;
    
    jsonResponse(['messages' => $messages, 'current_progress' => (int)$currentProgress, 'read_up_to' => (int)$minRead]);
}

function sendGroupMessage(int $userId): void {
    $db = Database::getInstance()->getPdo();
    
    // Handle form data (file upload) or json
    $groupId = $_POST['group_id'] ?? null;
    $content = $_POST['content'] ?? '';
    $msgType = $_POST['msg_type'] ?? 'text';
    $attachment = null;
    
    if (!$groupId && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = getJsonInput();
        $groupId = $input['group_id'] ?? null;
        $content = $input['content'] ?? '';
        $msgType = $input['msg_type'] ?? 'text';
        $attachment = $input['attachment'] ?? null;
    } else if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('prog_') . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename);
        $attachment = '/api/uploads/' . $filename;
        $originalFilename = $_FILES['file']['name'];
    }
    
    if (!$groupId || empty($content)) {
        jsonResponse(['error' => 'Data tidak lengkap'], 400);
    }
    
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Akses ditolak'], 403);
    }
    
    $origName = $originalFilename ?? null;
    $stmt = $db->prepare('INSERT INTO group_messages (group_id, sender_id, content, is_system, msg_type, attachment, original_filename) VALUES (?, ?, ?, 0, ?, ?, ?)');
    $stmt->execute([$groupId, $userId, $content, $msgType, $attachment, $origName]);
    $messageId = $db->lastInsertId();
    
    // Check if the message tags @ai
    if (stripos($content, '@ai') !== false) {
        $bgScript = __DIR__ . '/background_ai.php';
        $attPath = $attachment ? (__DIR__ . '/../uploads/' . basename($attachment)) : 'null';
        $cmd = "php " . escapeshellarg($bgScript) . " " . escapeshellarg((string)$groupId) . " " . escapeshellarg($content) . " " . escapeshellarg($attPath) . " > /dev/null 2>&1 &";
        exec($cmd);
    }
    
    jsonResponse(['message' => 'Pesan terkirim', 'message_id' => $messageId]);
}

function triggerGroupAi(int $groupId, string $userMessage, ?string $attachmentPath): void {
    $db = Database::getInstance()->getPdo();
    
    // Get last 10 messages for context
    $stmt = $db->prepare('
        SELECT u.username, m.content, m.is_system 
        FROM group_messages m 
        LEFT JOIN users u ON m.sender_id = u.id 
        WHERE m.group_id = ? 
        ORDER BY m.id DESC LIMIT 10
    ');
    $stmt->execute([$groupId]);
    $history = array_reverse($stmt->fetchAll());
    
    $messages = [
        ['role' => 'system', 'content' => 'Anda adalah AI Moderator untuk grup kolaborasi tugas. Jawab secara ringkas, asyik, dan memotivasi anggota grup. Jawablah sesuai konteks obrolan. Jangan membuat daftar tugas panjang jika tidak diminta. Gunakan bahasa Indonesia. ATURAN KETAT: Kamu TIDAK BOLEH menulis kode (coding), menulis esai, menyelesaikan PR, atau mengerjakan tugas pengguna. Jika diminta mengerjakan tugas, tolak dengan sopan dan jelaskan bahwa tugasmu hanya memotivasi dan mengatur jadwal mereka.']
    ];
    
    $chatHistory = '';
    foreach ($history as $msg) {
        $sender = $msg['is_system'] ? 'System/AI' : ($msg['username'] ?: 'Anggota');
        $chatHistory .= "$sender: {$msg['content']}\n";
    }
    
    $messages[] = [
        'role' => 'user',
        'content' => "Riwayat Chat Grup (termasuk pesanku yang terakhir):\n$chatHistory\n\nTolong tanggapi pesan yang me-mention @ai."
    ];
    
    // Add image support if attachment exists
    if ($attachmentPath && file_exists($attachmentPath)) {
        $fileMimeType = mime_content_type($attachmentPath);
        $fileBase64 = base64_encode(file_get_contents($attachmentPath));
        $messages[count($messages) - 1]['content'] = [
            ['type' => 'text', 'text' => "Tolong tanggapi pesan yang me-mention @ai, termasuk gambar ini jika relevan. Riwayat:\n$chatHistory"],
            ['type' => 'image_url', 'image_url' => ['url' => "data:{$fileMimeType};base64,{$fileBase64}"]]
        ];
    }
    
    $payload = json_encode([
        'model' => 'gacor',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 800,
        'stream' => false,
    ]);

    $ch = curl_init(OPENROUTER_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("Group AI curl httpCode: $httpCode, error: $error");

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (!empty($data['choices'][0]['message']['content'])) {
            $aiResponse = "🤖 AI Assistant: " . $data['choices'][0]['message']['content'];
            $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
            $stmt->execute([$groupId, $aiResponse]);
        } else {
            error_log("Group AI invalid response: " . substr($response, 0, 100));
        }
    } else {
        error_log("Group AI failed: $response");
    }
}

function approveProgress(int $userId): void {
    $input = getJsonInput();
    $messageId = $input['message_id'] ?? null;
    $progressPercent = (int)($input['progress_percent'] ?? 0);
    
    if (!$messageId) jsonResponse(['error' => 'message_id required'], 400);
    if ($progressPercent < 1 || $progressPercent > 100) jsonResponse(['error' => 'Progress harus antara 1-100%'], 400);
    
    $db = Database::getInstance()->getPdo();
    
    // Verify message is in a group the user belongs to
    $stmt = $db->prepare('
        SELECT m.sender_id, m.group_id FROM group_messages m
        JOIN group_members gm ON m.group_id = gm.group_id
        WHERE m.id = ? AND gm.user_id = ?
    ');
    $stmt->execute([$messageId, $userId]);
    $msg = $stmt->fetch();
    
    if (!$msg) jsonResponse(['error' => 'Akses ditolak'], 403);
    if ($msg['sender_id'] == $userId) jsonResponse(['error' => 'Tidak bisa approve progress sendiri'], 400);
    
    // Add approval with progress percent
    try {
        $stmt = $db->prepare('INSERT INTO group_message_approvals (message_id, user_id, progress_percent) VALUES (?, ?, ?)');
        $stmt->execute([$messageId, $userId, $progressPercent]);
        
        // Check if fully approved (all members except sender)
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM group_members WHERE group_id = ?');
        $stmt->execute([$msg['group_id']]);
        $totalMembers = $stmt->fetch()['c'];
        
        $stmt = $db->prepare('SELECT COUNT(*) as c FROM group_message_approvals WHERE message_id = ?');
        $stmt->execute([$messageId]);
        $totalApprovals = $stmt->fetch()['c'];
        
        if ($totalApprovals >= ($totalMembers - 1) && $totalMembers > 1) {
            // Calculate average progress from all approvals
            $stmt = $db->prepare('SELECT AVG(progress_percent) as avg_pct FROM group_message_approvals WHERE message_id = ?');
            $stmt->execute([$messageId]);
            $avgPercent = round($stmt->fetch()['avg_pct']);
            
            // Get current progress
            $stmt = $db->prepare('SELECT progress FROM groups WHERE id = ?');
            $stmt->execute([$msg['group_id']]);
            $currentProgress = (int)$stmt->fetch()['progress'];
            $newProgress = min(100, $currentProgress + $avgPercent);
            
            // Update group progress
            $stmt = $db->prepare('UPDATE groups SET progress = ? WHERE id = ?');
            $stmt->execute([$newProgress, $msg['group_id']]);
            
            // Mark the sender's assigned group tasks as completed since their report was approved
            $stmt = $db->prepare('UPDATE tasks SET completed = 1, progress = 100, completed_at = CURRENT_TIMESTAMP WHERE group_id = ? AND user_id = ? AND completed = 0');
            $stmt->execute([$msg['group_id'], $msg['sender_id']]);
            
            // Add AI announcement message
            $aiMsg = "🤖 AI Assistant: Laporan progress telah diverifikasi oleh semua anggota! Rata-rata penilaian: +{$avgPercent}%. Progress tim sekarang: {$newProgress}%. ";
            if ($newProgress >= 100) {
                $aiMsg .= "🎉 Selamat! Proyek telah selesai 100%!";
            } else {
                $aiMsg .= "Teruskan kerja bagusnya!";
            }
            $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
            $stmt->execute([$msg['group_id'], $aiMsg]);
        }
    } catch (Exception $e) {
        // Ignore duplicate key error (user already approved)
    }
    
    jsonResponse(['message' => 'Progress approved']);
}

function deleteGroup(int $userId): void {
    $groupId = $_GET['group_id'] ?? null;
    if (!$groupId) jsonResponse(['error' => 'group_id required'], 400);
    
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT created_by FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if (!$group) jsonResponse(['error' => 'Grup tidak ditemukan'], 404);
    if ($group['created_by'] != $userId) jsonResponse(['error' => 'Hanya admin pembuat grup yang bisa menghapus grup'], 403);
    
    $stmt = $db->prepare('SELECT COUNT(*) as c FROM group_members WHERE group_id = ?');
    $stmt->execute([$groupId]);
    $count = $stmt->fetch()['c'];
    
    if ($count > 1) {
        jsonResponse(['error' => 'Grup masih memiliki anggota. Delete hanya bisa dilakukan jika anggota sisa 1'], 403);
    }
    
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM group_message_approvals WHERE message_id IN (SELECT id FROM group_messages WHERE group_id = ?)')->execute([$groupId]);
        $db->prepare('DELETE FROM group_messages WHERE group_id = ?')->execute([$groupId]);
        $db->prepare('DELETE FROM group_members WHERE group_id = ?')->execute([$groupId]);
        $db->prepare('DELETE FROM groups WHERE id = ?')->execute([$groupId]);
        $db->commit();
        jsonResponse(['message' => 'Grup berhasil dihapus']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Gagal menghapus grup'], 500);
    }
}

function leaveGroup(int $userId): void {
    $groupId = $_GET['group_id'] ?? null;
    if (!$groupId) jsonResponse(['error' => 'group_id required'], 400);
    
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT created_by FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    $stmt = $db->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    
    $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
    $stmt->execute([$groupId, "Seorang anggota telah keluar dari grup."]);
    
    jsonResponse(['message' => 'Berhasil keluar grup']);
}

function resetGroupProgress(int $userId): void {
    $input = getJsonInput();
    $groupId = $input['group_id'] ?? null;
    if (!$groupId) jsonResponse(['error' => 'group_id required'], 400);

    $db = Database::getInstance()->getPdo();
    
    // Check permissions (Admin only)
    $stmt = $db->prepare('SELECT created_by FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if (!$group || $group['created_by'] != $userId) {
        jsonResponse(['error' => 'Hanya admin yang bisa reset progress'], 403);
    }
    
    // Reset progress to 0
    $stmt = $db->prepare('UPDATE groups SET progress = 0 WHERE id = ?');
    $stmt->execute([$groupId]);
    
    // Send AI message about new project
    $aiMsg = "🤖 AI Assistant: 🚀 Babak Baru Dimulai! Progress telah di-reset menjadi 0%. Silakan tentukan tujuan proyek baru kalian dan mari selesaikan bersama!";
    $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
    $stmt->execute([$groupId, $aiMsg]);
    
    jsonResponse(['message' => 'Progress direset menjadi 0%']);
}

function deleteGroupMessage(int $userId): void {
    $messageId = $_GET['id'] ?? null;
    if (!$messageId) jsonResponse(['error' => 'Message ID required'], 400);

    $db = Database::getInstance()->getPdo();
    
    // Check if message belongs to user and was sent less than 5 minutes ago
    $stmt = $db->prepare("SELECT sender_id, created_at, group_id FROM group_messages WHERE id = ?");
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
    
    // Delete approvals first (foreign key constraint)
    $stmt = $db->prepare('DELETE FROM group_message_approvals WHERE message_id = ?');
    $stmt->execute([$messageId]);
    
    // Delete message
    $stmt = $db->prepare('DELETE FROM group_messages WHERE id = ?');
    $stmt->execute([$messageId]);
    
    jsonResponse(['message' => 'Pesan berhasil dihapus']);
}

function kickMember(int $userId): void {
    $input = getJsonInput();
    $groupId = $input['group_id'] ?? null;
    $targetUserId = $input['user_id'] ?? null;
    
    if (!$groupId || !$targetUserId) jsonResponse(['error' => 'Data tidak lengkap'], 400);
    
    $db = Database::getInstance()->getPdo();
    
    // Check if requester is admin
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $requester = $stmt->fetch();
    
    if (!$requester || $requester['role'] !== 'admin') {
        jsonResponse(['error' => 'Hanya admin yang bisa mengeluarkan anggota'], 403);
    }
    
    if ($userId == $targetUserId) {
        jsonResponse(['error' => 'Gunakan fitur keluar grup untuk keluar sendiri'], 400);
    }
    
    // Check if target is creator
    $stmt = $db->prepare('SELECT created_by FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if ($group && $group['created_by'] == $targetUserId) {
        jsonResponse(['error' => 'Pembuat grup tidak bisa dikeluarkan'], 403);
    }
    
    // Remove member
    $stmt = $db->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $targetUserId]);
    
    if ($stmt->rowCount() > 0) {
        // Send AI message
        $stmt = $db->prepare('SELECT username, display_name FROM users WHERE id = ?');
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();
        $name = $targetUser['display_name'] ?: $targetUser['username'];
        
        $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
        $stmt->execute([$groupId, "🤖 AI Assistant: $name telah dikeluarkan dari grup oleh admin."]);
        
        jsonResponse(['message' => 'Anggota berhasil dikeluarkan']);
    } else {
        jsonResponse(['error' => 'Anggota tidak ditemukan di grup'], 404);
    }
}

function changeMemberRole(int $userId): void {
    $input = getJsonInput();
    $groupId = $input['group_id'] ?? null;
    $targetUserId = $input['user_id'] ?? null;
    $newRole = $input['role'] ?? null;
    
    if (!$groupId || !$targetUserId || !in_array($newRole, ['admin', 'member'])) {
        jsonResponse(['error' => 'Data tidak lengkap atau role tidak valid'], 400);
    }
    
    $db = Database::getInstance()->getPdo();
    
    // Check if requester is admin
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $requester = $stmt->fetch();
    
    if (!$requester || $requester['role'] !== 'admin') {
        jsonResponse(['error' => 'Hanya admin yang bisa mengubah role anggota'], 403);
    }
    
    // Check if target is the group creator
    $stmt = $db->prepare('SELECT created_by FROM groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if ($group && $group['created_by'] == $targetUserId && $newRole !== 'admin') {
        jsonResponse(['error' => 'Pembuat grup tidak bisa diturunkan menjadi member biasa'], 403);
    }
    
    // Update role
    $stmt = $db->prepare('UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$newRole, $groupId, $targetUserId]);
    
    if ($stmt->rowCount() > 0) {
        // Send AI message
        $stmt = $db->prepare('SELECT username, display_name FROM users WHERE id = ?');
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();
        $name = $targetUser['display_name'] ?: $targetUser['username'];
        
        $roleText = $newRole === 'admin' ? 'Admin' : 'Member';
        $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
        $stmt->execute([$groupId, "🤖 AI Assistant: Peran $name telah diubah menjadi $roleText."]);
        
        jsonResponse(['message' => 'Role berhasil diubah']);
    } else {
        jsonResponse(['error' => 'Anggota tidak ditemukan di grup'], 404);
    }
}

function getGroupMembers(int $userId): void {
    $groupId = $_GET['group_id'] ?? null;
    if (!$groupId) jsonResponse(['error' => 'group_id required'], 400);

    $db = Database::getInstance()->getPdo();
    
    // check access
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    if (!$stmt->fetch()) jsonResponse(['error' => 'Akses ditolak'], 403);
    
    $stmt = $db->prepare('
        SELECT u.id, u.username, u.display_name, u.avatar, gm.role 
        FROM group_members gm
        JOIN users u ON gm.user_id = u.id
        WHERE gm.group_id = ?
        ORDER BY gm.role ASC, u.username ASC
    ');
    $stmt->execute([$groupId]);
    jsonResponse(['members' => $stmt->fetchAll()]);
}

function addGroupMember(int $userId): void {
    $input = getJsonInput();
    $groupId = $input['group_id'] ?? null;
    $targetUserId = $input['user_id'] ?? null;
    
    if (!$groupId || !$targetUserId) jsonResponse(['error' => 'Data tidak lengkap'], 400);
    
    $db = Database::getInstance()->getPdo();
    
    // Check if requester is admin
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $requester = $stmt->fetch();
    
    if (!$requester || $requester['role'] !== 'admin') {
        jsonResponse(['error' => 'Hanya admin yang bisa menambahkan anggota'], 403);
    }
    
    // Check if already in group
    $stmt = $db->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $targetUserId]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'User sudah ada di dalam grup ini'], 400);
    }
    
    // Add member
    $stmt = $db->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, "member")');
    $stmt->execute([$groupId, $targetUserId]);
    
    // Send AI message
    $stmt = $db->prepare('SELECT username, display_name FROM users WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch();
    $name = $targetUser['display_name'] ?: $targetUser['username'];
    
    $stmt = $db->prepare('INSERT INTO group_messages (group_id, content, is_system) VALUES (?, ?, 1)');
    $stmt->execute([$groupId, "🤖 AI Assistant: $name telah ditambahkan ke dalam grup."]);
    
    jsonResponse(['message' => 'Anggota berhasil ditambahkan']);
}

function createGroupTask(int $userId): void {
    $input = getJsonInput();
    $groupId = $input['group_id'] ?? null;
    $targetUserId = $input['target_user_id'] ?? null;
    $title = $input['title'] ?? '';
    $difficulty = $input['difficulty'] ?? 'medium';
    $deadline = $input['deadline'] ?? '';
    
    if (!$groupId || !$targetUserId || empty($title) || empty($deadline)) {
        jsonResponse(['error' => 'Data tidak lengkap'], 400);
    }
    
    $db = Database::getInstance()->getPdo();
    
    // Check if requester is admin
    $stmt = $db->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $role = $stmt->fetchColumn();
    
    if ($role !== 'admin') {
        jsonResponse(['error' => 'Hanya admin yang bisa membagikan tugas kelompok'], 403);
    }
    
    // Check if target user is in group
    $stmt = $db->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $targetUserId]);
    if (!$stmt->fetchColumn()) {
        jsonResponse(['error' => 'Anggota tidak ditemukan di grup'], 404);
    }
    
    $stmt = $db->prepare('INSERT INTO tasks (user_id, group_id, title, description, difficulty, deadline) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $targetUserId,
        $groupId,
        $title,
        $input['description'] ?? '',
        $difficulty,
        $deadline
    ]);
    
    // Send a system message to the group
    $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $targetUsername = $stmt->fetchColumn();
    
    $msg = "Tugas kelompok baru dibagikan kepada $targetUsername: \"$title\"";
    $stmt = $db->prepare('INSERT INTO group_messages (group_id, sender_id, content, is_system) VALUES (?, NULL, ?, 1)');
    $stmt->execute([$groupId, $msg]);
    
    jsonResponse(['message' => 'Tugas kelompok berhasil dibagikan']);
}
