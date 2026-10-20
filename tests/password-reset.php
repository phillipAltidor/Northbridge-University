<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/includes/password-reset.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') throw new RuntimeException('Tests require the local database.');
$source = $pdo;
$original = hash('sha256', json_encode($source->query('SELECT * FROM Login ORDER BY user_ID')->fetchAll()));
$name = 'northbridge_reset_test_' . bin2hex(random_bytes(6));
$source->exec("CREATE DATABASE `$name`");
$test = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
try {
    foreach (['User', 'Login'] as $table) $test->exec($source->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1]);
    $test->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/password_reset.sql'));
    $sent = []; $notices = [];
    $service = new PasswordResetService($test, function ($email, $code) use (&$sent) { $sent[$email] = $code; }, function ($email) use (&$notices) { $notices[] = $email; });
    $id = 900000;
    foreach (['Student', 'Faculty', 'Admin', 'StatStaff'] as $role) {
        $id++;
        $email = strtolower($role) . '@example.invalid';
        $test->prepare('INSERT INTO User VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$id, 'Reset', 'Fixture', 'Other', '2000-01-01', 'Test Street', 'Test City', 'NY', '00000', $role]);
        $oldHash = password_hash('Old-Password-2026!', PASSWORD_DEFAULT);
        $test->prepare('INSERT INTO Login VALUES (?, ?, ?, 3, 1, ?)')->execute([$id, $email, $oldHash, $role]);
        $request = $service->issue($email, '127.0.0.1', $role);
        check(!$request['limited'] && isset($sent[$email]), "$role sends code to the registered address");
        $row = $test->query('SELECT * FROM Password_Reset WHERE user_ID = ' . $id)->fetch();
        check($row['code_Hash'] !== $sent[$email] && password_verify($sent[$email], $row['code_Hash']), "$role stores only a hash");
        $incorrect = $sent[$email] === '000000' ? '111111' : '000000';
        check($service->verify($request['id'], $incorrect) === null, "$role rejects incorrect code");
        $unchanged = $test->query('SELECT * FROM Login WHERE user_ID = ' . $id)->fetch();
        check($unchanged['user_Password'] === $oldHash && (int) $unchanged['lock_var'] === 1, "$role invalid code changes no credentials");
        $grant = $service->verify($request['id'], $sent[$email]);
        check(is_string($grant), "$role accepts valid code");
        check($service->verify($request['id'], $sent[$email]) === null, "$role code is single-use");
        check(!$service->complete($request['id'], str_repeat('0', 64), 'Replacement-Password-2026!'), "$role rejects forged grant");
        check(!$service->complete($request['id'], $grant, 'short'), "$role rejects short password");
        $otherAccounts = $test->query('SELECT * FROM Login WHERE user_ID <> ' . $id . ' ORDER BY user_ID')->fetchAll();
        check($service->complete($request['id'], $grant, 'Replacement-Password-2026!'), "$role completes reset");
        check($otherAccounts === $test->query('SELECT * FROM Login WHERE user_ID <> ' . $id . ' ORDER BY user_ID')->fetchAll(), "$role reset leaves all other accounts unchanged");
        $updated = $test->query('SELECT * FROM Login WHERE user_ID = ' . $id)->fetch();
        check(password_verify('Replacement-Password-2026!', $updated['user_Password']) && !password_verify('Old-Password-2026!', $updated['user_Password']), "$role replaces password hash");
        check((int) $updated['no_Of_Tries'] === 0 && (int) $updated['lock_var'] === 0, "$role unlocks account only after completion");
        check(!$service->complete($request['id'], $grant, 'Another-Password-2026!'), "$role rejects reused grant");
        check(in_array($email, $notices, true), "$role receives change notification");
        check($service->issue($email, '127.0.0.1', $role)['limited'], "$role resend cooldown");
    }
    $email = 'student@example.invalid';
    $test->exec('DELETE FROM Password_Reset');
    $expired = $service->issue($email, 'expired', 'Student');
    $test->prepare('UPDATE Password_Reset SET expires_At = ? WHERE request_ID = ?')->execute([gmdate('Y-m-d H:i:s', time() - 1), $expired['id']]);
    check($service->verify($expired['id'], $sent[$email]) === null, 'Expired code rejected');
    $test->exec('DELETE FROM Password_Reset');
    $attempt = $service->issue($email, 'attempts', 'Student');
    for ($i = 0; $i < 5; $i++) $service->verify($attempt['id'], 'not-a-code');
    check($service->verify($attempt['id'], $sent[$email]) === null, 'Five invalid attempts disable a code');
    $test->exec('DELETE FROM Password_Reset');
    $expired = $service->issue($email, 'grant-expiry', 'Student');
    $grant = $service->verify($expired['id'], $sent[$email]);
    $test->prepare('UPDATE Password_Reset SET expires_At = ? WHERE request_ID = ?')->execute([gmdate('Y-m-d H:i:s', time() - 1), $expired['id']]);
    check(!$service->complete($expired['id'], $grant, 'Replacement-Password-2026!'), 'Expired verification grant rejected');
    $test->exec('DELETE FROM Password_Reset');
    $first = $service->issue('faculty@example.invalid', 'parallel-1', 'Faculty');
    $firstCode = $sent['faculty@example.invalid'];
    $test->exec("UPDATE Password_Reset SET created_At = '" . gmdate('Y-m-d H:i:s', time() - 120) . "'");
    $second = $service->issue('faculty@example.invalid', 'parallel-2', 'Faculty');
    $firstGrant = $service->verify($first['id'], $firstCode);
    $secondGrant = $service->verify($second['id'], $sent['faculty@example.invalid']);
    check(is_string($firstGrant) && is_string($secondGrant), 'Multiple outstanding requests can be verified');
    check($service->complete($first['id'], $firstGrant, 'Concurrent-Password-2026!'), 'First verified request completes');
    check(!$service->complete($second['id'], $secondGrant, 'Overwritten-Password-2026!'), 'Completion invalidates every other outstanding request');
    $test->exec('DELETE FROM Password_Reset');
    $before = count($sent);
    $missing = $service->issue('missing@example.invalid', 'unknown', 'Student');
    check(is_string($missing['id']) && !$missing['limited'] && count($sent) === $before, 'Unknown email receives neutral request but no email');
    check($service->verify($missing['id'], '123456') === null, 'Unknown email cannot verify');
    $test->exec('DELETE FROM Password_Reset');
    $sent = [];
    $mismatch = $service->issue($email, 'mismatch', 'Admin');
    check(is_string($mismatch['id']) && !$sent, 'Wrong portal does not send a code');
    $test->exec('DELETE FROM Password_Reset');
    $first = $service->issue($email, 'credential-change', 'Student');
    $grant = $service->verify($first['id'], $sent[$email]);
    $test->prepare('UPDATE Login SET user_Password = ? WHERE user_Email = ?')->execute([password_hash('Changed-Elsewhere-2026!', PASSWORD_DEFAULT), $email]);
    check(!$service->complete($first['id'], $grant, 'Replacement-Password-2026!'), 'Password changed elsewhere invalidates outstanding grant');
    $test->exec('DELETE FROM Password_Reset');
    $first = $service->issue($email, 'email-change', 'Student');
    $test->prepare('UPDATE Login SET user_Email = ? WHERE user_Email = ?')->execute(['changed@example.invalid', $email]);
    check($service->verify($first['id'], $sent[$email]) === null, 'Email changed elsewhere invalidates code');
    $test->exec('DELETE FROM Password_Reset');
    $broken = new PasswordResetService($test, function () { throw new RuntimeException('Simulated SMTP failure'); }, function () {});
    $failed = $broken->issue('faculty@example.invalid', 'delivery-failure', 'Faculty');
    check((bool) $test->query('SELECT consumed_At FROM Password_Reset')->fetchColumn(), 'Failed delivery invalidates code');
    check(is_string($failed['id']) && !$failed['limited'], 'Failed delivery keeps neutral public result');
    $test->exec('DELETE FROM Password_Reset');
    for ($i = 0; $i < 6; $i++) {
        $request = $service->issue('faculty@example.invalid', 'email-limit-' . $i, 'Faculty');
        check(!$request['limited'], 'Allowed hourly email request');
        $test->exec("UPDATE Password_Reset SET created_At = '" . gmdate('Y-m-d H:i:s', time() - 120) . "'");
    }
    check($service->issue('faculty@example.invalid', 'email-limit-7', 'Faculty')['limited'], 'Email hourly limit enforced across IPs');
    $test->exec('DELETE FROM Password_Reset');
    for ($i = 0; $i < 20; $i++) $service->issue("missing$i@example.invalid", 'same-ip');
    check($service->issue('another@example.invalid', 'same-ip')['limited'], 'IP hourly limit enforced across email addresses');
    check(reset_password_error('Long-Enough-Password', 'different') !== '', 'Confirmation mismatch rejected');
    check(reset_password_error(str_repeat('x', 73), str_repeat('x', 73)) !== '', 'Bcrypt byte limit enforced');
    check($original === hash('sha256', json_encode($source->query('SELECT * FROM Login ORDER BY user_ID')->fetchAll())), 'All original Login records unchanged');
    echo "PASS: $checks password-reset checks; isolated test database; no original accounts changed\n";
} finally {
    $test = null;
    $source->exec("DROP DATABASE `$name`");
}
