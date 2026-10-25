<?php

require_once __DIR__ . '/auth.php';


// Navigation links for each type of user
function nav_items(string $role): array
{
    switch ($role) {

        case 'Student':
            return [
                ['Home', app_url('home.php')],
                ['Student Information', app_url('student/information.php')]
            ];

        case 'Admin':
            return [
                ['Home', app_url('home.php')],
                ['Create User', app_url('admin/create-user.php')],
                ['Update User Information', app_url('admin/update-user.php')],
                ['Master Schedule', app_url('admin/master-schedule.php')]
            ];

        case 'Faculty':
        case 'StatStaff':
            return [['Home', app_url('home.php')]];

        default:
            return [];
    }
}


// Display the beginning of each portal page
function page_start(
    string $title,
    string $role,
    string $active = '',
    ?int $userID = null,
    bool $showHeading = true
): void {

    global $pdo;
    require_once __DIR__ . '/../config/database.php';

    $stmt = $pdo->prepare(
        'SELECT u.first_Name, u.middle_Name, u.last_Name, l.user_Email
         FROM User u JOIN Login l ON l.user_ID = u.user_ID
         WHERE u.user_ID = ?'
    );
    $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
    $account = $stmt->fetch();
    if ($account) {
        $_SESSION['name'] = implode(' ', array_filter([
            $account['first_Name'], $account['middle_Name'], $account['last_Name']
        ], static fn($part) => $part !== null && $part !== ''));
        $_SESSION['email'] = $account['user_Email'];
    }
    $name = $_SESSION['name'] ?? 'User';
    $email = $_SESSION['email'] ?? '';
    $profilePaths = [
        'Admin' => 'admin/personal-information.php',
        'Student' => 'student/information.php',
        'Faculty' => 'faculty/information.php',
    ];
    $hasProfile = isset($profilePaths[$role]);


    if ($role === 'StatStaff') {
        $portalName = 'Statistics Portal';
    } else {
        $portalName = $role . ' Portal';
    }

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>
            <?= e($title) ?> | Northbridge University
        </title>

        <link
            rel="stylesheet"
            href="<?= e(app_url('assets/css/style.css')) ?>"
        >
        <?php if ($hasProfile): ?>
            <script src="<?= e(app_url('assets/js/admin-navigation.js')) ?>" defer></script>
            <script src="<?= e(app_url('assets/js/admin-profile.js')) ?>" defer></script>
        <?php endif; ?>

    </head>

    <body>

    <header class="topbar<?= $hasProfile ? ' admin-topbar' : '' ?>">

        <div class="brand">

            <div class="seal">
                N
            </div>

            <div>

                <b>Northbridge University</b>

                <small>
                    <?= e($portalName) ?>
                </small>

            </div>

        </div>


        <div class="account">

            <?php if ($hasProfile):
                $initials = mb_strtoupper(mb_substr($account['first_Name'] ?? $name, 0, 1, 'UTF-8') . mb_substr($account['last_Name'] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
                ?>
                <div class="admin-profile">
                    <a class="admin-profile-link" href="<?= e(app_url($profilePaths[$role])) ?>" aria-label="<?= e('Personal information for ' . $name) ?>">
                        <span class="admin-profile-trigger">
                            <span class="admin-profile-avatar" aria-hidden="true"><?= e($initials) ?></span>
                            <strong class="admin-profile-name"><?= e($name) ?></strong>
                        </span>
                    </a>
                    <div class="admin-profile-panel" hidden>
                        <dl>
                            <div><dt>Email</dt><dd><?= e($email) ?></dd></div>
                            <div><dt>User ID</dt><dd><?= e((int)($_SESSION['user_id'] ?? 0)) ?></dd></div>
                        </dl>
                    </div>
                </div>
            <?php else: ?>
            <div>

                <strong>
                    <?= e($name) ?>
                </strong>

                <small>
                    <?= e($email) ?>
                </small>

            </div>
            <?php endif; ?>

            <a
                class="btn light"
                href="<?= e(app_url('logout.php')) ?>"
            >
                Logout
            </a>

        </div>

    </header>


    <div class="shell">

        <aside class="sidebar<?= $hasProfile ? ' admin-sidebar' : '' ?>">

            <?php if ($role === 'Admin'):
                require_once __DIR__ . '/admin-navigation.php';
                admin_navigation($active);
            elseif ($role === 'Student'):
                require_once __DIR__ . '/student-navigation.php';
                student_navigation($active);
            elseif ($role === 'Faculty'):
                require_once __DIR__ . '/faculty-navigation.php';
                faculty_navigation($active);
            else: ?>

            <?php foreach (nav_items($role) as $item): ?>

                <?php

                $label = $item[0];
                $url = $item[1];

                if (app_url($active) === $url) {
                    $class = 'active';
                } else {
                    $class = '';
                }

                ?>

                <a
                    class="<?= $class ?>"
                    href="<?= e($url) ?>"
                >
                    <?= e($label) ?>
                </a>

            <?php endforeach; ?>
            <?php endif; ?>

        </aside>


        <main class="content">

            <?php if ($showHeading): ?>
            <h1 class="page-heading">
                <span><?= e($title) ?></span>
                <?php if ($userID !== null): ?>
                    <span class="page-user-id">ID: <?= e($userID) ?></span>
                <?php endif; ?>
            </h1>
            <?php endif; ?>

    <?php
}


// Finish each portal page
function page_end(): void
{
    ?>

        </main>

    </div>

    </body>
    </html>

    <?php
}


// Display a message after an action is completed
function flash(): void
{
    if (!empty($_SESSION['flash'])) {

        $flashMessage = $_SESSION['flash'];

        unset($_SESSION['flash']);

        ?>

        <div class="alert <?= e($flashMessage['type']) ?>">
            <?= e($flashMessage['message']) ?>
        </div>

        <?php
    }
}

?>
