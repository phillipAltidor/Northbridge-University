<?php

// Navigation follows the supplied Faculty required behavior list.
function faculty_nav_groups(): array
{
    return [
        'account' => ['My Account', [
            ['My Profile', 'faculty/information.php'], ['Update Password', 'faculty/password.php'], ['Update Address', 'faculty/address.php'],
        ]],
        'teaching' => ['Teaching', [
            ['Semester Schedule', 'faculty/schedule.php'], ['Master Schedule', 'faculty/master-schedule.php'],
            ['Course Rosters', 'faculty/rosters.php'], ['Mark Attendance', 'faculty/attendance.php'], ['Assign Grades', 'faculty/grades.php'],
        ]],
        'advising' => ['Advising', [
            ['Advisee List', 'faculty/advisees.php'], ['Student Information', 'faculty/students.php'],
            ['Student Unofficial Transcript', 'faculty/transcript.php'], ['Student Degree Audit', 'faculty/degree-audit.php'],
        ]],
        'academics' => ['Academic Information', [
            ['Departments', 'faculty/departments.php'], ['Courses Offered by Departments', 'faculty/courses.php'],
            ['Majors Offered by Departments', 'faculty/majors.php'], ['Minors Offered by Departments', 'faculty/minors.php'],
            ['Course Prerequisites', 'faculty/prerequisites.php'], ['Major Requirements', 'faculty/major-requirements.php'],
            ['Course Requirements for a Major', 'faculty/major-courses.php'],
        ]],
    ];
}

function faculty_navigation(string $active): void
{
    $current = parse_url($active, PHP_URL_PATH);
    ?>
    <nav class="admin-navigation faculty-navigation" aria-label="Faculty navigation"
         data-storage-key="<?= e(app_url('faculty-navigation') . ':collapsed-v2:' . (int) ($_SESSION['user_id'] ?? 0)) ?>">
        <a href="<?= e(app_url('home.php')) ?>" <?= $current === 'home.php' ? 'class="active" aria-current="page"' : '' ?>>Home</a>
        <?php foreach (faculty_nav_groups() as $key => [$label, $items]): ?>
            <details class="admin-nav-group" data-nav-group="<?= e($key) ?>">
                <summary><?= e($label) ?></summary>
                <div class="admin-nav-items">
                    <?php foreach ($items as [$itemLabel, $path]): ?>
                        <?php if ($path !== null): ?>
                            <a href="<?= e(app_url($path)) ?>" <?= $current === $path ? 'class="active" aria-current="page"' : '' ?>><?= e($itemLabel) ?></a>
                        <?php else: ?>
                            <span class="admin-nav-disabled" aria-disabled="true"><?= e($itemLabel) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </nav>
    <?php
}
