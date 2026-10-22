<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['Admin']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/schedule.php';
require_admin($pdo);
$pdo->exec("SET time_zone = '+00:00'");
header('Cache-Control: no-store');
$_SESSION['master_schedule_token'] ??= bin2hex(random_bytes(32));
function section_record(PDO $pdo, int $crn, bool $lock = false): ?array
{
    $stmt = $pdo->prepare('SELECT CRN, course_ID, section_No, faculty_ID, time_Slot_ID, room_ID, semester_ID, available_Seats FROM Course_Section WHERE CRN = ?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$crn]);
    return $stmt->fetch() ?: null;
}
function section_version(array $record): string
{
    return hash_hmac('sha256', json_encode($record, JSON_THROW_ON_ERROR), $_SESSION['master_schedule_token']);
}
function section_integer($value, int $minimum = 1): int|false
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => 2147483647]]);
}
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$crn = section_integer(($isPost ? $_POST : $_GET)['crn'] ?? null) ?: 0;
$section = section_record($pdo, $crn);
$semester = null;
if ($section) {
    $stmt = $pdo->prepare("SELECT semester_Name FROM Semester WHERE semester_ID = ? AND semester_Name = 'Fall 2026'");
    $stmt->execute([$section['semester_ID']]);
    $semester = $stmt->fetchColumn();
}
if (!$section || !$semester) {
    http_response_code(404);
    page_start('Edit Course Section', 'Admin', 'admin/master-schedule.php');
    echo '<p role="alert">Fall 2026 course section could not be found.</p>';
    page_end();
    exit;
}
$values = $section;
$enrolledCount = schedule_enrolled_count($pdo, (int)$section['semester_ID'], $crn);
$values['available_Seats'] = SCHEDULE_MAXIMUM_SEATS - $enrolledCount;
$version = section_version($section);
$error = '';
if ($isPost) {
    $version = is_string($_POST['record_version'] ?? null) ? $_POST['record_version'] : '';
    $token = is_string($_POST['form_token'] ?? null) ? $_POST['form_token'] : '';
    if (!hash_equals($_SESSION['master_schedule_token'], $token)) {
        $error = 'Please reload the section and try again.';
    }
    foreach (['course_ID', 'section_No', 'faculty_ID', 'time_Slot_ID', 'room_ID'] as $field) {
        $input = $_POST[$field] ?? null;
        if ($field === 'course_ID') {
            $values[$field] = is_string($input) ? trim($input) : '';
            if (!preg_match('/^[A-Z]+[0-9]+$/D', $values[$field]) || strlen($values[$field]) > 20) {
                $error = 'Select a valid course code.';
            }
        } elseif ($field === 'room_ID' && $input === '') {
            $values[$field] = null;
        } elseif ($field === 'room_ID') {
            $values[$field] = is_string($input) ? trim($input) : '';
            if (!preg_match('/^[A-Z]+[0-9]+$/D', $values[$field]) || strlen($values[$field]) > 20) {
                $error = 'Select a valid room code.';
            }
        } else {
            $values[$field] = section_integer($input);
            if ($values[$field] === false) {
                $error = 'Select valid course, faculty, time slot, and room values, and a positive section number.';
            }
        }
    }
    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT semester_Name FROM Semester WHERE semester_ID = ? FOR UPDATE');
            $stmt->execute([$section['semester_ID']]);
            if ($stmt->fetchColumn() !== 'Fall 2026') {
                throw new DomainException('This section is no longer in Fall 2026.');
            }
            $before = section_record($pdo, $crn, true);
            if (!$before || $before['semester_ID'] !== $section['semester_ID'] || !hash_equals(section_version($before), $version)) {
                throw new DomainException('This section changed after you opened it. Reload the section before saving.');
            }
            $after = $before;
            foreach (['course_ID', 'section_No', 'faculty_ID', 'time_Slot_ID', 'room_ID'] as $field) {
                $after[$field] = $values[$field];
            }
            if ($after['time_Slot_ID'] !== $before['time_Slot_ID'] && academic_semester_status($semester) !== 'Upcoming') {
                throw new DomainException('The time slot cannot change after the semester has started.');
            }
            $after['available_Seats'] = SCHEDULE_MAXIMUM_SEATS - schedule_enrolled_count($pdo, (int)$before['semester_ID'], $crn);
            if ($after['available_Seats'] < 0) throw new DomainException('This section exceeds its maximum capacity of 10.');
            if ($after !== $before) {
                foreach (['course_ID' => ['Course', 'course_ID'], 'faculty_ID' => ['Faculty', 'faculty_ID'], 'time_Slot_ID' => ['Time_Slot', 'time_Slot_ID'], 'room_ID' => ['Room', 'room_ID']] as $field => [$table, $column]) {
                    if ($after[$field] === null) {
                        continue;
                    }
                    $stmt = $pdo->prepare("SELECT `$column` FROM `$table` WHERE `$column` = ? FOR SHARE");
                    $stmt->execute([$after[$field]]);
                    if (!$stmt->fetchColumn()) {
                        throw new DomainException('The selected course, faculty, time slot, or room is not available.');
                    }
                }
                if ($after['room_ID'] !== null) {
                    $stmt = $pdo->prepare("SELECT room_Type FROM Room WHERE room_ID = ?");
                    $stmt->execute([$after['room_ID']]);
                    if (!in_array($stmt->fetchColumn(), ['Lecture', 'Lab'], true)) {
                        throw new DomainException('Select a Lecture or Lab room. Office rooms cannot host course sections.');
                    }
                }
                if ($after['course_ID'] !== $before['course_ID']) {
                    foreach (['Enrollment', 'Attendance', 'Student_History', 'Faculty_History'] as $table) {
                        $stmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE CRN = ? LIMIT 1");
                        $stmt->execute([$crn]);
                        if ($stmt->fetchColumn()) {
                            throw new DomainException('The course cannot change because this section has enrollment or academic records.');
                        }
                    }
                }
                if ($after['course_ID'] !== $before['course_ID'] || $after['room_ID'] !== $before['room_ID'] || $after['time_Slot_ID'] !== $before['time_Slot_ID']) {
                    $stmt = $pdo->prepare('SELECT c.course_Credits, r.room_Type FROM Course c LEFT JOIN Room r ON r.room_ID = ? WHERE c.course_ID = ?');
                    $stmt->execute([$after['room_ID'], $after['course_ID']]);
                    $meeting = $stmt->fetch();
                    $minutes = (int) $meeting['course_Credits'] === 3 ? 90 : ($meeting['room_Type'] === 'Lab' ? 180 : 120);
                    $stmt = $pdo->prepare('SELECT TIMESTAMPDIFF(MINUTE, p.start_Time, p.end_Time) AS duration FROM Time_Slot_Period tp JOIN Period p ON p.period_ID = tp.period_ID WHERE tp.time_Slot_ID = ?');
                    $stmt->execute([$after['time_Slot_ID']]);
                    $durations = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    if (!$durations || array_filter($durations, static fn($duration) => (int) $duration !== $minutes)) {
                        throw new DomainException('Choose a time slot with a ' . $minutes . '-minute meeting for this course and room type.');
                    }
                }
                $stmt = $pdo->prepare('SELECT CRN FROM Course_Section WHERE semester_ID = ? AND course_ID = ? AND section_No = ? AND CRN <> ? LIMIT 1');
                $stmt->execute([$after['semester_ID'], $after['course_ID'], $after['section_No'], $crn]);
                if ($stmt->fetchColumn()) {
                    throw new DomainException('That course and section number already exist in Fall 2026.');
                }
                $timeChanged = $after['time_Slot_ID'] !== $before['time_Slot_ID'];
                $checkFaculty = $timeChanged || $after['faculty_ID'] !== $before['faculty_ID'];
                $checkRoom = $after['room_ID'] !== null && ($timeChanged || $after['room_ID'] !== $before['room_ID']);
                if ($checkFaculty || $checkRoom) {
                    $stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM Time_Slot_Day WHERE time_Slot_ID = ?) AS days, (SELECT COUNT(*) FROM Time_Slot_Period WHERE time_Slot_ID = ?) AS periods');
                    $stmt->execute([$after['time_Slot_ID'], $after['time_Slot_ID']]);
                    $mapping = $stmt->fetch();
                    if (!$mapping['days'] || !$mapping['periods']) {
                        throw new DomainException('The time slot needs day and period mappings before it can be assigned.');
                    }
                    $stmt = $pdo->prepare(
                        'SELECT DISTINCT cs.CRN FROM Course_Section cs
                         JOIN Time_Slot_Day od ON od.time_Slot_ID = cs.time_Slot_ID
                         JOIN Time_Slot_Day nd ON nd.time_Slot_ID = ? AND nd.day_ID = od.day_ID
                         JOIN Time_Slot_Period op ON op.time_Slot_ID = cs.time_Slot_ID
                         JOIN Period oldp ON oldp.period_ID = op.period_ID
                         JOIN Time_Slot_Period np ON np.time_Slot_ID = ?
                         JOIN Period newp ON newp.period_ID = np.period_ID
                         WHERE cs.semester_ID = ? AND cs.CRN <> ?
                           AND newp.start_Time < oldp.end_Time AND newp.end_Time > oldp.start_Time
                           AND ((? = 1 AND cs.faculty_ID = ?) OR (? = 1 AND cs.room_ID = ?))
                         ORDER BY cs.CRN LIMIT 1'
                    );
                    $stmt->execute([$after['time_Slot_ID'], $after['time_Slot_ID'], $after['semester_ID'], $crn, (int) $checkFaculty, $after['faculty_ID'], (int) $checkRoom, $after['room_ID']]);
                    if ($conflict = $stmt->fetchColumn()) {
                        throw new DomainException('The instructor or room overlaps with section CRN ' . $conflict . '. Choose another assignment.');
                    }
                }
                if ($timeChanged) {
                    $stmt = $pdo->prepare(
                        'SELECT DISTINCT cs.CRN FROM Enrollment mine
                         JOIN Enrollment other ON other.student_ID = mine.student_ID AND other.CRN <> mine.CRN
                         JOIN Course_Section cs ON cs.CRN = other.CRN
                         JOIN Time_Slot_Day od ON od.time_Slot_ID = cs.time_Slot_ID
                         JOIN Time_Slot_Day nd ON nd.time_Slot_ID = ? AND nd.day_ID = od.day_ID
                         JOIN Time_Slot_Period op ON op.time_Slot_ID = cs.time_Slot_ID
                         JOIN Period oldp ON oldp.period_ID = op.period_ID
                         JOIN Time_Slot_Period np ON np.time_Slot_ID = ?
                         JOIN Period newp ON newp.period_ID = np.period_ID
                         WHERE mine.CRN = ? AND cs.semester_ID = ?
                           AND newp.start_Time < oldp.end_Time AND newp.end_Time > oldp.start_Time
                         ORDER BY cs.CRN LIMIT 1'
                    );
                    $stmt->execute([$after['time_Slot_ID'], $after['time_Slot_ID'], $crn, $after['semester_ID']]);
                    if ($conflict = $stmt->fetchColumn()) {
                        throw new DomainException('An enrolled student has an overlapping class in section CRN ' . $conflict . '. Choose another time slot.');
                    }
                }
                $stmt = $pdo->prepare('UPDATE Course_Section SET course_ID = ?, section_No = ?, faculty_ID = ?, time_Slot_ID = ?, room_ID = ?, available_Seats = ? WHERE CRN = ? AND semester_ID = ?');
                $stmt->execute([$after['course_ID'], $after['section_No'], $after['faculty_ID'], $after['time_Slot_ID'], $after['room_ID'], $after['available_Seats'], $crn, $after['semester_ID']]);
                $stmt = $pdo->prepare('INSERT INTO Course_Section_Update_Log (CRN, admin_ID, before_Data, after_Data) VALUES (?, ?, ?, ?)');
                $stmt->execute([$crn, (int) $_SESSION['user_id'], json_encode($before, JSON_THROW_ON_ERROR), json_encode($after, JSON_THROW_ON_ERROR)]);
                // Refresh an already saved schedule in the same transaction as the section edit.
                $snapshot = ['semester_Name' => $semester, 'sections' => schedule_sections($pdo, (int) $after['semester_ID'], true)];
                $stmt = $pdo->prepare('UPDATE Master_Schedule SET admin_ID = ?, saved_At = CURRENT_TIMESTAMP(6), schedule_Data = ? WHERE semester_ID = ?');
                $stmt->execute([(int) $_SESSION['user_id'], json_encode($snapshot, JSON_THROW_ON_ERROR), $after['semester_ID']]);
                $successNotice = 'Course section updated successfully.';
            } else {
                $successNotice = 'No section information changed.';
            }
            $pdo->commit();
            $_SESSION['master_schedule_notice'] = $successNotice;
            header('Location: master-schedule.php?semester_id=' . $section['semester_ID']);
            exit;
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Unable to save the section and its history. Please try again.';
        }
    }
}
$courses = $pdo->query('SELECT course_ID, course_Name FROM Course ORDER BY course_Name, course_ID')->fetchAll();
$faculty = $pdo->query('SELECT f.faculty_ID, u.first_Name, u.middle_Name, u.last_Name FROM Faculty f JOIN User u ON u.user_ID = f.faculty_ID ORDER BY u.last_Name, u.first_Name, f.faculty_ID')->fetchAll();
$slots = $pdo->query('SELECT time_Slot_ID, time_Slot_Day, time_Slot_Period FROM Time_Slot ORDER BY time_Slot_ID')->fetchAll();
$rooms = $pdo->query("SELECT r.room_ID, b.Bldg_Name, r.room_Number, r.room_Type FROM Room r JOIN Building b ON b.Bldg_ID = r.Bldg_ID WHERE r.room_Type IN ('Lecture', 'Lab') ORDER BY b.Bldg_Name, r.room_Number")->fetchAll();
$stmt = $pdo->prepare('SELECT admin_ID, updated_At, before_Data, after_Data FROM Course_Section_Update_Log WHERE CRN = ? ORDER BY log_ID DESC LIMIT 20');
$stmt->execute([$crn]);
$history = $stmt->fetchAll();
$labels = ['course_ID' => 'Course', 'section_No' => 'Section Number', 'faculty_ID' => 'Faculty', 'time_Slot_ID' => 'Time Slot', 'room_ID' => 'Room', 'available_Seats' => 'Available Seats'];
page_start('Edit Course Section', 'Admin', 'admin/master-schedule.php');
?>
<p><a href="master-schedule.php?semester_id=<?= e($section['semester_ID']) ?>">Back to Master Schedule</a></p>
<p><strong>CRN:</strong> <?= e($crn) ?> · <strong>Semester:</strong> <?= e($semester) ?> (ID <?= e($section['semester_ID']) ?>)</p>
<?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="user-form" method="post" action="edit-section.php">
    <input type="hidden" name="crn" value="<?= e($crn) ?>">
    <input type="hidden" name="form_token" value="<?= e($_SESSION['master_schedule_token']) ?>">
    <input type="hidden" name="record_version" value="<?= e($version) ?>">
    <div class="form-group"><label for="course_ID">Course</label><select id="course_ID" name="course_ID" required>
        <?php foreach ($courses as $course): ?><option value="<?= e($course['course_ID']) ?>" <?= (string) $values['course_ID'] === (string) $course['course_ID'] ? 'selected' : '' ?>><?= e(schedule_course_label($course['course_ID']) . ' · ' . $course['course_Name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label for="section_No">Section Number</label><input type="number" id="section_No" name="section_No" min="1" max="2147483647" value="<?= e($values['section_No']) ?>" required></div>
    <div class="form-group"><label for="faculty_ID">Faculty</label><select id="faculty_ID" name="faculty_ID" required>
        <?php foreach ($faculty as $person): ?>
            <?php $name = implode(' ', array_filter([$person['first_Name'], $person['middle_Name'], $person['last_Name']], static fn($part) => $part !== null && $part !== '')); ?>
            <option value="<?= e($person['faculty_ID']) ?>" <?= (int) $values['faculty_ID'] === (int) $person['faculty_ID'] ? 'selected' : '' ?>><?= e($person['faculty_ID'] . ' · ' . $name) ?></option>
        <?php endforeach; ?>
    </select></div>
    <div class="form-group"><label for="time_Slot_ID">Time Slot</label><select id="time_Slot_ID" name="time_Slot_ID" required>
        <?php foreach ($slots as $slot): ?><option value="<?= e($slot['time_Slot_ID']) ?>" <?= (int) $values['time_Slot_ID'] === (int) $slot['time_Slot_ID'] ? 'selected' : '' ?>><?= e($slot['time_Slot_ID'] . ' · ' . schedule_day_label($slot['time_Slot_Day']) . ' · ' . schedule_time_label($slot['time_Slot_Period'])) ?></option><?php endforeach; ?>
    </select></div>
    <div class="form-group"><label for="room_ID">Room (optional)</label><select id="room_ID" name="room_ID">
        <option value="" <?= $values['room_ID'] === null ? 'selected' : '' ?>>No room</option>
        <?php foreach ($rooms as $room): ?><option value="<?= e($room['room_ID']) ?>" <?= $values['room_ID'] !== null && (string) $values['room_ID'] === (string) $room['room_ID'] ? 'selected' : '' ?>><?= e($room['room_ID'] . ' · ' . $room['Bldg_Name'] . ' · Room ' . $room['room_Number'] . ' · ' . $room['room_Type']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="grid2">
        <div class="form-group"><label for="maximum_Seats">Maximum Seats</label><input id="maximum_Seats" type="number" value="<?= e(SCHEDULE_MAXIMUM_SEATS) ?>" readonly></div>
        <div class="form-group"><label for="available_Seats">Available Seats</label><input id="available_Seats" type="number" value="<?= e($values['available_Seats']) ?>" readonly></div>
    </div>
    <button class="btn" type="submit">Save Changes</button>
</form>
<h2>Section History</h2>
<?php if (!$history): ?><p>No section changes recorded.</p><?php endif; ?>
<?php foreach ($history as $entry): ?>
    <?php $before = json_decode($entry['before_Data'], true); $after = json_decode($entry['after_Data'], true); ?>
    <details class="update-history"><summary><?= e($entry['updated_At']) ?> UTC · Admin ID <?= e($entry['admin_ID']) ?></summary>
        <div class="table-scroll"><table><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>
            <?php foreach ($labels as $field => $label): ?>
                <?php if ($before[$field] !== $after[$field]): ?><tr><td><?= e($label) ?></td><td><?= e($before[$field] ?? 'No room') ?></td><td><?= e($after[$field] ?? 'No room') ?></td></tr><?php endif; ?>
            <?php endforeach; ?>
        </tbody></table></div>
    </details>
<?php endforeach; ?>
<?php page_end(); ?>
