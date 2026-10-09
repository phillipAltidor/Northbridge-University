<?php

// Get one row from the database
function one(
    PDO $pdo,
    string $sql,
    array $params = []
): ?array {

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch();

    if ($row) {
        return $row;
    }

    return null;
}


// Get multiple rows from the database
function all_rows(
    PDO $pdo,
    string $sql,
    array $params = []
): array {

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


?>
