<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/database.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') throw new RuntimeException('Expected local database.');
$base = getenv('NORTHBRIDGE_TEST_URL') ?: 'http://127.0.0.1:8000';
$parts = parse_url($base);
if (($parts['host'] ?? '') !== '127.0.0.1') throw new RuntimeException('HTTP tests must target localhost.');
$original = hash('sha256', json_encode($pdo->query('SELECT * FROM Login ORDER BY user_ID')->fetchAll()));
$checks = 0; $owned = [];
function assert_reset(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
function reset_http(string $path, array &$jar, ?array $post = null): array {
    global $base;
    $headers = "Content-Type: application/x-www-form-urlencoded\r\n";
    foreach ($jar as $name => $value) $headers .= "Cookie: $name=$value\r\n";
    $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => $headers, 'content' => $post === null ? '' : http_build_query($post), 'ignore_errors' => true, 'follow_location' => 0]]);
    $body = file_get_contents($base . $path, false, $context);
    $response = http_get_last_response_headers();
    assert_reset($body !== false && !preg_match('/(?:Warning|Fatal error|Notice):/', $body), 'Clean reset response');
    foreach ($response as $header) if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/', $header, $match)) $jar[$match[1]] = $match[2];
    $dom = new DOMDocument(); @$dom->loadHTML($body ?: '<html></html>');
    return [new DOMXPath($dom), $response, $body];
}
try {
    foreach (['Student', 'Faculty', 'Admin', 'StatStaff'] as $role) {
        $jar = [];
        [$page] = reset_http('/reset-password.php?role=' . $role, $jar);
        $token = $page->query('//input[@name="form_token"]')->item(0)->getAttribute('value');
        assert_reset($page->query('//header//a[@href="index.php"]')->length === 1, 'Reset Home navigation');
        $email = 'reset-http-' . bin2hex(random_bytes(8)) . '@example.invalid';
        [, $headers] = reset_http('/reset-password.php', $jar, ['role' => $role, 'action' => 'request', 'email' => $email, 'form_token' => 'invalid']);
        assert_reset(str_contains($headers[0], '400'), 'Invalid CSRF rejected');
        [, $headers] = reset_http('/reset-password.php', $jar, ['role' => $role, 'action' => 'request', 'email' => $email, 'form_token' => $token]);
        assert_reset(str_contains($headers[0], '302'), 'Request uses redirect after POST');
        $statement = $pdo->prepare('SELECT request_ID FROM Password_Reset WHERE email_Hash = ?');
        $statement->execute([hash('sha256', strtolower($email))]);
        $requestID = $statement->fetchColumn();
        assert_reset(is_string($requestID), 'Unknown email has neutral request state');
        $owned[] = $requestID;
        [$page, , $body] = reset_http('/reset-password.php?role=' . $role, $jar);
        assert_reset($page->query('//input[@name="code"]')->length === 1 && str_contains($body, 'If the email matches an account'), 'Neutral code screen');
        $token = $page->query('//input[@name="form_token"]')->item(0)->getAttribute('value');
        [$page, , $body] = reset_http('/reset-password.php', $jar, ['role' => $role, 'action' => 'verify', 'code' => '123456', 'form_token' => $token]);
        assert_reset(str_contains($body, 'Invalid or expired verification code.'), 'Wrong code displays error');
        assert_reset($page->query('//input[@name="password"]')->length === 0, 'Wrong code never opens password form');
        [, $headers] = reset_http('/reset-password.php', $jar, ['role' => $role, 'action' => 'complete', 'password' => 'Unexpected-Password-2026!', 'password_confirmation' => 'Unexpected-Password-2026!', 'form_token' => $token]);
        assert_reset(str_contains($headers[0], '400'), 'Cannot skip verification');
        [, $headers] = reset_http('/reset-password.php', $jar, ['role' => $role, 'action' => 'restart', 'form_token' => $token]);
        assert_reset(in_array('Location: reset-password.php?role=' . $role, $headers, true), 'Restart preserves portal');
        [$page] = reset_http('/reset-password.php?role=' . $role, $jar);
        assert_reset($page->query('//input[@name="email"]')->length === 1 && $page->query('//input[@name="code"]')->length === 0, 'Restart clears verification state');
    }
    assert_reset($original === hash('sha256', json_encode($pdo->query('SELECT * FROM Login ORDER BY user_ID')->fetchAll())), 'Real accounts unchanged');
    echo "PASS: $checks reset HTTP checks; no real emails sent or accounts modified\n";
} finally {
    $statement = $pdo->prepare('DELETE FROM Password_Reset WHERE request_ID = ? AND user_ID IS NULL');
    foreach ($owned as $requestID) $statement->execute([$requestID]);
}
