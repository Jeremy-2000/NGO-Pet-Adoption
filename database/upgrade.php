<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers/functions.php';

$config = db_config();

try {
    $pdo = new PDO(db_dsn((string) $config['database']), (string) $config['username'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    die('Database connection failed: ' . $exception->getMessage() . PHP_EOL);
}

$migrations = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($migrations);

foreach ($migrations as $migration) {
    $sql = file_get_contents($migration);

    if ($sql === false || trim($sql) === '') {
        continue;
    }

    $pdo->exec($sql);
    echo 'Applied ' . basename($migration) . PHP_EOL;
}

echo 'Database upgrade complete.' . PHP_EOL;
