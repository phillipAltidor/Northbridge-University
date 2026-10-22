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

$isAdmin = $_SESSION['user_type'] === 'Admin';
page_start($isAdmin ? 'Admin Dashboard' : $name, $_SESSION['user_type'], 'home.php', (int) $user['user_ID'], !$isAdmin);

if ($isAdmin):
    $today = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
    $actions = [
        ['Create User', 'admin/create-user.php', 'Create a user account', 'Create User'],
        ['Update User Information', 'admin/update-user.php', 'Find a user and update their requested information', 'Find User'],
        ['Master Schedule', 'admin/master-schedule.php', 'Review semester schedules and manage Fall 2026 sections', 'View Schedule'],
    ];
    ?>
    <div class="admin-home">
        <section class="admin-home-welcome" aria-labelledby="admin-welcome-title">
            <img class="admin-home-campus" src="<?= e(app_url('assets/images/northbridge-campus.png')) ?>" alt="" width="1942" height="809" fetchpriority="high">
            <div class="admin-home-greeting">
            <time datetime="<?= e($today->format('Y-m-d')) ?>"><?= e($today->format('l, F j, Y')) ?></time>
            <h1 id="admin-welcome-title">Welcome back, <?= e($user['first_Name']) ?>!</h1>
            </div>
        </section>

            <section class="admin-home-actions" aria-labelledby="admin-actions-title">
                <h2 id="admin-actions-title">Quick Access</h2>
                <div class="admin-home-action-grid">
                    <?php foreach ($actions as [$title, $path, $description, $label]): ?>
                        <article class="admin-home-action">
                            <h3><?= e($title) ?></h3>
                            <p><?= e($description) ?></p>
                            <a class="btn" href="<?= e(app_url($path)) ?>"><?= e($label) ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

    </div>
    <?php
endif;
page_end();
