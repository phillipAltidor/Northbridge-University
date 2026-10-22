<?php

function admin_nav_groups(): array
{
    return [
        'users' => ['Users', false, [
            ['Create User', 'admin/create-user.php'],
            ['Update User Information', 'admin/update-user.php'],
        ]],
        'departments' => ['Departments', false, [
            ['Create Department', null], ['Update Department', null],
            ['Assign Faculty to Departments', null],
        ]],
        'courses' => ['Courses & Scheduling', false, [
            ['Create Course', null], ['Add Course Prerequisites', null],
            ['Create Course Section', null], ['Update Course Section', null],
            ['Master Schedule', 'admin/master-schedule.php'],
            ['Add Faculty to Course Section', null],
            ['Update Faculty for Course Section', null],
        ]],
        'programs' => ['Majors & Minors', false, [
            ['Create Major', null], ['Update Major', null],
            ['Create Minor', null], ['Update Minor', null],
            ['Create Major Requirements', null], ['Update Major Requirements', null],
            ['Create Minor Requirements', null], ['Update Minor Requirements', null],
        ]],
        'students' => ['Student Records', false, [
            ['Add Student to Course Section', null],
            ['Drop Student from Course Section', null],
            ['Update Student Grade', null], ['Change Previously Recorded Grade', null],
            ['Declare Student Major', null], ['Add Student Minor', null],
            ['Assign Advisor to Student', null],
        ]],
        'facilities' => ['Buildings & Rooms', false, [
            ['Create Building', null], ['Update Building', null],
            ['Create Room in Building', null],
        ]],
    ];
}

function admin_navigation(string $active): void
{
    $current = $active === 'admin/edit-section.php' ? 'admin/master-schedule.php' : $active;
    ?>
    <nav class="admin-navigation" aria-label="Admin navigation"
         data-storage-key="<?= e(app_url('admin-navigation') . ':collapsed-v2:' . (int) ($_SESSION['user_id'] ?? 0)) ?>">
        <a href="<?= e(app_url('home.php')) ?>" <?= $current === 'home.php' ? 'class="active" aria-current="page"' : '' ?>>Home</a>
        <?php foreach (admin_nav_groups() as $key => [$label, $defaultOpen, $items]): ?>
            <details class="admin-nav-group" data-nav-group="<?= e($key) ?>" <?= $defaultOpen ? 'open' : '' ?>>
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
