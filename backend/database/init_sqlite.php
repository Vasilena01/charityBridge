<?php
/**
 * Initialize SQLite database with schema
 * Run this file once to create the database: php backend/database/init_sqlite.php
 */

require_once __DIR__ . '/../includes/config.php';

try {
    echo "Creating database at: " . DB_PATH . "\n";

    // Create the users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('volunteer', 'organizer', 'company')),
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            bio TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            email_verified INTEGER DEFAULT 0
        )
    ");
    echo "✓ Created users table\n";

    // Create indexes for users table
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email ON users(email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_role ON users(role)");
    echo "✓ Created users indexes\n";

    // Create sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id TEXT PRIMARY KEY,
            user_id INTEGER DEFAULT NULL,
            data TEXT NOT NULL,
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Created sessions table\n";

    // Create index for sessions
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_last_activity ON sessions(last_activity)");
    echo "✓ Created sessions index\n";

    echo "\n✅ Database initialized successfully!\n";
    echo "Database location: " . DB_PATH . "\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
