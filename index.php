<?php
session_start();

if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/auth.php';
    header('Location: ' . role_home($_SESSION['user_type']));
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Northbridge University Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<header class="login-header">
    <h1>Northbridge University</h1>
    <p>Student & Academic Information System</p>
</header>

<div class="login-container">

    <h2>Portal Login</h2>
    <p class="login-description">
        Enter your university email and password.
    </p>

    <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="post">

        <div class="form-group">
            <label for="email">University Email</label>
            <input
                type="email"
                id="email"
                name="email"
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
                required
            >
        </div>

        <button type="submit" class="login-button">
            Login
        </button>

    </form>

    <p class="login-note">
        <a href="reset-password.php">Reset Password</a>
    </p>

    <p class="login-note">
    Accounts are locked after 3 unsuccessful login attempts.
</p>

</div>

<footer class="login-footer">
    <p>&copy; 2026 Northbridge University</p>
</footer>

</body>
</html>
