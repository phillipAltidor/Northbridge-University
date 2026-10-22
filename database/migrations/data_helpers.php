<?php
declare(strict_types=1);

function timeline_rows(PDO $pdo, string $table): array
{
    $keys = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll();
    usort($keys, static fn($a, $b) => $a['Seq_in_index'] <=> $b['Seq_in_index']);
    $order = $keys ? implode(',', array_map(static fn($k) => '`' . $k['Column_name'] . '`', $keys)) : '1';
    return $pdo->query("SELECT * FROM `$table` ORDER BY $order")->fetchAll();
}

function timeline_sql(PDO $pdo, string $table, array $rows): string
{
    if (!$rows) return "-- No rows for $table in this academic snapshot.\n";
    $columns = implode(',', array_map(static fn($c) => "`$c`", array_keys($rows[0])));
    $sql = '';
    foreach (array_chunk($rows, 250) as $batch) {
        $values = array_map(static fn($row) => '(' . implode(',', array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row))) . ')', $batch);
        $sql .= "INSERT INTO `$table` ($columns) VALUES\n" . implode(",\n", $values) . ";\n\n";
    }
    return rtrim($sql) . "\n";
}

function timeline_export_order(PDO $pdo, string $table, array $rows, string $sourceSQL): array
{
    // MySQL parses the seed inserts so unchanged records retain their existing order.
    $temp = 'timeline_seed_order';
    $pdo->exec("CREATE TEMPORARY TABLE `$temp` (_seed_position BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY) AS SELECT * FROM `$table` LIMIT 0");
    $pdo->exec(str_replace("INSERT INTO `$table`", "INSERT INTO `$temp`", $sourceSQL));
    $keys = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll();
    usort($keys, static fn($a, $b) => $a['Seq_in_index'] <=> $b['Seq_in_index']);
    $keyOf = static fn($row) => json_encode(array_map(static fn($key) => $row[$key['Column_name']], $keys), JSON_THROW_ON_ERROR);
    $remaining = [];
    foreach ($rows as $row) $remaining[$keyOf($row)] = $row;
    $ordered = [];
    foreach ($pdo->query("SELECT * FROM `$temp` ORDER BY _seed_position") as $row) {
        $key = $keyOf($row);
        if (isset($remaining[$key])) { $ordered[] = $remaining[$key]; unset($remaining[$key]); }
    }
    $pdo->exec("DROP TEMPORARY TABLE `$temp`");
    return array_merge($ordered, array_values($remaining));
}
