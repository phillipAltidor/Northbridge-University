<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/department-management.php';

$checks = 0;
function check(bool $condition): void {
    global $checks;
    if (!$condition) { throw new RuntimeException('Department assertion failed: ' . ($checks + 1)); }
    $checks++;
}
function rejected(callable $action): void {
    try { $action(); } catch (DomainException $e) { check(true); return; }
    throw new RuntimeException('Invalid input was accepted.');
}

$_ENV['ADMIN_DEPARTMENT_WRITER_IDS'] = '';
check(!department_can_write(42));
$_ENV['ADMIN_DEPARTMENT_WRITER_IDS'] = '42, 57';
check(department_can_write(42));
check(!department_can_write(43));
foreach (['42,', '42,invalid', '042', '0', '42, -1'] as $config) {
    $_ENV['ADMIN_DEPARTMENT_WRITER_IDS'] = $config;
    check(!department_can_write(42));
}
unset($_ENV['ADMIN_DEPARTMENT_WRITER_IDS']);
putenv('ADMIN_DEPARTMENT_WRITER_IDS=42');
check(department_can_write(42));
$_ENV['ADMIN_DEPARTMENT_WRITER_IDS'] = '';
check(!department_can_write(42));
putenv('ADMIN_DEPARTMENT_WRITER_IDS');

check(department_positive_id('2147483647', 'department') === 2147483647);
foreach ([null, [], 1, '0', '-1', '1.0', '1e2', '01', '2147483648', '1 OR 1=1'] as $id) {
    rejected(fn() => department_positive_id($id, 'department'));
}
$valid = ['dept_Name' => ' Example Department ', 'office_ID' => 'ADM101',
    'Email' => 'department@example.test', 'Phone_No' => '555-0100',
    'chair_ID' => '42', 'dept_Manager' => 'Example Manager'];
check(department_values($valid)['dept_Name'] === 'Example Department');
foreach (['dept_Name' => '', 'office_ID' => str_repeat('x', 21), 'Email' => 'invalid',
    'Phone_No' => "555\n0100", 'chair_ID' => [], 'dept_Manager' => str_repeat('x', 101)] as $key => $bad) {
    rejected(fn() => department_values(array_replace($valid, [$key => $bad])));
}
$row = array_merge(['dept_ID' => 12], department_values($valid));
check(department_version($row) === department_version(array_map('strval', $row)));
foreach (array_keys($row) as $key) {
    check(department_version($row) !== department_version(array_replace($row, [$key => 'changed'])));
}
echo "PASS: $checks department permission, input validation, and stale-version checks (no database writes).\n";
