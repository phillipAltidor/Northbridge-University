<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
$portalRoles = require __DIR__ . '/config/portal-roles.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . role_home($_SESSION['user_type']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Northbridge University</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/welcome.js" defer></script>
</head>
<body class="welcome-page">
<header class="topbar welcome-header">
    <div class="brand">
        <div class="seal" aria-hidden="true">N</div>
        <div><h1 id="university-name">Northbridge University</h1><small>Your Future Starts Here.</small></div>
    </div>
    <nav class="welcome-nav" aria-label="Main navigation">
        <a class="welcome-home" href="index.php" aria-current="page">Home</a>
        <details class="portal-dropdown">
            <summary>Login</summary>
            <ul class="portal-menu">
                <?php foreach ($portalRoles as $role => $label): ?>
                    <li><a href="login.php?role=<?= e($role) ?>"><?= e($label) ?> Login</a></li>
                <?php endforeach; ?>
            </ul>
        </details>
    </nav>
</header>
<main>
    <section class="campus-welcome" aria-label="University campus">
        <img class="campus-photo" src="assets/images/northbridge-campus.png" alt="Illustrative Northbridge campus with a brick academic hall, lawns, and autumn trees" fetchpriority="high" width="1942" height="809">
    </section>
</main>
<footer class="welcome-footer">
    <p>&copy; 2026 Northbridge University</p>
</footer>
</body>
</html>
