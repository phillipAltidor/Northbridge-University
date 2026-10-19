<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['Admin']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin.php';
require_admin($pdo);
$pdo->exec("SET time_zone = '+00:00'");
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/schedule.php';

function schedule_version(array $schedule): string
{
    return hash_hmac('sha256', json_encode($schedule, JSON_THROW_ON_ERROR), $_SESSION['master_schedule_token']);
}
$_SESSION['master_schedule_token'] ??= bin2hex(random_bytes(32));
$semesters = $pdo->query(
    "SELECT semester_ID, semester_Name FROM Semester
     WHERE semester_Name REGEXP '^(Fall 202[2-6]|Spring 202[3-7])$'
     ORDER BY CAST(SUBSTRING_INDEX(semester_Name, ' ', -1) AS UNSIGNED),
              CASE WHEN semester_Name LIKE 'Spring%' THEN 0 ELSE 1 END,
              semester_ID"
)->fetchAll();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$input = $isPost ? $_POST : $_GET;
$semesterID = filter_var($input['semester_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$semester = null;
foreach ($semesters as $option) {
    if ((int) $option['semester_ID'] === $semesterID) {
        $semester = $option;
        break;
    }
}
$canModify = $semester && $semester['semester_Name'] === 'Fall 2026';
$error = '';
if (($semesterID || $isPost) && !$semester) {
    http_response_code(400);
    $error = 'Please select an available semester.';
}
$notice = $_SESSION['master_schedule_notice'] ?? '';
unset($_SESSION['master_schedule_notice']);
if ($isPost && $semester) {
    $token = is_string($_POST['form_token'] ?? null) ? $_POST['form_token'] : '';
    $version = is_string($_POST['schedule_version'] ?? null) ? $_POST['schedule_version'] : '';
    if (!$canModify) {
        http_response_code(403);
        $error = 'Only the Fall 2026 master schedule can be saved.';
    } elseif (!hash_equals($_SESSION['master_schedule_token'], $token)) {
        $error = 'Please reload the schedule and try again.';
    } else {
        try {
            $pdo->beginTransaction();
            // Serialize saves for this semester, including repeated submissions.
            $stmt = $pdo->prepare('SELECT semester_Name FROM Semester WHERE semester_ID = ? FOR UPDATE');
            $stmt->execute([$semesterID]);
            $semesterName = $stmt->fetchColumn();
            if ($semesterName !== 'Fall 2026') {
                $pdo->rollBack();
                $error = 'Please select Fall 2026.';
            } else {
                $stmt = $pdo->prepare('SELECT semester_ID FROM Master_Schedule WHERE semester_ID = ?');
                $stmt->execute([$semesterID]);
                if ($stmt->fetchColumn()) {
                    $pdo->rollBack();
                    $_SESSION['master_schedule_notice'] = 'The Fall 2026 master schedule has already been saved.';
                    header('Location: master-schedule.php?semester_id=' . $semesterID);
                    exit;
                }
                $snapshot = ['semester_Name' => $semesterName, 'sections' => schedule_sections($pdo, $semesterID, true)];
                if (!$snapshot['sections']) {
                    $pdo->rollBack();
                    $error = 'There are no Fall 2026 course sections to save.';
                } elseif (!hash_equals(schedule_version($snapshot), $version)) {
                    $pdo->rollBack();
                    $error = 'The course sections changed after you opened this page. Review the refreshed schedule before saving.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO Master_Schedule (semester_ID, admin_ID, schedule_Data) VALUES (?, ?, ?)');
                    $stmt->execute([$semesterID, (int) $_SESSION['user_id'], json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                    $pdo->commit();
                    $_SESSION['master_schedule_notice'] = 'Fall 2026 master schedule saved successfully.';
                    header('Location: master-schedule.php?semester_id=' . $semesterID);
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Unable to save the master schedule. Please try again.';
        }
    }
}
$saved = null;
$snapshot = null;
if ($semester) {
    $stmt = $pdo->prepare('SELECT admin_ID, saved_At, schedule_Data FROM Master_Schedule WHERE semester_ID = ?');
    $stmt->execute([$semesterID]);
    $saved = $stmt->fetch() ?: null;
    $snapshot = ['semester_Name' => $semester['semester_Name'], 'sections' => schedule_sections($pdo, $semesterID)];
}
page_start('Master Schedule', 'Admin', 'admin/master-schedule.php');
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert success" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if (!$semesters): ?>
    <p>No semesters are available.</p>
<?php else: ?>
    <form class="user-form" method="get" action="master-schedule.php">
        <div class="form-group">
            <label for="semester_id">Semester</label>
            <select id="semester_id" name="semester_id" required onchange="if (this.value) this.form.requestSubmit();">
                <option value="">Select a semester</option>
                <?php foreach ($semesters as $option): ?>
                    <option value="<?= e($option['semester_ID']) ?>" <?= (int) $option['semester_ID'] === $semesterID ? 'selected' : '' ?>><?= e($option['semester_Name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
<?php endif; ?>
<?php if ($snapshot): ?>
    <h2><?= e($snapshot['semester_Name']) ?> Master Schedule</h2>
    <?php $sections = $snapshot['sections']; ?>
    <p>Semester ID <?= e($semesterID) ?> · <?= count($sections) ?> course sections · <?= count(array_unique(array_column($sections, 'course_ID'))) ?> courses</p>
    <?php if ($saved): ?>
        <p>Saved <?= e($saved['saved_At']) ?> UTC by Admin ID <?= e($saved['admin_ID']) ?>.</p>
    <?php elseif ($sections && $canModify): ?>
        <form method="post" action="master-schedule.php">
            <input type="hidden" name="semester_id" value="<?= e($semesterID) ?>">
            <input type="hidden" name="form_token" value="<?= e($_SESSION['master_schedule_token']) ?>">
            <input type="hidden" name="schedule_version" value="<?= e(schedule_version($snapshot)) ?>">
            <button class="btn" type="submit">Save Master Schedule</button>
        </form>
    <?php elseif (!$sections): ?>
        <p>No course sections are available for this semester.</p>
    <?php endif; ?>
    <?php if ($sections): ?>
        <div class="table-scroll"><table>
            <thead><tr><th>CRN</th><th>Course</th><th>Section</th><th>Faculty</th><th>Days</th><th>Time</th><th>Room</th><th>Available Seats</th><?php if ($canModify): ?><th>Action</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($sections as $section): ?>
                <?php $instructor = implode(' ', array_filter([$section['first_Name'], $section['middle_Name'], $section['last_Name']], static fn($part) => $part !== null && $part !== '')); ?>
                <tr>
                    <td><?= e($section['CRN']) ?></td>
                    <td><?= e(schedule_course_label($section['course_ID']) . ' · ' . $section['course_Name']) ?></td>
                    <td><?= e($section['section_No']) ?></td>
                    <td><?= e($instructor) ?></td>
                    <td><?= e(schedule_day_label($section['time_Slot_Day'])) ?></td>
                    <td><?= e(schedule_time_label($section['time_Slot_Period'])) ?></td>
                    <td><?= e($section['room_ID'] ?? 'Unassigned') ?></td>
                    <td><?= e($section['available_Seats']) ?></td>
                    <?php if ($canModify): ?><td><a class="btn small" href="edit-section.php?crn=<?= e($section['CRN']) ?>">Edit</a></td><?php endif; ?>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
<?php endif; ?>
<?php page_end(); ?>
