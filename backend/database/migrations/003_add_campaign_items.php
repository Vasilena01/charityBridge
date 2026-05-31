<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS campaign_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            item_type VARCHAR(20) NOT NULL DEFAULT 'good',
            production_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            donation_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            quantity_available INTEGER NOT NULL DEFAULT 1,
            quantity_sold INTEGER NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_campaign_items_campaign ON campaign_items(campaign_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_campaign_items_status ON campaign_items(status)");

    echo "Migration 003 applied\n";
} catch (PDOException $e) {
    echo "Migration 003 failed: " . $e->getMessage() . "\n";
    exit(1);
}
