<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require dirname(__DIR__) . '/config/database.php';
if (!in_array($host, ['127.0.0.1', 'localhost'], true) || $database !== 'northbridge_university') throw new RuntimeException('Local database required');
$sessions = [];
$checks = 0;
function student_http_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
    echo "PASS: $message\n";
}
function student_http_request(string $path, string $sid = ''): array
{
    $context = stream_context_create(['http' => [
        'header' => $sid ? "Cookie: PHPSESSID=$sid\r\n" : '',
        'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 20,
    ]]);
    $html = file_get_contents('http://127.0.0.1:8000/' . $path, false, $context);
    $headers = http_get_last_response_headers();
    preg_match('/HTTP\/\S+ (\d+)/', $headers[0], $match);
    if ($html === false || preg_match('/(?:Warning|Fatal error|Notice):/', $html)) throw new RuntimeException('Unexpected PHP output');
    return [(int) $match[1], $html, $headers];
}
function student_data_hash(PDO $pdo): string
{
    $hash = hash_init('sha256');
    foreach (['User', 'Login', 'Enrollment', 'Course_Section', 'Attendance'] as $table) {
        foreach ($pdo->query("SELECT * FROM `$table` ORDER BY 1, 2") as $row) hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
    }
    return hash_final($hash);
}
$before = student_data_hash($pdo);
try {
    foreach (['student/schedule.php', 'student/information.php'] as $path) {
        student_http_check(student_http_request($path)[0] === 302, 'Logged-out Student page redirects');
    }
    foreach (['Student', 'Admin', 'Faculty', 'StatStaff'] as $role) {
        $q = $pdo->prepare('SELECT user_ID, user_Password FROM Login WHERE user_Type=? ORDER BY user_ID LIMIT 1');
        $q->execute([$role]); $account = $q->fetch();
        $sid = bin2hex(random_bytes(20));
        session_id($sid); session_start();
        $_SESSION = ['user_id' => (int) $account['user_ID'], 'user_type' => $role, 'auth_version' => hash('sha256', $account['user_Password'])];
        session_write_close(); $sessions[] = $sid;
        [$profileStatus, $profileHtml] = student_http_request('student/information.php', $sid);
        student_http_check($profileStatus === ($role === 'Student' ? 200 : 403), "$role Student profile access enforced");
        if ($role === 'Student') {
            [$tamperedStatus, $tamperedProfile] = student_http_request('student/information.php?user_id=100002&student_id=100002', $sid);
            student_http_check($tamperedStatus === 200 && $profileHtml === $tamperedProfile, 'Profile cannot be switched to another student by URL');
            $profileDom = new DOMDocument(); @$profileDom->loadHTML($profileHtml); $profileXPath = new DOMXPath($profileDom);
            student_http_check($profileXPath->query('//div[@class="profile-page"]')->length === 1
                && $profileXPath->query('//section[@class="profile-section"]')->length === 2
                && $profileXPath->query('//aside[@class="profile-account"]')->length === 1, 'Student profile matches Admin section layout');
            student_http_check($profileXPath->query('//main//h1')->length === 1
                && $profileXPath->query('//main//form | //main//input')->length === 0, 'Single profile heading and no editing controls');
            student_http_check(str_contains($profileHtml, '<dt>Role</dt><dd>Student</dd>'), 'Account role displayed as Student');
            if (in_array('--preview', $argv, true)) {
                $path = '/private/tmp/northbridge-student-profile-preview.html';
                file_put_contents($path, str_replace('<head>', '<head><base href="http://127.0.0.1:8000/">', $profileHtml));
                chmod($path, 0600);
            }
        }
        foreach (['current', 'next'] as $term) {
            [$status, $html] = student_http_request('student/schedule.php?term=' . $term, $sid);
            student_http_check($status === ($role === 'Student' ? 200 : 403), "$role schedule access enforced");
            if ($role === 'Student') {
                if (in_array('--preview', $argv, true)) {
                    $preview = str_replace('<head>', '<head><base href="http://127.0.0.1:8000/">', $html);
                    $path = '/private/tmp/northbridge-student-schedule-' . $term . '.html';
                    file_put_contents($path, $preview);
                    chmod($path, 0600);
                }
                [$tamperedStatus, $tampered] = student_http_request('student/schedule.php?term=' . $term . '&user_id=100002&student_id=100002', $sid);
                student_http_check($tamperedStatus === 200 && $tampered === $html, 'URL cannot switch to another Student schedule');
                student_http_check(!str_contains($html, 'Grade') && !str_contains($html, 'Attendance'), 'No grading or attendance controls');
            }
        }
        [$status, $html] = student_http_request('home.php', $sid);
        student_http_check($status === 200, "$role Home still works");
        if ($role !== 'Student') {
            student_http_check(!str_contains($html, 'student-week-title'), 'Student agenda not leaked to other roles');
            continue;
        }
        [$defaultStatus, $defaultSchedule] = student_http_request('student/schedule.php', $sid);
        [$currentStatus, $currentSchedule] = student_http_request('student/schedule.php?term=current', $sid);
        student_http_check($defaultStatus === 200 && $currentStatus === 200 && $defaultSchedule === $currentSchedule, 'Semester Schedule defaults to the current semester');
        $dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
        student_http_check($xpath->query('//nav[contains(@class,"student-navigation")]//a')->length === 3, 'Home has three implemented navigation links');
        student_http_check($xpath->query('//span[@class="admin-nav-disabled"]')->length === 17, 'All 17 unimplemented Student actions disabled');
        student_http_check($xpath->query('//details[@open]')->length === 0, 'Navigation starts collapsed');
        student_http_check($xpath->query('//div[contains(@class,"admin-home-quick-links")]/*')->length === 3, 'Exactly three Quick Access tiles');
        student_http_check(!str_contains($html, '/admin/') && !str_contains($html, 'Recent Updates'), 'No Admin actions or activity on Student Home');
        student_http_check(!str_contains($html, $account['user_Password']), 'Credentials excluded from markup');
        foreach (['bogus', 'current%27', '%3Cscript%3E', 'next&term[]=current'] as $invalid) {
            student_http_check(student_http_request('student/schedule.php?term=' . $invalid, $sid)[0] === 400, 'Invalid term selection rejected');
        }
        student_http_check(student_http_request('admin/master-schedule.php', $sid)[0] === 403, 'Student cannot access Admin Master Schedule');
        if (in_array('--preview', $argv, true)) {
            $html = str_replace('<head>', '<head><base href="http://127.0.0.1:8000/">', $html);
            file_put_contents('/private/tmp/northbridge-student-home-preview.html', $html);
            chmod('/private/tmp/northbridge-student-home-preview.html', 0600);
        }
    }
    student_http_check(student_data_hash($pdo) === $before, 'Student dashboard and schedule requests leave database records unchanged');
    echo "PASS: $checks Student HTTP checks\n";
} finally {
    foreach ($sessions as $sid) { session_id($sid); session_start(); $_SESSION = []; session_destroy(); }
}
