<?php
declare(strict_types=1);
require_once __DIR__ . '/academic-timeline.php';
require_once __DIR__ . '/academic-portal.php';
require_once __DIR__ . '/password-reset.php';

function faculty_rows(PDO $pdo, string $sql, array $args = []): array {
    $q = $pdo->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function faculty_id($value, string $label = 'record'): int {
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (int)$value > 2147483647) throw new DomainException("Select a valid $label.");
    return (int)$value;
}
function faculty_text($value, int $max = 100): string {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/u', $value)) throw new DomainException('Enter valid text within the field length limit.');
    return trim($value);
}
function faculty_account(PDO $pdo, int $id, bool $lock = false): array {
    $rows = faculty_rows($pdo, "SELECT u.*, f.faculty_Type, l.user_Email, l.user_Password FROM User u JOIN Faculty f ON f.faculty_ID=u.user_ID JOIN Login l ON l.user_ID=u.user_ID WHERE u.user_ID=? AND u.user_Type='Faculty' AND l.user_Type='Faculty'" . ($lock ? ' FOR UPDATE' : ''), [$id]);
    if (!$rows) throw new DomainException('A faculty account could not be found.');
    return $rows[0];
}
function faculty_version(array $rows): string { return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)); }
function faculty_address_version(array $account): string {
    return faculty_version(array_intersect_key($account, array_flip(['street','city','state','zip_Code'])));
}
function faculty_save_address(PDO $pdo, int $id, array $input, string $version): void {
    $values = [];
    foreach (['street'=>100,'city'=>50,'state'=>50,'zip_Code'=>10] as $key=>$length) {
        $values[$key] = faculty_text($input[$key] ?? null, $length);
        if ($values[$key] === '') throw new DomainException('Complete every address field.');
    }
    try {
        $pdo->beginTransaction(); $account = faculty_account($pdo, $id, true);
        if (!hash_equals(faculty_address_version($account), $version)) throw new DomainException('Your address changed since this form opened. Reload before saving.');
        $q = $pdo->prepare('UPDATE User SET street=?, city=?, state=?, zip_Code=? WHERE user_ID=?');
        $q->execute([...array_values($values), $id]); $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function faculty_password_matches(string $plain, string $stored): bool {
    return password_verify($plain, $stored) || (password_get_info($stored)['algo'] === null && hash_equals($stored, $plain));
}
function faculty_save_password(PDO $pdo, int $id, array $input): string {
    foreach (['current_password','new_password','confirmation'] as $key) if (!is_string($input[$key] ?? null)) throw new DomainException('Complete the password fields.');
    $error = reset_password_error($input['new_password'], $input['confirmation']);
    if ($error !== '') throw new DomainException($error);
    if (str_contains($input['new_password'], "\0")) throw new DomainException('The password contains an unsupported character.');
    try {
        $pdo->beginTransaction(); $account = faculty_account($pdo, $id, true);
        if (!faculty_password_matches($input['current_password'], $account['user_Password'])) throw new DomainException('The current password is incorrect.');
        if (faculty_password_matches($input['new_password'], $account['user_Password'])) throw new DomainException('Choose a different new password.');
        $hash = password_hash($input['new_password'], PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE Login SET user_Password=? WHERE user_ID=?')->execute([$hash, $id]);
        $pdo->commit(); return hash('sha256', $hash);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

// Profile access is deliberately limited to the instructor's students or advisees.
// Transcript access follows manual use case 45 (faculty selects a student), like
// the existing faculty degree-audit search. No personal address or DOB is exposed.
function faculty_student_search(PDO $pdo, int $fid, string $query, string $scope): array {
    $args = [];
    $where = '1=1';
    if ($scope === 'advisees') { $where = 'EXISTS(SELECT 1 FROM Advisor a WHERE a.student_ID=s.student_ID AND a.faculty_ID=?)'; $args[]=$fid; }
    elseif ($scope === 'students') {
        $where = '(EXISTS(SELECT 1 FROM Advisor a WHERE a.student_ID=s.student_ID AND a.faculty_ID=?) OR EXISTS(SELECT 1 FROM Enrollment e JOIN Course_Section cs ON cs.CRN=e.CRN AND cs.semester_ID=e.semester_ID WHERE e.student_ID=s.student_ID AND cs.faculty_ID=?))';
        $args=[$fid,$fid];
    }
    if ($query !== '') {
        $where .= " AND (CAST(s.student_ID AS CHAR)=? OR u.first_Name LIKE ? ESCAPE '!' OR u.last_Name LIKE ? ESCAPE '!')";
        $like='%'.str_replace(['!','%','_'], ['!!','!%','!_'], $query).'%'; array_push($args,$query,$like,$like);
    }
    return faculty_rows($pdo, "SELECT s.student_ID,s.student_Type,s.student_Year,u.first_Name,u.middle_Name,u.last_Name,l.user_Email,m.major_Name FROM Student s JOIN User u ON u.user_ID=s.student_ID JOIN Login l ON l.user_ID=s.student_ID JOIN Major m ON m.major_ID=s.major_ID WHERE $where ORDER BY u.last_Name,u.first_Name,s.student_ID LIMIT 100",$args);
}
function faculty_transcript(PDO $pdo, int $sid): array {
    $rows=faculty_rows($pdo, 'SELECT h.CRN,h.course_ID,h.semester_ID,h.Grade,c.course_Name,c.course_Credits,s.semester_Name FROM Student_History h JOIN Course c ON c.course_ID=h.course_ID JOIN Semester s ON s.semester_ID=h.semester_ID WHERE h.student_ID=? UNION ALL SELECT e.CRN,cs.course_ID,e.semester_ID,e.Grade,c.course_Name,c.course_Credits,s.semester_Name FROM Enrollment e JOIN Course_Section cs ON cs.CRN=e.CRN AND cs.semester_ID=e.semester_ID JOIN Course c ON c.course_ID=cs.course_ID JOIN Semester s ON s.semester_ID=e.semester_ID WHERE e.student_ID=? AND NOT EXISTS(SELECT 1 FROM Student_History h WHERE h.student_ID=e.student_ID AND h.CRN=e.CRN)', [$sid,$sid]);
    usort($rows,static fn($a,$b)=>[academic_semester_order($a['semester_Name']),$a['course_ID'],$a['CRN']] <=> [academic_semester_order($b['semester_Name']),$b['course_ID'],$b['CRN']]);
    foreach ($rows as &$row) {
        $row['status']=academic_semester_status($row['semester_Name']);
        // Current/planned courses never masquerade as earned credit.
        if ($row['status'] !== 'Completed') $row['Grade']=null;
    } unset($row); return $rows;
}
function faculty_window_config(): array { return require dirname(__DIR__).'/config/faculty-windows.php'; }
function faculty_write_window(string $mode, array $section, array $meetings, array $policy, DateTimeImmutable $now): ?string {
    $now=$now->setTimezone(new DateTimeZone('America/New_York'));
    $term=$policy[$section['semester_Name']] ?? null;
    if (!is_array($term)) return 'The academic calendar for this semester has not been configured. Saving is unavailable.';
    if ($mode === 'grades') {
        $window=$term['grading'][$section['CRN']] ?? null;
        if (!is_array($window) || empty($window['final_exam_ends_at']) || empty($window['allowed_grades'])) return 'The final exam end time and permitted grades for this section have not been configured.';
        $stamp=$window['final_exam_ends_at'];
        if (!is_string($stamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $stamp) || !is_array($window['allowed_grades'])) return 'The grading calendar needs explicit time zones and permitted grades.';
        $open=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:sP', $stamp);
        if (!$open || $open->format('Y-m-d\\TH:i:sP') !== $stamp) return 'The grading calendar needs review before saving.';
        $local=$open->setTimezone(new DateTimeZone('America/New_York'));
        if ($local->format('P') !== $open->format('P') || empty($term['final_exams_start']) || empty($term['final_exams_end']) || $local->format('Y-m-d') < $term['final_exams_start'] || $local->format('Y-m-d') > $term['final_exams_end']) return 'The final exam must fall within the approved exam dates with the correct New York time offset.';
        $close=$open->setTimestamp($open->getTimestamp()+48*60*60);
        if ($now < $open || $now >= $close) return 'The grading window is closed for this section. Grades are due within 48 hours after its final exam ends.';
        return null;
    }
    if (!in_array($now->format('Y-m-d'), $term['attendance_dates'] ?? [], true)) return 'Attendance can only be recorded on an approved class date.';
    if (isset($term['early_closures'][$now->format('Y-m-d')]) && $now->format('H:i:s') >= $term['early_closures'][$now->format('Y-m-d')]) return 'Classes are cancelled for the remainder of today.';
    foreach ($meetings as $m) {
        if ($m['day_Of_Week'] !== $now->format('l') || empty($m['start_Time']) || empty($m['end_Time'])) continue;
        if (($m['start_Date'] && $now->format('Y-m-d') < $m['start_Date']) || ($m['end_Date'] && $now->format('Y-m-d') > $m['end_Date'])) continue;
        if ($now->format('H:i:s') >= $m['start_Time'] && $now->format('H:i:s') < $m['end_Time']) return null;
    }
    return 'Attendance can only be recorded during this section’s scheduled class period today.';
}
function faculty_section(PDO $pdo, int $fid, int $crn, bool $lock=false): array {
    $rows=faculty_rows($pdo,'SELECT cs.*,s.semester_Name,c.course_Name FROM Course_Section cs JOIN Semester s ON s.semester_ID=cs.semester_ID JOIN Course c ON c.course_ID=cs.course_ID WHERE cs.CRN=? AND cs.faculty_ID=?'.($lock?' FOR UPDATE':''),[$crn,$fid]);
    if (!$rows) throw new DomainException('Choose one of your assigned sections.');
    return $rows[0];
}
function faculty_mark_rows(PDO $pdo, array $section, string $date, bool $lock=false): array {
    // Enrollment only: historical-only students are never editable by faculty.
    return faculty_rows($pdo,'SELECT e.student_ID,e.Grade,u.first_Name,u.last_Name,a.Present_Absent FROM Enrollment e JOIN User u ON u.user_ID=e.student_ID LEFT JOIN Attendance a ON a.student_ID=e.student_ID AND a.CRN=e.CRN AND a.attendance_Date=? WHERE e.CRN=? AND e.semester_ID=? ORDER BY e.student_ID'.($lock?' FOR UPDATE':''),[$date,$section['CRN'],$section['semester_ID']]);
}
function faculty_meetings(PDO $pdo, int $crn): array {
    return faculty_rows($pdo,'SELECT mt.* FROM '.academic_portal_meeting_relation($pdo).' mt WHERE mt.CRN=?',[$crn]);
}
function faculty_marks_version(array $section, string $date, string $mode, array $rows): string {
    return faculty_version([(int)$section['CRN'], $date, $mode, $rows]);
}
function faculty_save_marks(PDO $pdo, int $fid, int $crn, string $mode, array $marks, string $version, array $policy, ?DateTimeImmutable $clock=null): void {
    if (!in_array($mode,['attendance','grades'],true)) throw new DomainException('Invalid operation.');
    try {
        $pdo->beginTransaction(); faculty_account($pdo,$fid,true);
        $section=faculty_section($pdo,$fid,$crn,true);
        $now=$clock ?? academic_today(); $date=$now->setTimezone(new DateTimeZone('America/New_York'))->format('Y-m-d');
        $reason=faculty_write_window($mode,$section,faculty_meetings($pdo,$crn),$policy,$now);
        if ($reason !== null) throw new DomainException($reason);
        $rows=faculty_mark_rows($pdo,$section,$date,true);
        if (!hash_equals(faculty_marks_version($section,$date,$mode,$rows),$version)) throw new DomainException('The roster or marks changed. Reload before saving.');
        $byID=array_column($rows,null,'student_ID'); $changes=0;
        foreach ($marks as $sid=>$value) {
            $sid=faculty_id((string)$sid,'student');
            if (!isset($byID[$sid]) || !is_string($value)) throw new DomainException('A submitted student is not in this section.');
            if ($value === '') continue;
            $allowed=$mode === 'attendance' ? ['Present','Absent'] : $policy[$section['semester_Name']]['grading'][$crn]['allowed_grades'];
            if (!in_array($value,$allowed,true) || strlen($value)>($mode==='attendance'?10:5)) throw new DomainException('Select a permitted mark for each student.');
            if ($mode === 'attendance') {
                $exists=faculty_rows($pdo,'SELECT Present_Absent FROM Attendance WHERE student_ID=? AND CRN=? AND attendance_Date=? FOR UPDATE',[$sid,$crn,$date]);
                if ($exists) $pdo->prepare('UPDATE Attendance SET Present_Absent=? WHERE student_ID=? AND CRN=? AND attendance_Date=?')->execute([$value,$sid,$crn,$date]);
                else $pdo->prepare('INSERT INTO Attendance (student_ID,CRN,course_ID,attendance_Date,Present_Absent) VALUES (?,?,?,?,?)')->execute([$sid,$crn,$section['course_ID'],$date,$value]);
            } else {
                if ($byID[$sid]['Grade'] !== null && $byID[$sid]['Grade'] !== $value) throw new DomainException('An existing final grade can only be changed by an administrator.');
                $history=faculty_rows($pdo,'SELECT Grade FROM Student_History WHERE student_ID=? AND CRN=? FOR UPDATE',[$sid,$crn]);
                if ($history && $history[0]['Grade'] !== null && $history[0]['Grade'] !== $value) throw new DomainException('This student already has a recorded grade. Contact an administrator.');
                $pdo->prepare('UPDATE Enrollment SET Grade=? WHERE student_ID=? AND CRN=? AND semester_ID=?')->execute([$value,$sid,$crn,$section['semester_ID']]);
                // Keep a preexisting history attempt consistent; do not run term rollover.
                if ($history) $pdo->prepare('UPDATE Student_History SET Grade=? WHERE student_ID=? AND CRN=?')->execute([$value,$sid,$crn]);
            }
            $changes++;
        }
        if ($changes===0) throw new DomainException('Choose at least one mark before saving.');
        $reason=faculty_write_window($mode,$section,faculty_meetings($pdo,$crn),$policy,$clock ?? academic_today());
        if ($reason !== null) throw new DomainException($reason);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
