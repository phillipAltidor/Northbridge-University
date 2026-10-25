<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require dirname(__DIR__) . '/config/database.php';
if (!in_array($host, ['127.0.0.1','localhost'], true) || $database !== 'northbridge_university') throw new RuntimeException('Local database required');
$sessions = [];
$checks = 0;
function faculty_http_check(bool $pass, string $label): void
{
    global $checks;
    if (!$pass) throw new RuntimeException($label);
    $checks++; echo "PASS: $label\n";
}
function faculty_http_request(string $path, string $sid = ''): array
{
    $context = stream_context_create(['http' => ['header' => $sid ? "Cookie: PHPSESSID=$sid\r\n" : '',
        'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20]]);
    $html = file_get_contents('http://127.0.0.1:8000/' . $path, false, $context);
    $headers = http_get_last_response_headers(); preg_match('/HTTP\/\S+ (\d+)/', $headers[0], $match);
    if ($html === false || preg_match('/(?:Warning|Fatal error|Notice):/', $html)) throw new RuntimeException('Unexpected page error');
    return [(int)$match[1], $html, $headers];
}
function faculty_data_hash(PDO $pdo): string
{
    $hash = hash_init('sha256');
    foreach (['User','Login','Faculty','Course_Section','Enrollment','Attendance','Advisor','User_Update_Log','Course_Section_Update_Log'] as $table) {
        foreach ($pdo->query("SELECT * FROM `$table` ORDER BY 1, 2") as $row) hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
    }
    return hash_final($hash);
}
function faculty_preview(string $name, string $html): void
{
    global $argv;
    if (!in_array('--preview', $argv, true)) return;
    $path = '/private/tmp/northbridge-faculty-' . $name . '.html';
    file_put_contents($path, str_replace('<head>', '<head><base href="http://127.0.0.1:8000/">', $html));
    chmod($path, 0600);
}
$before = faculty_data_hash($pdo);
try {
    foreach (['faculty/information.php','faculty/schedule.php'] as $path) faculty_http_check(faculty_http_request($path)[0] === 302, 'Logged-out Faculty page redirects');
    foreach (['Faculty','Student','Admin','StatStaff'] as $role) {
        $q = $pdo->prepare('SELECT user_ID,user_Password FROM Login WHERE user_Type=? ORDER BY user_ID LIMIT 1');
        $q->execute([$role]); $account = $q->fetch();
        $sid = bin2hex(random_bytes(20)); session_id($sid); session_start();
        $_SESSION = ['user_id'=>(int)$account['user_ID'], 'user_type'=>$role, 'auth_version'=>hash('sha256',$account['user_Password'])];
        session_write_close(); $sessions[] = $sid;
        foreach (['faculty/information.php','faculty/schedule.php','faculty/schedule.php?term=next'] as $path) {
            [$status,$html] = faculty_http_request($path,$sid);
            faculty_http_check($status === ($role === 'Faculty' ? 200 : 403), "$role access enforced: $path");
            if ($role !== 'Faculty') continue;
            $separator = str_contains($path,'?') ? '&' : '?';
            [$tamperedStatus,$tampered] = faculty_http_request($path . $separator . 'faculty_id=200002&user_id=100001',$sid);
            faculty_http_check($tamperedStatus === 200 && $tampered === $html, 'URL cannot switch Faculty account records');
            faculty_http_check(!str_contains($html,$account['user_Password']), 'No credential leakage');
            if ($path === 'faculty/information.php') {
                faculty_http_check(str_contains($html,'<dt>Role</dt><dd>Faculty</dd>') && !str_contains($html,'<form'), 'Faculty profile is read-only');
                faculty_preview('profile',$html);
            } else {
                faculty_preview(str_contains($path,'next') ? 'schedule-next' : 'schedule-current',$html);
            }
        }
        [$status,$home] = faculty_http_request('home.php',$sid);
        faculty_http_check($status === 200, "$role Home remains available");
        if ($role !== 'Faculty') {
            faculty_http_check(!str_contains($home,'faculty-week-title'), 'Faculty agenda not exposed to other roles');
            continue;
        }
        $dom = new DOMDocument(); @$dom->loadHTML($home); $xpath = new DOMXPath($dom);
        faculty_http_check($xpath->query('//nav[contains(@class,"faculty-navigation")]//a')->length === 3, 'Three working Faculty navigation links');
        faculty_http_check($xpath->query('//span[@class="admin-nav-disabled"]')->length === 17 && $xpath->query('//details[@open]')->length === 0, 'Unfinished Faculty actions disabled and groups collapsed');
        faculty_http_check($xpath->query('//div[contains(@class,"admin-home-quick-links")]/*')->length === 3
            && $xpath->query('//div[contains(@class,"admin-home-quick-links")]/div[@aria-disabled="true"]')->length === 2, 'Three Faculty tiles; rosters and advisees disabled');
        faculty_http_check(!str_contains($home,'/admin/') && !str_contains($home,'/student/') && !str_contains($home,'Recent Updates'), 'Faculty Home excludes cross-role actions and Admin updates');
        [$defaultStatus,$default] = faculty_http_request('faculty/schedule.php',$sid);
        [$currentStatus,$current] = faculty_http_request('faculty/schedule.php?term=current',$sid);
        faculty_http_check($defaultStatus === 200 && $currentStatus === 200 && $default === $current, 'Faculty schedule defaults to current semester');
        foreach (['bogus','next&term[]=current','%3Cscript%3E'] as $invalid) faculty_http_check(faculty_http_request('faculty/schedule.php?term='.$invalid,$sid)[0] === 400,'Invalid Faculty term rejected');
        faculty_http_check(faculty_http_request('admin/master-schedule.php',$sid)[0] === 403
            && faculty_http_request('student/information.php',$sid)[0] === 403, 'Faculty cannot enter other role pages');
        faculty_preview('home',$home);
    }
    faculty_http_check(faculty_data_hash($pdo) === $before, 'Faculty reads preserve account, roster, attendance, advisor, and log records');
    echo "PASS: $checks Faculty HTTP checks\n";
} finally {
    foreach ($sessions as $sid) { session_id($sid); session_start(); $_SESSION=[]; session_destroy(); }
}
