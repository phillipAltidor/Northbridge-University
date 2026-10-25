<?php

// Navigation follows the supplied Faculty required behavior list.
function faculty_nav_groups(): array
{
    return [
        'account' => ['My Account', [
            ['My Profile', 'faculty/information.php'], ['Update Password', null], ['Update Address', null],
        ]],
        'teaching' => ['Teaching', [
            ['Semester Schedule', 'faculty/schedule.php'], ['Master Schedule', null],
            ['Course Rosters', null], ['Mark Attendance', null], ['Assign Grades', null],
        ]],
        'advising' => ['Advising', [
            ['Advisee List', null], ['Student Information', null],
            ['Student Unofficial Transcript', null], ['Student Degree Audit', null],
        ]],
        'academics' => ['Academic Information', [
            ['Departments', null], ['Courses Offered by Departments', null],
            ['Majors Offered by Departments', null], ['Minors Offered by Departments', null],
            ['Course Prerequisites', null], ['Major Requirements', null],
            ['Course Requirements for a Major', null],
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
