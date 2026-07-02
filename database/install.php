<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers/functions.php';

$config = db_config();
$adminDsn = db_dsn(null);

try {
    $adminPdo = new PDO($adminDsn, (string) $config['username'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $database = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $config['database']);
    $adminPdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO(db_dsn((string) $config['database']), (string) $config['username'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    die('Database connection failed: ' . $exception->getMessage());
}

$sql = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($sql);

$adminEmail = 'admin@petadoption.local';
$adminPassword = 'Admin123!';
$exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$exists->execute([$adminEmail]);

if (!$exists->fetch()) {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['Platform Admin', $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), 'admin', 'active']);
}

echo "Database installed successfully.\n";
echo "Admin login: {$adminEmail} / {$adminPassword}\n";
