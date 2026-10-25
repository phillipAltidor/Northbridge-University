<?php

require_once __DIR__ . '/../includes/layout.php';
require_role(['Faculty']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/faculty-schedule.php';
header('Cache-Control: no-store');
$term = $_GET['term'] ?? 'current';
if (!is_string($term) || !in_array($term, ['current', 'next'], true)) {
    http_response_code(400);
    exit('Invalid semester selection.');
}
$timeline = require __DIR__ . '/../config/academic-timeline.php';
$semester = $timeline[$term === 'current' ? 'current_semester' : 'next_semester'];
$meetings = meeting_schedule_group_days(faculty_schedule_meetings($pdo, (int) $_SESSION['user_id'], $semester));
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
            <table>
                <thead><tr><th scope="col">Course</th><th scope="col">CRN</th><th scope="col">Section</th><th scope="col">Credits</th><th scope="col">Days</th><th scope="col">Time</th><th scope="col">Building / Room</th><th scope="col">Available Seats</th></tr></thead>
                <tbody>
                    <?php foreach ($meetings as $meeting): ?>
                        <tr>
                            <td><strong><?= e($meeting['course_ID']) ?></strong><br><?= e($meeting['course_Name']) ?></td>
                            <td><?= e($meeting['CRN']) ?></td><td><?= e($meeting['section_No']) ?></td><td><?= e($meeting['course_Credits']) ?></td>
                            <td><?= e($meeting['meeting_Days']) ?></td>
                            <td><?= e(meeting_schedule_time($meeting['start_Time']) . ' - ' . meeting_schedule_time($meeting['end_Time'])) ?></td>
                            <td><?= e($meeting['Bldg_Name'] ?? 'Building unassigned') ?><?= $meeting['room_Number'] !== null ? ' / ' . e($meeting['room_Number']) : '' ?></td>
                            <td><?= e($meeting['available_Seats']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<script src="<?= e(app_url('assets/js/student-schedule.js')) ?>" defer></script>
<?php page_end(); ?>
