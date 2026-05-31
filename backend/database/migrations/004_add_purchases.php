<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
    $hasBalance = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'virtual_balance') { $hasBalance = true; break; }
    }
    if (!$hasBalance) {
        $pdo->exec("ALTER TABLE users ADD COLUMN virtual_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id INTEGER NOT NULL,
            buyer_id INTEGER NOT NULL,
            campaign_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            unit_cost DECIMAL(10, 2) NOT NULL,
            unit_donation DECIMAL(10, 2) NOT NULL,
            total_paid DECIMAL(10, 2) NOT NULL,
            total_donation DECIMAL(10, 2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (item_id) REFERENCES campaign_items(id) ON DELETE RESTRICT,
            FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchases_buyer ON purchases(buyer_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchases_item ON purchases(item_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_purchases_campaign ON purchases(campaign_id)");

    echo "Migration 004 applied\n";
} catch (PDOException $e) {
    echo "Migration 004 failed: " . $e->getMessage() . "\n";
    exit(1);
}
