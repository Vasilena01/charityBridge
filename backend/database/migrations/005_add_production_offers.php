<?php
require_once __DIR__ . '/../../includes/config.php';

try {
    $cols = $pdo->query("PRAGMA table_info(campaign_items)")->fetchAll();
    $hasProducer = false;
    foreach ($cols as $c) {
        if ($c['name'] === 'producer_id') { $hasProducer = true; break; }
    }
    if (!$hasProducer) {
        $pdo->exec("ALTER TABLE campaign_items ADD COLUMN producer_id INTEGER DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS production_offers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER NOT NULL,
            producer_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            item_type VARCHAR(20) NOT NULL DEFAULT 'good',
            proposed_production_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            proposed_donation_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            quantity_offered INTEGER NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            organizer_note TEXT DEFAULT NULL,
            accepted_item_id INTEGER DEFAULT NULL,
            decided_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (producer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (accepted_item_id) REFERENCES campaign_items(id) ON DELETE SET NULL
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_offers_campaign ON production_offers(campaign_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_offers_producer ON production_offers(producer_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_offers_status ON production_offers(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_campaign_items_producer ON campaign_items(producer_id)");

    echo "Migration 005 applied\n";
} catch (PDOException $e) {
    echo "Migration 005 failed: " . $e->getMessage() . "\n";
    exit(1);
}
