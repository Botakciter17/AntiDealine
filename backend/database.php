<?php
/**
 * Database Manager - SQLite
 */

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . DB_PATH);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->migrate();
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    private function migrate(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                display_name TEXT DEFAULT '',
                avatar TEXT DEFAULT '',
                api_key TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        try { $this->pdo->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT ''"); } catch (PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT ''"); } catch (PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE users ADD COLUMN google_id TEXT DEFAULT NULL"); } catch (PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE users ADD COLUMN email TEXT DEFAULT NULL"); } catch (PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_number TEXT DEFAULT NULL"); } catch (PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_verified INTEGER DEFAULT 0"); } catch (PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_otp TEXT DEFAULT NULL"); } catch (PDOException $e) {}
        try { $this->pdo->exec("ALTER TABLE tasks ADD COLUMN whatsapp_notified INTEGER DEFAULT 0"); } catch (PDOException $e) {}

        $this->pdo->exec("

            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT DEFAULT '',
                difficulty TEXT DEFAULT 'medium',
                deadline DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed INTEGER DEFAULT 0,
                completed_at DATETIME DEFAULT NULL,
                progress INTEGER DEFAULT 0,
                estimated_time TEXT DEFAULT '',
                group_id INTEGER DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS chat_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT DEFAULT 'Chat baru',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_id INTEGER DEFAULT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (session_id) REFERENCES chat_sessions(id)
            );

            CREATE TABLE IF NOT EXISTS friendships (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id1 INTEGER NOT NULL,
                user_id2 INTEGER NOT NULL,
                status TEXT DEFAULT 'pending', -- 'pending', 'accepted'
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id1) REFERENCES users(id),
                FOREIGN KEY (user_id2) REFERENCES users(id),
                UNIQUE(user_id1, user_id2)
            );

            CREATE TABLE IF NOT EXISTS direct_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender_id INTEGER NOT NULL,
                receiver_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id),
                FOREIGN KEY (receiver_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                avatar TEXT DEFAULT '',
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS group_members (
                group_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role TEXT DEFAULT 'member', -- 'admin', 'member'
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (group_id, user_id),
                FOREIGN KEY (group_id) REFERENCES groups(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS group_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                sender_id INTEGER, -- Bisa null jika pesan dari sistem/AI
                content TEXT NOT NULL,
                is_system INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (group_id) REFERENCES groups(id),
                FOREIGN KEY (sender_id) REFERENCES users(id)
            );
            CREATE TABLE IF NOT EXISTS group_message_approvals (
                message_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id, user_id),
                FOREIGN KEY (message_id) REFERENCES group_messages(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        ");

        try {
            $this->pdo->exec("ALTER TABLE group_messages ADD COLUMN msg_type TEXT DEFAULT 'text'");
        } catch (Exception $e) {}
        try {
            $this->pdo->exec("ALTER TABLE group_messages ADD COLUMN attachment TEXT DEFAULT NULL");
        } catch (Exception $e) {}
        try {
            $this->pdo->exec("ALTER TABLE group_messages ADD COLUMN original_filename TEXT DEFAULT NULL");
        } catch (Exception $e) {}
        try {
            $this->pdo->exec("ALTER TABLE groups ADD COLUMN progress INTEGER DEFAULT 0");
        } catch (Exception $e) {}
        try {
            $this->pdo->exec("ALTER TABLE group_message_approvals ADD COLUMN progress_percent INTEGER DEFAULT 0");
        } catch (Exception $e) {}
        try {
            $this->pdo->exec("ALTER TABLE group_members ADD COLUMN last_read_message_id INTEGER DEFAULT 0");
        } catch (Exception $e) {}
    }
}
