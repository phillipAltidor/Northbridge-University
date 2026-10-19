<?php

require_once __DIR__ . '/auth.php';

function require_admin(PDO $pdo): void
{
    require_role(['Admin']);
    $stmt = $pdo->prepare(
        'SELECT Admin.admin_ID FROM Admin
         JOIN User ON User.user_ID = Admin.admin_ID
         WHERE Admin.admin_ID = ? AND User.user_Type = ?'
    );
    $stmt->execute([(int) $_SESSION['user_id'], 'Admin']);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit('403 - You do not have permission to access this admin page.');
    }
}
