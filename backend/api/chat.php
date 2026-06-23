<?php
/**
 * Chat API - Proxy to 9router (local AI proxy)
 * AI analyzes user's task descriptions and generates structured tasks
 * Supports file uploads (images) for AI vision analysis
 * Supports chat sessions (like ChatGPT)
 */

function chat(int $userId): void {
    // Handle both JSON and FormData requests
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'multipart/form-data') !== false) {
        $message = $_POST['message'] ?? '';
        $sessionId = $_POST['session_id'] ?? null;
        $uploadedFile = $_FILES['file'] ?? null;
    } else {
        $input = getJsonInput();
        $message = $input['message'] ?? '';
        $sessionId = $input['session_id'] ?? null;
        $uploadedFile = null;
    }

    if (empty($message) && empty($uploadedFile)) {
        jsonResponse(['error' => 'Message or file is required'], 400);
    }

    $db = Database::getInstance()->getPdo();

    // Create a new session if none provided
    if (empty($sessionId)) {
        $stmt = $db->prepare('INSERT INTO chat_sessions (user_id, title) VALUES (?, ?)');
        // Use first 30 chars of message as title
        $title = mb_substr($message ?: 'File upload', 0, 40);
        $stmt->execute([$userId, $title]);
        $sessionId = (int) $db->lastInsertId();
    }

    // Handle file upload
    $fileBase64 = null;
    $fileMimeType = null;
    $fileDescription = '';
    
    if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileMimeType = mime_content_type($uploadedFile['tmp_name']);
        
        if (in_array($fileMimeType, $allowedMimes)) {
            $fileData = file_get_contents($uploadedFile['tmp_name']);
            $fileBase64 = base64_encode($fileData);
            $fileDescription = "\n\n[User attached an image file: {$uploadedFile['name']}. Analyze the image content to understand the task and estimate completion time.]";
        } else {
            $fileDescription = "\n\n[User attached a file: {$uploadedFile['name']} (type: {$fileMimeType}). Consider this context when estimating the task.]";
        }
    }

    // Save user message
    $savedMessage = $message ?: '(file terlampir)';
    if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $savedMessage .= " [📎 {$uploadedFile['name']}]";
    }
    $stmt = $db->prepare('INSERT INTO chat_messages (user_id, session_id, role, content) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $sessionId, 'user', $savedMessage]);

    // Get existing tasks for context
    $stmt = $db->prepare('SELECT title, description, difficulty, deadline, progress, completed FROM tasks WHERE user_id = ? ORDER BY deadline ASC');
    $stmt->execute([$userId]);
    $existingTasks = $stmt->fetchAll();

    // Get chat history for THIS SESSION (last 20 messages)
    $stmt = $db->prepare('SELECT role, content FROM chat_messages WHERE user_id = ? AND session_id = ? ORDER BY id DESC LIMIT 20');
    $stmt->execute([$userId, $sessionId]);
    $history = array_reverse($stmt->fetchAll());

    $currentDate = date('Y-m-d H:i:s');
    $tasksJson = json_encode($existingTasks, JSON_PRETTY_PRINT);

    $systemPrompt = <<<PROMPT
You are AntiDeadline AI, a task management assistant. Your job is to help users organize their tasks and deadlines.

Current date/time: {$currentDate}

User's existing tasks:
{$tasksJson}

IMPORTANT RULES:
1. When the user tells you about tasks/assignments, extract them and respond with a JSON block containing tasks to create.
2. Always be encouraging and help them understand the urgency of their tasks.
3. Respond in the same language the user uses (if they speak Indonesian, respond in Indonesian).
4. When creating tasks, wrap them in a ```json code block with this exact format:

```json
{
  "tasks": [
    {
      "title": "Task title",
      "description": "Brief description",
      "difficulty": "easy|medium|hard",
      "deadline": "YYYY-MM-DD HH:mm:ss",
      "progress": 0,
      "estimated_time": "2-3 jam"
    }
  ]
}
```

5. If the user is just chatting or asking questions (not adding tasks), just respond normally without JSON.
6. The difficulty field affects how urgently the health bar changes:
   - "easy": slower decay, user has more leeway
   - "medium": moderate decay
   - "hard": fast decay, must work on it NOW
7. Estimate difficulty based on the user's description of the task complexity.
8. If the user mentions progress (e.g., "sudah 50%"), set the progress field accordingly (0-100).
9. ESTIMATED TIME: Always provide an estimated_time field for each task. This is how long you think the task will take to complete.
   Examples: "30 menit", "1-2 jam", "3-4 jam", "1 hari", "2-3 hari"
   Base your estimate on the task description, difficulty, and any attached files.
10. FILE/IMAGE ANALYSIS: If the user uploads a file (especially an image of an assignment, materi, or task instructions), carefully analyze its content. Based on what you see:
   - Estimate the time required to complete the task (e.g., "menulis tangan 5 halaman = ~2-3 jam")
   - Suggest an appropriate deadline if none is given
   - Set difficulty based on the complexity visible in the material
   - Provide a brief summary of what the task requires
11. NO INTERNAL NOTES: Do NOT include any internal thinking, debug notes, tags, or messages like "skipped: subtask breakdown" in your response. Your response must only contain the conversational text meant for the user, followed by the JSON block if a task is created.
PROMPT;

    // Build messages array
    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($history as $msg) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }

    // If there's an image, modify the last user message to include the image for vision
    if ($fileBase64 && $fileMimeType) {
        array_pop($messages);
        
        $userContent = [
            [
                'type' => 'text',
                'text' => ($message ?: 'Analisis file ini dan bantu estimasi pengerjaan tugas.') . $fileDescription
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$fileMimeType};base64,{$fileBase64}"
                ]
            ]
        ];
        
        $messages[] = [
            'role' => 'user',
            'content' => $userContent
        ];
    }

    $payload = json_encode([
        'model' => 'gacor',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 2000,
        'stream' => false,
    ]);

    $ch = curl_init(OPENROUTER_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        jsonResponse(['error' => 'Failed to connect to AI: ' . $curlError], 500);
    }

    $responseData = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMsg = $responseData['error']['message'] ?? 'AI request failed';
        jsonResponse(['error' => $errorMsg], $httpCode);
    }

    $aiContent = $responseData['choices'][0]['message']['content'] ?? '';

    // Save AI response
    $stmt = $db->prepare('INSERT INTO chat_messages (user_id, session_id, role, content) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $sessionId, 'assistant', $aiContent]);

    // Update session title from first AI response if it's the first exchange
    $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM chat_messages WHERE session_id = ?');
    $stmt->execute([$sessionId]);
    $msgCount = $stmt->fetch()['cnt'];
    if ($msgCount <= 2) {
        // Use user's first message as session title
        $title = mb_substr($message ?: 'File upload', 0, 40);
        $stmt = $db->prepare('UPDATE chat_sessions SET title = ? WHERE id = ?');
        $stmt->execute([$title, $sessionId]);
    }

    // Extract tasks from AI response if present
    $createdTasks = [];
    if (preg_match('/```json\s*(.*?)\s*```/s', $aiContent, $matches)) {
        $tasksData = json_decode($matches[1], true);
        if (isset($tasksData['tasks']) && is_array($tasksData['tasks'])) {
            foreach ($tasksData['tasks'] as $task) {
                $stmt = $db->prepare('INSERT INTO tasks (user_id, title, description, difficulty, deadline, progress, estimated_time) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $userId,
                    $task['title'] ?? 'Untitled Task',
                    $task['description'] ?? '',
                    $task['difficulty'] ?? 'medium',
                    $task['deadline'] ?? date('Y-m-d 23:59:59', strtotime('+7 days')),
                    $task['progress'] ?? 0,
                    $task['estimated_time'] ?? ''
                ]);
                $createdTasks[] = [
                    'id' => (int) $db->lastInsertId(),
                    'title' => $task['title'] ?? 'Untitled Task',
                    'description' => $task['description'] ?? '',
                    'difficulty' => $task['difficulty'] ?? 'medium',
                    'deadline' => $task['deadline'] ?? date('Y-m-d 23:59:59', strtotime('+7 days')),
                    'progress' => $task['progress'] ?? 0,
                    'estimated_time' => $task['estimated_time'] ?? '',
                    'completed' => 0,
                ];
            }
        }
    }

    jsonResponse([
        'message' => $aiContent,
        'tasks_created' => $createdTasks,
        'session_id' => $sessionId,
    ]);
}

/**
 * Get chat history for a specific session
 */
function getHistory(int $userId): void {
    $sessionId = $_GET['session_id'] ?? null;
    $db = Database::getInstance()->getPdo();
    
    if ($sessionId) {
        $stmt = $db->prepare('SELECT role, content FROM chat_messages WHERE user_id = ? AND session_id = ? ORDER BY id ASC LIMIT 100');
        $stmt->execute([$userId, $sessionId]);
    } else {
        // Return empty if no session specified
        jsonResponse([]);
        return;
    }
    
    $history = $stmt->fetchAll();
    
    // Parse tasksCreated from assistant messages for history rendering
    foreach ($history as &$msg) {
        if ($msg['role'] === 'assistant') {
            $createdTasks = [];
            if (preg_match('/```json\s*(.*?)\s*```/s', $msg['content'], $matches)) {
                $tasksData = json_decode($matches[1], true);
                if (isset($tasksData['tasks']) && is_array($tasksData['tasks'])) {
                    foreach ($tasksData['tasks'] as $task) {
                        $createdTasks[] = [
                            'title' => $task['title'] ?? 'Untitled Task',
                            'difficulty' => $task['difficulty'] ?? 'medium'
                        ];
                    }
                }
            }
            if (!empty($createdTasks)) {
                $msg['tasksCreated'] = $createdTasks;
            }
        }
    }
    unset($msg);

    jsonResponse($history);
}

/**
 * Get all chat sessions for the user
 */
function getSessions(int $userId): void {
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT id, title, created_at FROM chat_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
    $stmt->execute([$userId]);
    $sessions = $stmt->fetchAll();
    jsonResponse($sessions);
}

/**
 * Delete a chat session and its messages
 */
function deleteSession(int $userId): void {
    $input = getJsonInput();
    $sessionId = $input['id'] ?? null;
    
    if (!$sessionId) {
        jsonResponse(['error' => 'Session ID is required'], 400);
    }
    
    $db = Database::getInstance()->getPdo();
    
    // Delete messages first
    $stmt = $db->prepare('DELETE FROM chat_messages WHERE session_id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $userId]);
    
    // Delete session
    $stmt = $db->prepare('DELETE FROM chat_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sessionId, $userId]);
    
    jsonResponse(['message' => 'Session deleted']);
}
