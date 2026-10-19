<?php

require_once __DIR__ . '/includes/layout.php';

require_role(['Student', 'Admin', 'Faculty', 'StatStaff']);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$user = one(
    $pdo,
    'SELECT user_ID, first_Name, middle_Name, last_Name FROM User WHERE user_ID = ?',
    [(int) $_SESSION['user_id']]
);

if (!$user) {
    http_response_code(404);
    exit('User information could not be found.');
}

$name = implode(' ', array_filter([
    $user['first_Name'],
    $user['middle_Name'],
    $user['last_Name']
], static fn($part) => $part !== null && $part !== ''));

page_start($name, $_SESSION['user_type'], 'home.php', (int) $user['user_ID']);
page_end();
