<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contributions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            contributor_id INTEGER NOT NULL,
            type VARCHAR(20) NOT NULL,
            amount DECIMAL(10, 2) DEFAULT NULL,
            hours_count DECIMAL(6, 2) DEFAULT NULL,
            goods_description TEXT DEFAULT NULL,
            goods_estimated_value DECIMAL(10, 2) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (contributor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contributions_campaign ON contributions(campaign_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contributions_contributor ON contributions(contributor_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contributions_type ON contributions(type)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contributions_status ON contributions(status)");

    echo "Migration 006 applied\n";
} catch (PDOException $e) {
    echo "Migration 006 failed: " . $e->getMessage() . "\n";
    exit(1);
}
