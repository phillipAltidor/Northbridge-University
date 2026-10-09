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
                ['Create User', app_url('admin/create-user.php')]
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
    ?int $userID = null
): void {

    $name = $_SESSION['name'] ?? 'User';
    $email = $_SESSION['email'] ?? '';


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

    </head>

    <body>

    <header class="topbar">

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

            <div>

                <strong>
                    <?= e($name) ?>
                </strong>

                <small>
                    <?= e($email) ?>
                </small>

            </div>

            <a
                class="btn light"
                href="<?= e(app_url('logout.php')) ?>"
            >
                Logout
            </a>

        </div>

    </header>


    <div class="shell">

        <aside class="sidebar">

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

        </aside>


        <main class="content">

            <h1 class="page-heading">
                <span><?= e($title) ?></span>
                <?php if ($userID !== null): ?>
                    <span class="page-user-id">ID: <?= e($userID) ?></span>
                <?php endif; ?>
            </h1>

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
