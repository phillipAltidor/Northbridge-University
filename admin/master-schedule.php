<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['Admin']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin.php';
require_admin($pdo);
$pdo->exec("SET time_zone = '+00:00'");
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/schedule.php';
require_once __DIR__ . '/../includes/academic-timeline.php';

function schedule_version(array $schedule): string
{
    return hash_hmac('sha256', json_encode($schedule, JSON_THROW_ON_ERROR), $_SESSION['master_schedule_token']);
}

function schedule_pagination(array $page, array $params): void
{
    ?>
    <nav class="schedule-pagination" aria-label="Schedule pages">
        <?php foreach ([['First page', '&laquo;', 1, $page['page'] === 1], ['Previous page', '&lsaquo;', $page['page'] - 1, $page['page'] === 1]] as [$label, $symbol, $target, $disabled]): ?>
            <?php if ($disabled): ?><span aria-disabled="true" aria-label="<?= e($label) ?>"><?= $symbol ?></span>
            <?php else: ?><a href="master-schedule.php?<?= e(http_build_query([...$params, 'page' => $target])) ?>#schedule-results" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><?= $symbol ?></a><?php endif; ?>
        <?php endforeach; ?>
        <span class="schedule-page-number">Page <?= $page['page'] ?> of <?= $page['pages'] ?></span>
        <?php foreach ([['Next page', '&rsaquo;', $page['page'] + 1, $page['page'] === $page['pages']], ['Last page', '&raquo;', $page['pages'], $page['page'] === $page['pages']]] as [$label, $symbol, $target, $disabled]): ?>
            <?php if ($disabled): ?><span aria-disabled="true" aria-label="<?= e($label) ?>"><?= $symbol ?></span>
            <?php else: ?><a href="master-schedule.php?<?= e(http_build_query([...$params, 'page' => $target])) ?>#schedule-results" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><?= $symbol ?></a><?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
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
$years = array_values(array_unique(array_map(static fn($s) => substr($s['semester_Name'], -4), $semesters)));
$year = is_string($input['year'] ?? null) && in_array($input['year'], $years, true) ? $input['year'] : '';
$semesterOptions = array_values(array_filter($semesters, static fn($s) => $year === '' || str_ends_with($s['semester_Name'], $year)));
$query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 200, 'UTF-8') : '';
$department = filter_var($_GET['department'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$availableOnly = ($_GET['available'] ?? '') === '1';
$semesterID = filter_var($input['semester_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$semester = null;
foreach ($semesterOptions as $option) {
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
$sections = $snapshot['sections'] ?? [];
$departments = [];
foreach ($sections as $row) $departments[$row['dept_ID']] = $row['dept_Name'];
asort($departments);
$visibleSections = schedule_filter_sections($sections, $query, $department, $availableOnly);
$pageData = schedule_page($visibleSections, (int)(filter_var($_GET['per_page'] ?? 25, FILTER_VALIDATE_INT) ?: 25), (int)(filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
$pageParams = ['semester_id' => $semesterID, 'year' => $year, 'q' => $query, 'department' => $department, 'available' => $availableOnly ? '1' : '', 'per_page' => $pageData['per_page']];
page_start('Master Schedule', 'Admin', 'admin/master-schedule.php');
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert success" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if (!$semesters): ?>
    <p>No semesters are available.</p>
<?php else: ?>
    <form class="schedule-filters" method="get" action="master-schedule.php">
        <div class="form-group">
            <label for="schedule_year">Year</label>
            <select id="schedule_year" name="year" onchange="this.form.elements.semester_id.value=''; this.form.requestSubmit();">
                <option value="">All Years</option>
                <?php foreach ($years as $optionYear): ?><option value="<?= e($optionYear) ?>" <?= $year === $optionYear ? 'selected' : '' ?>><?= e($optionYear) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="semester_id">Semester</label>
            <select id="semester_id" name="semester_id" onchange="this.form.requestSubmit();">
                <option value="">Select a semester</option>
                <?php foreach ($semesterOptions as $option): ?>
                    <option value="<?= e($option['semester_ID']) ?>" <?= (int) $option['semester_ID'] === $semesterID ? 'selected' : '' ?>><?= e($option['semester_Name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group schedule-search">
            <label for="course_search">Course Code, Name, or CRN</label>
            <input id="course_search" name="q" type="search" value="<?= e($query) ?>" maxlength="200">
        </div>
        <?php if ($semester): ?>
        <div class="form-group">
            <label for="schedule_department">Department</label>
            <select id="schedule_department" name="department" onchange="this.form.requestSubmit();">
                <option value="">All Departments</option>
                <?php foreach ($departments as $id => $label): ?><option value="<?= e($id) ?>" <?= $department === (int)$id ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <label class="schedule-available"><input type="checkbox" name="available" value="1" <?= $availableOnly ? 'checked' : '' ?> onchange="this.form.requestSubmit();">Available Seats Only</label>
        <div class="form-group schedule-page-size">
            <label for="schedule_per_page">Sections per Page</label>
            <select id="schedule_per_page" name="per_page" onchange="this.form.requestSubmit();">
                <?php foreach ([25, 50] as $size): ?><option value="<?= $size ?>" <?= $pageData['per_page'] === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="btn" type="submit">Search</button>
        <?php if ($query !== '' || $department || $availableOnly): ?><a href="master-schedule.php?<?= e(http_build_query(['semester_id' => $semesterID, 'year' => $year])) ?>">Clear Filters</a><?php endif; ?>
    </form>
<?php endif; ?>
<?php if ($snapshot): ?>
    <h2><?= e($snapshot['semester_Name']) ?> Master Schedule</h2>
    <?php $timeline = require __DIR__ . '/../config/academic-timeline.php'; ?>
    <p><strong><?= e(academic_semester_status($snapshot['semester_Name'])) ?></strong> &middot; As of <?= e((new DateTimeImmutable($timeline['as_of']))->format('F j, Y')) ?></p>
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
        <div class="schedule-results-bar" id="schedule-results">
            <p>Showing <?= $pageData['start'] ?>&ndash;<?= $pageData['end'] ?> of <?= $pageData['total'] ?> matching sections</p>
            <?php schedule_pagination($pageData, $pageParams); ?>
        </div>
        <div class="schedule-scroll-top" role="region" aria-label="Horizontal table scroll" tabindex="0" hidden><div></div></div>
        <div class="table-scroll schedule-table-scroll" role="region" aria-label="Course sections" tabindex="0"><table class="master-schedule-table">
            <thead><tr><th scope="col">CRN</th><th scope="col">Course Code</th><th scope="col">Course Name</th><th scope="col">Credits</th><th scope="col">Faculty</th><th scope="col">Meeting Days</th><th scope="col">Start Time</th><th scope="col">End Time</th><th scope="col">Building</th><th scope="col">Room</th><th scope="col">Section</th><th scope="col">Max Seats</th><th scope="col">Available Seats</th><th scope="col">Semester</th><th scope="col">Year</th><?php if ($canModify): ?><th scope="col">Action</th><?php endif; ?></tr></thead>
            <tbody><?php foreach ($pageData['rows'] as $section): ?>
                <?php $instructor = implode(' ', array_filter([$section['first_Name'], $section['middle_Name'], $section['last_Name']], static fn($part) => $part !== null && $part !== '')); ?>
                <tr>
                    <td><?= e($section['CRN']) ?></td>
                    <td><?= e(schedule_course_label($section['course_ID'])) ?></td>
                    <td><?= e($section['course_Name']) ?></td>
                    <td><?= e($section['course_Credits']) ?></td>
                    <td><?= e($instructor) ?></td>
                    <td><?= e(schedule_day_label($section['time_Slot_Day'])) ?></td>
                    <td><?= e($section['start_Time'] ?? 'Unassigned') ?></td>
                    <td><?= e($section['end_Time'] ?? 'Unassigned') ?></td>
                    <td><?= e($section['Bldg_Name'] ?? 'Unassigned') ?></td>
                    <td><?= e($section['room_Number'] ?? 'Unassigned') ?></td>
                    <td><?= e($section['section_No']) ?></td>
                    <td><?= e($section['maximum_Seats']) ?></td>
                    <td><?= e($section['available_Seats']) ?></td>
                    <td><?= e(explode(' ', $snapshot['semester_Name'])[0]) ?></td>
                    <td><?= e(substr($snapshot['semester_Name'], -4)) ?></td>
                    <?php if ($canModify): ?><td><a class="btn small" href="edit-section.php?crn=<?= e($section['CRN']) ?>">Edit</a></td><?php endif; ?>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <div class="schedule-results-bar"><?php schedule_pagination($pageData, $pageParams); ?></div>
        <script src="<?= e(app_url('assets/js/schedule-table.js')) ?>" defer></script>
        <?php if (!$visibleSections): ?><p>No sections match these filters.</p><?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php page_end(); ?>
