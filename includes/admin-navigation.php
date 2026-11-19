<?php

function admin_nav_groups(): array
{
    return [
        'users' => ['Users', false, [
            ['Create User', 'admin/create-user.php'],
            ['Update User Information', 'admin/update-user.php'],
        ]],
        'departments' => ['Departments', false, [
            ['Create Department', 'admin/create-department.php'],
            ['Update Department', 'admin/update-department.php'],
            ['Assign Faculty to Departments', 'admin/assign-faculty-department.php'],
        ]],
        'courses' => ['Courses & Scheduling', false, [
            ['Create Course', 'admin/create-course.php'], ['Update Course', 'admin/update-course.php'], ['Add Course Prerequisites', 'admin/add-prerequisites.php'],
            ['Create Course Section', 'admin/create-section.php'], ['Update Course Section', 'admin/update-section.php'],
            ['Master Schedule', 'admin/master-schedule.php'],
            ['Add Faculty to Course Section', 'admin/add-faculty-section.php'],
            ['Update Faculty for Course Section', 'admin/update-faculty-section.php'],
        ]],
        'programs' => ['Majors & Minors', false, [
            ['Degree Evaluation Preview', 'admin/degree-preview.php'],
            ['Create Major', 'admin/create-major.php'], ['Update Major', 'admin/update-major.php'],
            ['Create Minor', 'admin/create-minor.php'], ['Update Minor', 'admin/update-minor.php'],
            ['Create Major Requirements', 'admin/create-major-requirements.php'], ['Update Major Requirements', 'admin/update-major-requirements.php'],
            ['Create Minor Requirements', 'admin/create-minor-requirements.php'], ['Update Minor Requirements', 'admin/update-minor-requirements.php'],
        ]],
        'students' => ['Student Records', false, [
            ['Add Student to Course Section', 'admin/add-student-section.php'],
            ['Drop Student from Course Section', 'admin/drop-student-section.php'],
            ['Update Student Grade', 'admin/update-student-grade.php'], ['Change Previously Recorded Grade', 'admin/change-grade.php'],
            ['Declare Student Major', 'admin/declare-major.php'], ['Add Student Minor', 'admin/add-minor.php'],
            ['Assign Advisor to Student', 'admin/assign-advisor.php'],
        ]],
        'facilities' => ['Buildings & Rooms', false, [
            ['Create Building', 'admin/create-building.php'], ['Update Building', 'admin/update-building.php'],
            ['Create Room in Building', 'admin/create-room.php'],
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
