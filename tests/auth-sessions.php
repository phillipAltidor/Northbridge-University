<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/database.php';
if ($host !== '127.0.0.1' || $database !== 'northbridge_university') throw new RuntimeException('Expected local database.');
if (!isset($argv[1])) {
    foreach (['valid', 'stale', 'legacy'] as $case) {
        $process = proc_open([PHP_BINARY, __FILE__, $case], [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) exit(1);
    }
    exit;
}
$case = $argv[1];
$account = $pdo->query('SELECT * FROM Login ORDER BY user_ID LIMIT 1')->fetch();
session_save_path(sys_get_temp_dir());
session_start();
$_SESSION['user_id'] = (int) $account['user_ID'];
$_SESSION['user_type'] = $account['user_Type'];
if ($case !== 'legacy') $_SESSION['auth_version'] = $case === 'valid' ? hash('sha256', $account['user_Password']) : str_repeat('0', 64);
$_SERVER['SCRIPT_NAME'] = '/home.php';
$returned = false;
register_shutdown_function(function () use ($case, &$returned): void {
    $pass = $case === 'valid' ? $returned && isset($_SESSION['user_id']) : !$returned && !isset($_SESSION['user_id']);
    $_SESSION = []; session_destroy();
    echo ($pass ? 'PASS' : 'FAIL') . ": $case authentication session\n";
    if (!$pass) exit(1);
});
require dirname(__DIR__) . '/includes/auth.php';
require_login();
$returned = true;
