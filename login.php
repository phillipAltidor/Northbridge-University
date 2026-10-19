<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
$portalRoles = require __DIR__ . '/config/portal-roles.php';
$roleInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['role'] ?? '') : ($_GET['role'] ?? '');
if (!is_string($roleInput) || ($roleInput !== '' && !isset($portalRoles[$roleInput]))) {
    header('Location: index.php');
    exit;
}
$selectedRole = $roleInput;
$loginURL = 'login.php' . ($selectedRole !== '' ? '?role=' . rawurlencode($selectedRole) : '');

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . role_home($_SESSION['user_type']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    require __DIR__ . '/includes/login-page.php';
    exit;
}

require_once __DIR__ . '/config/database.php';

$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter your email and password.';
    header('Location: ' . $loginURL);
    exit;
}

$sql = "SELECT Login.*, User.first_Name, User.last_Name
        FROM Login
        JOIN User ON User.user_ID = Login.user_ID
        WHERE Login.user_Email = ?
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);

$user = $stmt->fetch();

if (!$user) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: ' . $loginURL);
    exit;
}

if ($selectedRole !== '' && $user['user_Type'] !== $selectedRole) {
    $_SESSION['login_error'] = 'Unable to sign in to this portal. Check your credentials and selected portal.';
    header('Location: ' . $loginURL);
    exit;
}

if ($user['lock_var']) {
    $_SESSION['login_error'] = 'This account is locked. Contact an administrator.';
    header('Location: ' . $loginURL);
    exit;
}

$passwordCorrect =
    password_verify($password, $user['user_Password']) ||
    hash_equals((string) $user['user_Password'], $password);

if (!$passwordCorrect) {

    $tries = (int) $user['no_Of_Tries'] + 1;

    if ($tries >= 3) {
        $locked = 1;
    } else {
        $locked = 0;
    }

    $sql = "UPDATE Login
            SET no_Of_Tries = ?, lock_var = ?
            WHERE user_ID = ?";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $tries,
        $locked,
        $user['user_ID']
    ]);

    if ($locked) {
        $_SESSION['login_error'] =
            'Account locked after 3 unsuccessful attempts.';
    } else {
        $_SESSION['login_error'] =
            'Invalid email or password.';
    }

    header('Location: ' . $loginURL);
    exit;
}

$sql = "UPDATE Login
        SET no_Of_Tries = 0, lock_var = 0
        WHERE user_ID = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user['user_ID']]);

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['user_ID'];
$_SESSION['user_type'] = $user['user_Type'];
$_SESSION['email'] = $user['user_Email'];
$_SESSION['name'] = $user['first_Name'] . ' ' . $user['last_Name'];

header('Location: ' . role_home($user['user_Type']));
exit;
?>
