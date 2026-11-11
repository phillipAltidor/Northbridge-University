<?php
declare(strict_types=1);
// CLI-only connection. The configured portal database is never selected for writes.
function academic_test_database(): PDO
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Academic test tools are CLI only.');
    require dirname(__DIR__, 2) . '/config/database.php';
    if (!in_array($host, ['localhost', '127.0.0.1'], true)) throw new RuntimeException('Local database required.');
    $target = 'northbridge_ow_2026_test';
    if ($database !== 'northbridge_university') throw new RuntimeException('Portal must remain on the expected working database.');
    $test = new PDO("mysql:host=$host;port=$port;dbname=$target;charset=utf8mb4", $username, $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($test->query('SELECT DATABASE()')->fetchColumn() !== $target) throw new RuntimeException('Unexpected target database.');
    return $test;
}
function academic_legacy_fingerprints(PDO $pdo): array
{
    $result = [];
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND LEFT(TABLE_NAME,3)<>'NB_' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new RuntimeException('Unsafe table identifier.');
        $result[$table] = [(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn(),
            (string)$pdo->query("CHECKSUM TABLE `$table` EXTENDED")->fetch()['Checksum']];
    }
    return $result;
}
