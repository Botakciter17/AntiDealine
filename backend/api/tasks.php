<?php
/**
 * Tasks API - CRUD operations for tasks
 */

function getTasks(int $userId): void {
    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('SELECT * FROM tasks WHERE user_id = ? ORDER BY completed ASC, deadline ASC');
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll();

    // Add health_percent calculation
    foreach ($tasks as &$task) {
        $task['health_percent'] = calculateHealth($task);
    }

    jsonResponse(['tasks' => $tasks]);
}

function createTask(int $userId): void {
    $input = getJsonInput();

    if (empty($input['title']) || empty($input['deadline'])) {
        jsonResponse(['error' => 'Title and deadline are required'], 400);
    }

    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('INSERT INTO tasks (user_id, title, description, difficulty, deadline, progress) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $userId,
        $input['title'],
        $input['description'] ?? '',
        $input['difficulty'] ?? 'medium',
        $input['deadline'],
        $input['progress'] ?? 0
    ]);

    $taskId = (int) $db->lastInsertId();
    
    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    $task['health_percent'] = calculateHealth($task);

    jsonResponse(['task' => $task], 201);
}

function updateTask(int $userId): void {
    $input = getJsonInput();

    if (empty($input['id'])) {
        jsonResponse(['error' => 'Task ID is required'], 400);
    }

    $db = Database::getInstance()->getPdo();
    
    // Verify ownership
    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$input['id'], $userId]);
    $task = $stmt->fetch();

    if (!$task) {
        jsonResponse(['error' => 'Task not found'], 404);
    }

    $fields = [];
    $values = [];

    if (isset($input['title'])) { $fields[] = 'title = ?'; $values[] = $input['title']; }
    if (isset($input['description'])) { $fields[] = 'description = ?'; $values[] = $input['description']; }
    if (isset($input['difficulty'])) { $fields[] = 'difficulty = ?'; $values[] = $input['difficulty']; }
    if (isset($input['deadline'])) { $fields[] = 'deadline = ?'; $values[] = $input['deadline']; }
    if (isset($input['progress'])) { $fields[] = 'progress = ?'; $values[] = (int) $input['progress']; }
    if (isset($input['completed'])) { 
        $fields[] = 'completed = ?'; 
        $values[] = (int) $input['completed'];
        if ($input['completed']) {
            $fields[] = 'completed_at = ?';
            $values[] = date('Y-m-d H:i:s');
        } else {
            $fields[] = 'completed_at = ?';
            $values[] = null;
        }
    }

    if (empty($fields)) {
        jsonResponse(['error' => 'No fields to update'], 400);
    }

    $values[] = $input['id'];
    $values[] = $userId;

    $stmt = $db->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?');
    $stmt->execute($values);

    // Return updated task
    $stmt = $db->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$input['id']]);
    $task = $stmt->fetch();
    $task['health_percent'] = calculateHealth($task);

    jsonResponse(['task' => $task]);
}

function deleteTask(int $userId): void {
    $input = getJsonInput();

    if (empty($input['id'])) {
        jsonResponse(['error' => 'Task ID is required'], 400);
    }

    $db = Database::getInstance()->getPdo();
    $stmt = $db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$input['id'], $userId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Task not found'], 404);
    }

    jsonResponse(['message' => 'Task deleted']);
}

/**
 * Calculate health percentage for a task
 * 
 * Health goes from 100 (green) to 0 (red) based on:
 * - Time remaining vs total time (created_at to deadline)
 * - Difficulty multiplier (easy=0.6, medium=1.0, hard=1.8)
 * - Progress reduces urgency
 */
function calculateHealth(array $task): float {
    if ($task['completed']) return 100;

    $now = time();
    $deadline = strtotime($task['deadline']);
    $created = strtotime($task['created_at']);

    // If deadline passed
    if ($now >= $deadline) return 0;

    $totalTime = max($deadline - $created, 1);
    $timeRemaining = $deadline - $now;
    $timeRatio = $timeRemaining / $totalTime; // 1.0 = just created, 0.0 = deadline

    // Difficulty multiplier - harder tasks decay faster
    $difficultyMultiplier = match($task['difficulty']) {
        'easy' => 0.6,
        'medium' => 1.0,
        'hard' => 1.8,
        default => 1.0,
    };

    // Apply difficulty: use power function for non-linear decay
    $health = pow($timeRatio, $difficultyMultiplier) * 100;

    // Progress bonus: if you've done work, health decays slower
    $progressBonus = ($task['progress'] / 100) * 30; // max 30% bonus
    $health = min(100, $health + $progressBonus);

    return round(max(0, min(100, $health)), 1);
}
