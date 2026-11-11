<?php

require_once __DIR__ . '/../includes/layout.php';
require_role(['Faculty']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/faculty-schedule.php';
require_once __DIR__ . '/../includes/schedule-columns.php';
header('Cache-Control: no-store');
$term = $_GET['term'] ?? 'current';
if (!is_string($term) || !in_array($term, ['current', 'next'], true)) {
    http_response_code(400);
    exit('Invalid semester selection.');
}
$timeline = require __DIR__ . '/../config/academic-timeline.php';
$semester = $timeline[$term === 'current' ? 'current_semester' : 'next_semester'];
$meetings = meeting_schedule_group_sections(faculty_schedule_meetings($pdo, (int) $_SESSION['user_id'], $semester));
page_start('Semester Schedule', 'Faculty', 'faculty/schedule.php');
?>
<div class="student-schedule faculty-schedule">
    <form class="student-schedule-filter" method="get">
        <label for="term">Semester</label>
        <select id="term" name="term">
            <option value="current"<?= $term === 'current' ? ' selected' : '' ?>><?= e($timeline['current_semester']) ?> — Current</option>
            <option value="next"<?= $term === 'next' ? ' selected' : '' ?>><?= e($timeline['next_semester']) ?> — Next</option>
        </select>
        <noscript><button class="btn" type="submit">View Schedule</button></noscript>
    </form>
    <h2><?= e($semester) ?></h2>
    <?php if (!$meetings): ?>
        <p class="muted">No assigned classes for <?= e($semester) ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="schedule-columns-table">
                <?php schedule_columns_header('Roster'); ?>
                <tbody>
                    <?php foreach ($meetings as $meeting): ?>
                        <?php schedule_columns_row($meeting, $meeting['meeting_Groups'], ['url' => app_url('faculty/rosters.php') . '?' . http_build_query(['semester_id' => $meeting['semester_ID'], 'crn' => $meeting['CRN']]), 'label' => 'View Roster']); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script src="<?= e(app_url('assets/js/student-schedule.js')) ?>" defer></script>
<?php page_end(); ?>
