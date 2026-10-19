<?php

session_start();

require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . role_home($_SESSION['user_type']));
    exit;
}

$error = '';
$email = '';
$step = 'email';

if (empty($_SESSION['reset_form_token'])) {
    $_SESSION['reset_form_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null)
        ? trim($_POST['email'])
        : '';
    $token = $_POST['form_token'] ?? '';
    $action = $_POST['action'] ?? 'request';

    if ($action === 'verify') {
        $step = 'code';
    }

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['reset_form_token'], $token)
    ) {
        $error = 'Please reload the page and try again.';
    } elseif ($action === 'verify') {
        // Demonstrate rejection until generated-code verification is implemented.
        $error = 'Invalid verification code.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Code delivery and verification will be connected in a later update.
        $step = 'code';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Northbridge University</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<header class="login-header">
    <h1>Northbridge University</h1>
    <p>Student & Academic Information System</p>
</header>

<div class="login-container">
    <h2>Reset Password</h2>

    <?php if ($step === 'email'): ?>
        <p class="login-description">
            Enter the university email associated with your account.
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 'email'): ?>
        <form action="reset-password.php" method="post">
            <input
                type="hidden"
                name="form_token"
                value="<?= e($_SESSION['reset_form_token']) ?>"
            >

            <div class="form-group">
                <label for="email">University Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="first.last@northbridge.edu"
                    autocomplete="email"
                    value="<?= e($email) ?>"
                    required
                >
            </div>

            <button type="submit" class="login-button">Continue</button>
        </form>
    <?php else: ?>
        <form action="reset-password.php" method="post">
            <input type="hidden" name="action" value="verify">
            <input
                type="hidden"
                name="form_token"
                value="<?= e($_SESSION['reset_form_token']) ?>"
            >

            <div class="form-group">
                <label for="code">Verification Code</label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    autocomplete="one-time-code"
                    required
                >
            </div>

            <button type="submit" class="login-button">
                Verify Code
            </button>
        </form>

        <p class="login-note">
            <a href="reset-password.php">Use a different email</a>
        </p>
    <?php endif; ?>

    <p class="login-note">
        <a href="index.php">Back to Login</a>
    </p>
</div>

<footer class="login-footer">
    <p>&copy; 2026 Northbridge University</p>
</footer>

</body>
</html>
