<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS campaign_invites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            invited_user_id INTEGER NOT NULL,
            invited_by INTEGER NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            message TEXT DEFAULT NULL,
            decided_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (campaign_id, invited_user_id),
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invites_campaign ON campaign_invites(campaign_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invites_user ON campaign_invites(invited_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invites_status ON campaign_invites(status)");

    echo "Migration 007 applied\n";
} catch (PDOException $e) {
    echo "Migration 007 failed: " . $e->getMessage() . "\n";
    exit(1);
}
