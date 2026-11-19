<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/department-management.php';

// Disposable in-memory fixtures only. SQLite exercises SQL/data behavior but
// cannot certify MySQL locking, deadlock retries, or concurrent transactions.
class DepartmentFixturePDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$db = new DepartmentFixturePDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec("CREATE TABLE User (user_ID INTEGER PRIMARY KEY, user_Type TEXT);
CREATE TABLE Faculty (faculty_ID INTEGER PRIMARY KEY REFERENCES User(user_ID), faculty_Type TEXT);
CREATE TABLE Room (room_ID TEXT PRIMARY KEY, room_Type TEXT);
CREATE TABLE Office (office_ID TEXT PRIMARY KEY REFERENCES Room(room_ID));
CREATE TABLE Department (dept_ID INTEGER PRIMARY KEY, dept_Name TEXT NOT NULL, office_ID TEXT REFERENCES Office(office_ID), Email TEXT UNIQUE NOT NULL, Phone_No TEXT, chair_ID INTEGER REFERENCES Faculty(faculty_ID), dept_Manager TEXT);
CREATE TABLE Faculty_Department (faculty_ID INTEGER REFERENCES Faculty(faculty_ID), dept_ID INTEGER REFERENCES Department(dept_ID), percent_Time NUMERIC, date_Of_Appointment TEXT, PRIMARY KEY (faculty_ID, dept_ID));
INSERT INTO User VALUES (1, 'Faculty'), (2, 'Faculty');
INSERT INTO Faculty VALUES (1, 'Full-Time'), (2, 'Part-Time');
INSERT INTO Room VALUES ('ADM101', 'Office'), ('SCI101', 'Lecture');
INSERT INTO Office VALUES ('ADM101');");
$checks = 0;
function verify(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('Fixture check failed: ' . ($checks + 1)); $checks++; }
function unchanged_after_rejection(PDO $db, callable $action): void {
    $snapshot = fn() => json_encode([$db->query('SELECT * FROM Department ORDER BY dept_ID')->fetchAll(PDO::FETCH_ASSOC), $db->query('SELECT * FROM Faculty_Department ORDER BY faculty_ID, dept_ID')->fetchAll(PDO::FETCH_ASSOC)]);
    $before = $snapshot();
    try { $action(); } catch (DomainException $e) { verify($snapshot() === $before && !$db->inTransaction()); return; }
    throw new RuntimeException('Expected rejected write.');
}
$input = ['dept_Name'=>'Example department','office_ID'=>'ADM101','Email'=>'one@example.test','Phone_No'=>'555-0100','chair_ID'=>'1','dept_Manager'=>'Example manager'];
$id = department_save($db, $input, null);
verify($id === 1 && $db->query('SELECT dept_Name FROM Department')->fetchColumn() === 'Example department');
$row = $db->query('SELECT * FROM Department WHERE dept_ID = 1')->fetch(PDO::FETCH_ASSOC);
$version = department_version($row);
$input['dept_Name'] = 'Updated department';
department_save($db, $input, 1, $version);
verify($db->query('SELECT dept_Name FROM Department WHERE dept_ID = 1')->fetchColumn() === 'Updated department');
unchanged_after_rejection($db, fn() => department_save($db, $input, 1, $version));
unchanged_after_rejection($db, fn() => department_save($db, $input, null));
foreach (['office_ID'=>'SCI101', 'chair_ID'=>'999'] as $key=>$bad) {
    unchanged_after_rejection($db, fn() => department_save($db, array_replace($input, ['Email'=>'new@example.test', $key=>$bad]), null));
}
for ($i=2; $i<=4; $i++) {
    verify(department_save($db, array_replace($input, ['Email'=>"$i@example.test"]), null) === $i);
}
$appointment = ['faculty_ID'=>'1','dept_ID'=>'1','percent_Time'=>'50','date_Of_Appointment'=>'2026-01-01'];
department_assign_faculty($db, $appointment);
verify((int) $db->query('SELECT COUNT(*) FROM Faculty_Department')->fetchColumn() === 1);
unchanged_after_rejection($db, fn() => department_assign_faculty($db, $appointment));
unchanged_after_rejection($db, fn() => department_assign_faculty($db, array_replace($appointment, ['dept_ID'=>'2','percent_Time'=>'50.01'])));
department_assign_faculty($db, array_replace($appointment, ['dept_ID'=>'2']));
department_assign_faculty($db, array_replace($appointment, ['dept_ID'=>'3','percent_Time'=>'0']));
verify((int) $db->query('SELECT COUNT(*) FROM Faculty_Department WHERE faculty_ID = 1')->fetchColumn() === 3);
unchanged_after_rejection($db, fn() => department_assign_faculty($db, array_replace($appointment, ['dept_ID'=>'4','percent_Time'=>'0'])));
$partTime = array_replace($appointment, ['faculty_ID'=>'2']);
department_assign_faculty($db, $partTime);
unchanged_after_rejection($db, fn() => department_assign_faculty($db, array_replace($partTime, ['dept_ID'=>'2'])));
foreach (['faculty_ID'=>'999','dept_ID'=>'999','percent_Time'=>'-1','date_Of_Appointment'=>'2026-02-30'] as $key=>$bad) {
    unchanged_after_rejection($db, fn() => department_assign_faculty($db, array_replace($appointment, [$key=>$bad])));
}
echo "PASS: $checks in-memory create/update/assignment/rollback checks; MySQL concurrency not tested.\n";
