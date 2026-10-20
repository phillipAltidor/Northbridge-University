<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password-reset.php';
$portalRoles = require __DIR__ . '/config/portal-roles.php';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$roleInput = $isPost ? ($_POST['role'] ?? '') : ($_GET['role'] ?? ($_SESSION['reset_role'] ?? ''));
if (!is_string($roleInput) || ($roleInput !== '' && !isset($portalRoles[$roleInput]))) {
    header('Location: index.php'); exit;
}
$selectedRole = $roleInput;
$roleQuery = $selectedRole !== '' ? '?role=' . rawurlencode($selectedRole) : '';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . role_home($_SESSION['user_type'])); exit;
}
if (($_SESSION['reset_role'] ?? '') !== $selectedRole) {
    unset($_SESSION['reset_request'], $_SESSION['reset_grant'], $_SESSION['reset_email']);
}
$_SESSION['reset_role'] = $selectedRole;
$_SESSION['reset_form_token'] ??= bin2hex(random_bytes(32));
$error = '';
$notice = $_SESSION['reset_notice'] ?? '';
unset($_SESSION['reset_notice']);

if ($isPost) {
    $token = $_POST['form_token'] ?? '';
    $action = $_POST['action'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['reset_form_token'], $token)) {
        http_response_code(400);
        $error = 'Please reload the page and try again.';
    } elseif ($action === 'restart') {
        unset($_SESSION['reset_request'], $_SESSION['reset_grant'], $_SESSION['reset_email']);
        $_SESSION['reset_form_token'] = bin2hex(random_bytes(32));
        header('Location: reset-password.php' . $roleQuery); exit;
    } else {
        try {
            require __DIR__ . '/config/database.php';
            require_once __DIR__ . '/includes/mail.php';
            $service = new PasswordResetService($pdo, 'send_reset_code', 'send_password_changed_notice');
            if ($action === 'request') {
                $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
                if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    portal_mailer();
                    $started = microtime(true);
                    try {
                        $result = $service->issue($email, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $selectedRole);
                    } finally {
                        $remaining = 1000000 - (int) ((microtime(true) - $started) * 1000000);
                        if ($remaining > 0) usleep($remaining);
                    }
                    if ($result['limited']) {
                        $error = 'Please wait before requesting another code. Reset requests are limited for your protection.';
                    } else {
                        $_SESSION['reset_request'] = $result['id'];
                        $_SESSION['reset_email'] = $email;
                        unset($_SESSION['reset_grant']);
                        $_SESSION['reset_form_token'] = bin2hex(random_bytes(32));
                        $_SESSION['reset_notice'] = 'If the email matches an account in this portal, you will receive a code. Check your inbox and spam folder.';
                        header('Location: reset-password.php' . $roleQuery); exit;
                    }
                }
            } elseif ($action === 'verify' && !empty($_SESSION['reset_request']) && empty($_SESSION['reset_grant'])) {
                $code = is_string($_POST['code'] ?? null) ? trim($_POST['code']) : '';
                $grant = $service->verify($_SESSION['reset_request'], $code);
                if ($grant === null) {
                    $error = 'Invalid or expired verification code. You can request a new code below.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['reset_grant'] = $grant;
                    $_SESSION['reset_form_token'] = bin2hex(random_bytes(32));
                    header('Location: reset-password.php' . $roleQuery); exit;
                }
            } elseif ($action === 'complete' && !empty($_SESSION['reset_request']) && !empty($_SESSION['reset_grant'])) {
                $newPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
                $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
                $error = reset_password_error($newPassword, $confirmation);
                if ($error === '') {
                    if (!$service->complete($_SESSION['reset_request'], $_SESSION['reset_grant'], $newPassword)) {
                        unset($_SESSION['reset_request'], $_SESSION['reset_grant']);
                        $error = 'This reset has expired or was already used. Please request a new code.';
                    } else {
                        unset($_SESSION['reset_request'], $_SESSION['reset_grant'], $_SESSION['reset_email'], $_SESSION['reset_form_token'], $_SESSION['reset_role']);
                        session_regenerate_id(true);
                        $_SESSION['login_notice'] = 'Your password was changed and your account is unlocked. Please log in with your new password.';
                        header('Location: login.php' . $roleQuery); exit;
                    }
                }
            } else {
                http_response_code(400);
                $error = 'Please request a new code and try again.';
            }
        } catch (Throwable $failure) {
            error_log('Password reset service failed.');
            http_response_code(503);
            $error = 'Password reset is temporarily unavailable. Please try again later or contact your administrator.';
        }
    }
}
$step = !empty($_SESSION['reset_grant']) ? 'password' : (!empty($_SESSION['reset_request']) ? 'code' : 'email');
$titles = ['email' => 'Reset Password', 'code' => 'Verify Your Code', 'password' => 'Choose a New Password'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title><?= e($titles[$step]) ?> | Northbridge University</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/password-visibility.js" defer></script>
</head>
<body class="welcome-page portal-login-page">
<header class="topbar welcome-header">
    <div class="brand">
        <div class="seal" aria-hidden="true">N</div>
        <div><h1>Northbridge University</h1><small>Your Future Starts Here.</small></div>
    </div>
    <nav class="welcome-nav" aria-label="Main navigation"><a class="welcome-home" href="index.php">Home</a></nav>
</header>
<main class="login-stage">
    <img class="campus-photo" src="assets/images/northbridge-campus.png" alt="" width="1942" height="809">
    <section class="login-container login-panel" aria-labelledby="reset-title">
        <h2 id="reset-title"><?= e($titles[$step]) ?></h2>
        <?php if ($notice !== ''): ?><div class="success-message" role="status"><?= e($notice) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="error-message" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form action="reset-password.php" method="post">
            <input type="hidden" name="role" value="<?= e($selectedRole) ?>">
            <input type="hidden" name="form_token" value="<?= e($_SESSION['reset_form_token']) ?>">
            <input type="hidden" name="action" value="<?= e(['email' => 'request', 'code' => 'verify', 'password' => 'complete'][$step]) ?>">
            <?php if ($step === 'email'): ?>
                <div class="form-group">
                    <label for="email">University Email</label>
                    <input type="email" id="email" name="email" autocomplete="email" maxlength="255" value="<?= e($_SESSION['reset_email'] ?? '') ?>" required>
                </div>
            <?php elseif ($step === 'code'): ?>
                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required>
                </div>
                <label class="password-visibility">
                    <input type="checkbox" data-password-visibility aria-controls="password password_confirmation">
                    Show passwords
                </label>
            <?php endif; ?>
            <button class="login-button" type="submit"><?= e(['email' => 'Send Code', 'code' => 'Verify Code', 'password' => 'Reset Password'][$step]) ?></button>
        </form>
        <?php if ($step !== 'email'): ?>
            <form action="reset-password.php" method="post" class="login-note">
                <input type="hidden" name="role" value="<?= e($selectedRole) ?>">
                <input type="hidden" name="form_token" value="<?= e($_SESSION['reset_form_token']) ?>">
                <button class="text-button" type="submit" name="action" value="restart">Request a new code or use a different email</button>
            </form>
        <?php endif; ?>
        <p class="login-note"><a href="login.php<?= e($roleQuery) ?>">Back to Login</a></p>
    </section>
</main>
<footer class="welcome-footer"><p>&copy; 2026 Northbridge University</p></footer>
</body>
</html>
