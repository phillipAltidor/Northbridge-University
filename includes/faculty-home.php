<?php

require_once __DIR__ . '/faculty-schedule.php';
$timeline = require __DIR__ . '/../config/academic-timeline.php';
$today = academic_today();
$meetings = faculty_schedule_meetings($pdo, (int) $_SESSION['user_id'], $timeline['current_semester']);
$week = meeting_schedule_week($meetings, $today);
$actions = [
    ['Semester Schedule', 'faculty/schedule.php', 'quick-master-schedule.png'],
    ['Course Rosters', null, 'quick-faculty-rosters.png'],
    ['Advisee List', null, 'quick-student-information.png'],
];
?>
<div class="admin-home faculty-home">
    <section class="admin-home-welcome" aria-labelledby="faculty-welcome-title">
        <img class="admin-home-campus" src="<?= e(app_url('assets/images/northbridge-campus.png')) ?>" alt="" width="1942" height="809" fetchpriority="high">
        <div class="admin-home-greeting">
            <time datetime="<?= e($today->format('Y-m-d')) ?>"><?= e($today->format('l, F j, Y')) ?></time>
            <h1 id="faculty-welcome-title">Welcome back, <?= e($user['first_Name']) ?>!</h1>
        </div>
    </section>
    <section class="admin-home-actions" aria-labelledby="faculty-actions-title">
        <h2 id="faculty-actions-title">Quick Access</h2>
        <div class="admin-home-quick-links">
            <?php foreach ($actions as [$label, $path, $icon]): ?>
                <?php if ($path !== null): ?>
                    <a class="admin-home-quick-tile" href="<?= e(app_url($path)) ?>">
                        <img src="<?= e(app_url('assets/images/' . $icon)) ?>" alt="" width="112" height="112">
                        <span><?= e($label) ?></span>
                    </a>
                <?php else: ?>
                    <div class="admin-home-quick-tile student-quick-disabled" aria-disabled="true">
                        <img src="<?= e(app_url('assets/images/' . $icon)) ?>" alt="" width="112" height="112">
                        <span><?= e($label) ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="student-week" aria-labelledby="faculty-week-title">
        <div class="student-week-heading">
            <div>
                <h2 id="faculty-week-title">Week at a Glance</h2>
                <p><?= e($week[0]['date']->format('M j') . ' - ' . $week[6]['date']->format('M j, Y')) ?> · <?= e($timeline['current_semester']) ?> · Scheduled Meetings</p>
            </div>
            <a href="<?= e(app_url('faculty/schedule.php')) ?>">View Full Schedule</a>
        </div>
        <?php if (!$meetings): ?>
            <p class="muted">No assigned classes to display for <?= e($timeline['current_semester']) ?>.</p>
        <?php else: ?>
            <div class="student-week-days">
                <?php foreach ($week as $day): ?>
                    <section class="student-week-day<?= $day['today'] ? ' is-today' : '' ?>" aria-label="<?= e($day['date']->format('l, F j')) ?>">
                        <h3><time datetime="<?= e($day['date']->format('Y-m-d')) ?>"<?= $day['today'] ? ' aria-current="date"' : '' ?>><?= e($day['date']->format('D')) ?><span><?= e($day['date']->format('M j')) ?></span></time></h3>
                        <?php if (!$day['meetings']): ?><p class="student-week-empty">No classes</p><?php endif; ?>
                        <?php foreach ($day['meetings'] as $meeting): ?>
                            <div class="student-week-meeting">
                                <strong><?= e($meeting['course_ID']) ?> · <?= e($meeting['section_No']) ?></strong>
                                <p><?= e($meeting['course_Name']) ?></p>
                                <p><?= e(meeting_schedule_time($meeting['start_Time']) . ' - ' . meeting_schedule_time($meeting['end_Time'])) ?></p>
                                <p class="muted"><?= e($meeting['Bldg_Name'] ?? 'Building unassigned') ?><?= $meeting['room_Number'] !== null ? ' · Room ' . e($meeting['room_Number']) : '' ?></p>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
