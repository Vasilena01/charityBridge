<?php
/**
 * Migration: Add campaigns table for Phase 2
 * Run this to add campaigns support to existing database
 */

require_once __DIR__ . '/../../includes/config.php';

try {

    // Create campaigns table
    $sql = "
    CREATE TABLE IF NOT EXISTS campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organizer_id INTEGER NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        campaign_type VARCHAR(50) NOT NULL,
        goal_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        current_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        deadline DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        visibility VARCHAR(20) NOT NULL DEFAULT 'public',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ";

    $pdo->exec($sql);
    echo "✓ Campaigns table created\n";

    // Create indexes
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_campaigns_organizer ON campaigns(organizer_id)",
        "CREATE INDEX IF NOT EXISTS idx_campaigns_status ON campaigns(status)",
        "CREATE INDEX IF NOT EXISTS idx_campaigns_visibility ON campaigns(visibility)",
        "CREATE INDEX IF NOT EXISTS idx_campaigns_deadline ON campaigns(deadline)"
    ];

    foreach ($indexes as $index) {
        $pdo->exec($index);
    }
    echo "✓ Indexes created\n";

    echo "\n✅ Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
