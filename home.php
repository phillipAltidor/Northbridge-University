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
$isStudent = $_SESSION['user_type'] === 'Student';
$isFaculty = $_SESSION['user_type'] === 'Faculty';
if ($isStudent || $isFaculty) header('Cache-Control: no-store');
if ($isAdmin) {
    require_once __DIR__ . '/includes/admin.php';
    require_admin($pdo);
    header('Cache-Control: no-store');
    $pdo->exec("SET time_zone = '+00:00'");
    $recentUpdates = $pdo->query(
        "SELECT log.user_ID, log.updated_At,
                CONCAT_WS(' ', NULLIF(target.first_Name, ''), NULLIF(target.middle_Name, ''), NULLIF(target.last_Name, '')) AS user_Name,
                CONCAT_WS(' ', NULLIF(editor.first_Name, ''), NULLIF(editor.middle_Name, ''), NULLIF(editor.last_Name, '')) AS admin_Name
         FROM User_Update_Log log
         JOIN User target ON target.user_ID = log.user_ID
         JOIN User editor ON editor.user_ID = log.admin_ID
         ORDER BY log.log_ID DESC LIMIT 5"
    )->fetchAll();
}
$hasDashboard = $isAdmin || $isStudent || $isFaculty;
page_start($hasDashboard ? $_SESSION['user_type'] . ' Dashboard' : $name, $_SESSION['user_type'], 'home.php', (int) $user['user_ID'], !$hasDashboard);

if ($isAdmin):
    $today = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
    $actions = [
        ['Create User', 'admin/create-user.php', 'quick-create-user.png'],
        ['Update User Information', 'admin/update-user.php', 'quick-update-user.png'],
        ['Master Schedule', 'admin/master-schedule.php', 'quick-master-schedule.png'],
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
                <div class="admin-home-quick-links">
                    <?php foreach ($actions as [$label, $path, $icon]): ?>
                        <a class="admin-home-quick-tile" href="<?= e(app_url($path)) ?>">
                            <img src="<?= e(app_url('assets/images/' . $icon)) ?>" alt="" width="112" height="112">
                            <span><?= e($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="admin-home-recent" aria-labelledby="admin-recent-title">
                <h2 id="admin-recent-title">Recent Updates</h2>
                <?php if (!$recentUpdates): ?>
                    <p class="muted">No updates recorded yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th scope="col">Category</th><th scope="col">Record</th><th scope="col">Action</th><th scope="col">Updated By</th><th scope="col">Date (UTC)</th><th scope="col">Details</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentUpdates as $entry): ?>
                                    <?php $updatedAt = new DateTimeImmutable($entry['updated_At'], new DateTimeZone('UTC')); ?>
                                    <tr>
                                        <td>Users</td>
                                        <td><?= e($entry['user_Name']) ?></td>
                                        <td>Information updated</td>
                                        <td><?= e($entry['admin_Name']) ?></td>
                                        <td><time datetime="<?= e($updatedAt->format(DateTimeInterface::ATOM)) ?>"><?= e($updatedAt->format('M j, Y, g:i A')) ?></time></td>
                                        <td><a href="<?= e(app_url('admin/update-user.php') . '?user_id=' . (int) $entry['user_ID'] . '#update-history') ?>" aria-label="<?= e('View update history for ' . $entry['user_Name']) ?>">View History</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
    </div>
    <?php
elseif ($isStudent):
    require __DIR__ . '/includes/student-home.php';
elseif ($isFaculty):
    require __DIR__ . '/includes/faculty-home.php';
endif;
page_end();
