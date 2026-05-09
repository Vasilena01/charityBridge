<?php
try {
    if (DB_TYPE === 'sqlite') {
        // Create database directory if it doesn't exist
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $dsn = "sqlite:" . DB_PATH;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, null, null, $options);

        // Enable foreign keys for SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
    } else {
        // MySQL fallback
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
}
?>
