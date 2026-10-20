<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    http_response_code(404);
    exit;
}

if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/auth.php';
    header('Location: ' . role_home($_SESSION['user_type']));
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$notice = $_SESSION['login_notice'] ?? '';
unset($_SESSION['login_notice']);
$loginTitle = $selectedRole !== '' ? $portalRoles[$selectedRole] . ' Login' : 'Portal Login';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($loginTitle) ?> | Northbridge University</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/password-visibility.js" defer></script>
</head>

<body class="welcome-page portal-login-page">

<header class="topbar welcome-header">
    <div class="brand">
        <div class="seal" aria-hidden="true">N</div>
        <div><h1>Northbridge University</h1><small>Your Future Starts Here.</small></div>
    </div>
    <nav class="welcome-nav" aria-label="Main navigation">
        <a class="welcome-home" href="index.php">Home</a>
    </nav>
</header>

<main class="login-stage">
    <img class="campus-photo" src="assets/images/northbridge-campus.png" alt="" fetchpriority="high" width="1942" height="809">
    <section class="login-container login-panel" aria-labelledby="login-title">

    <h2 id="login-title"><?= e($loginTitle) ?></h2>

    <?php if ($notice !== ''): ?>
        <div class="success-message" role="status"><?= e($notice) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="post">
        <input type="hidden" name="role" value="<?= e($selectedRole) ?>">

        <div class="form-group">
            <label for="email">University Email</label>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="username"
                placeholder="first.last@northbridge.edu"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <label class="password-visibility">
            <input type="checkbox" data-password-visibility aria-controls="password">
            Show password
        </label>

        <button type="submit" class="login-button">
            Login
        </button>

    </form>

    <p class="login-note">
        <a href="reset-password.php<?= $selectedRole !== '' ? '?role=' . e(rawurlencode($selectedRole)) : '' ?>">Reset Password</a>
    </p>

    </section>
</main>

<footer class="welcome-footer">
    <p>&copy; 2026 Northbridge University</p>
</footer>

</body>
</html>
