<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deposits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            balance_before DECIMAL(10, 2) NOT NULL,
            balance_after DECIMAL(10, 2) NOT NULL,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'mock_card',
            card_last4 VARCHAR(4) DEFAULT NULL,
            card_holder VARCHAR(100) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_deposits_user ON deposits(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_deposits_created ON deposits(created_at)");

    echo "Migration 008 applied\n";
} catch (PDOException $e) {
    echo "Migration 008 failed: " . $e->getMessage() . "\n";
    exit(1);
}
