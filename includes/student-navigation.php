<?php

// Student use cases 5-27; unsuccessful outcomes belong within each workflow.
function student_nav_groups(): array
{
    return [
        'records' => ['My Records', [
            ['Student Information', 'student/information.php'],
            ['Holds', null], ['Unofficial Transcript', null],
            ['Degree Audit', null], ['Advisors', null],
        ]],
        'schedules' => ['Schedules & Registration', [
            ['Semester Schedule', 'student/schedule.php'],
            ['Register for a Course Section', null], ['Drop a Course Section', null],
        ]],
        'programs' => ['Majors & Minors', [
            ['Majors', null], ['Major Requirements', null], ['Add a Major', null],
            ['Minors', null], ['Minor Requirements', null], ['Add a Minor', null],
        ]],
        'university' => ['University Information', [
            ['Departments', null], ['Courses in a Department', null],
            ['Course Prerequisites', null], ['Semesters', null], ['Buildings', null],
        ]],
    ];
}

function student_navigation(string $active): void
{
    $current = parse_url($active, PHP_URL_PATH);
    ?>
    <nav class="admin-navigation student-navigation" aria-label="Student navigation"
         data-storage-key="<?= e(app_url('student-navigation') . ':collapsed-v2:' . (int) ($_SESSION['user_id'] ?? 0)) ?>">
        <a href="<?= e(app_url('home.php')) ?>" <?= $current === 'home.php' ? 'class="active" aria-current="page"' : '' ?>>Home</a>
        <?php foreach (student_nav_groups() as $key => [$label, $items]): ?>
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
