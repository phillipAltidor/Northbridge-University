<?php

require_once '../config/database.php';
require_once '../includes/layout.php';
require_once '../includes/functions.php';

require_role(['Admin']);

// Count the total number of users
$userCount = one(
    $pdo,
    "SELECT COUNT(*) AS total FROM User"
);

// Count the total number of courses
$courseCount = one(
    $pdo,
    "SELECT COUNT(*) AS total FROM Course"
);

// Count the total number of course sections
$sectionCount = one(
    $pdo,
    "SELECT COUNT(*) AS total FROM Course_Section"
);

// Count the total number of departments
$departmentCount = one(
    $pdo,
    "SELECT COUNT(*) AS total FROM Department"
);

page_start(
    'Admin Dashboard',
    'Admin',
    'dashboard.php'
);

?>

<p class="muted">
    Manage university users and academic information.
</p>

<div class="cards">

    <div class="card stat">
        <strong>
            <?= e($userCount['total']) ?>
        </strong>
        <span>Users</span>
    </div>

    <div class="card stat">
        <strong>
            <?= e($courseCount['total']) ?>
        </strong>
        <span>Courses</span>
    </div>

    <div class="card stat">
        <strong>
            <?= e($sectionCount['total']) ?>
        </strong>
        <span>Course Sections</span>
    </div>

    <div class="card stat">
        <strong>
            <?= e($departmentCount['total']) ?>
        </strong>
        <span>Departments</span>
    </div>

</div>

<div class="card">

    <h2>Administration</h2>

    <p>
        Use the menu to view university users,
        academic information, and course sections.
    </p>

</div>

<?php

page_end();

?>