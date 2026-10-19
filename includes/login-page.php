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
$loginTitle = $selectedRole !== '' ? $portalRoles[$selectedRole] . ' Login' : 'Portal Login';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($loginTitle) ?> | Northbridge University</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<header class="login-header">
    <h1>Northbridge University</h1>
    <p>Student &amp; Academic Information System</p>
</header>

<main class="login-container">

    <h2 id="login-title"><?= e($loginTitle) ?></h2>
    <p class="login-description">Enter your university email and password.</p>

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

        <button type="submit" class="login-button">
            Login
        </button>

    </form>

    <p class="login-note">
        <a href="reset-password.php<?= $selectedRole !== '' ? '?role=' . e(rawurlencode($selectedRole)) : '' ?>">Reset Password</a>
    </p>

    <p class="login-note">Accounts are locked after 3 unsuccessful login attempts.</p>
    <p class="login-note"><a href="index.php">Back to Welcome</a></p>
</main>

<footer class="login-footer">
    <p>&copy; 2026 Northbridge University</p>
</footer>

</body>
</html>
