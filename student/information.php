<?php

require_once __DIR__ . '/../includes/layout.php';

require_role(['Student']);
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$student = one(
    $pdo,
    "SELECT User.user_ID, User.first_Name, User.middle_Name, User.last_Name,
            User.gender, User.DOB, User.street, User.city, User.state,
            User.zip_Code, Login.user_Email
     FROM User
     JOIN Login ON Login.user_ID = User.user_ID
     WHERE User.user_ID = ? AND User.user_Type = 'Student'",
    [(int) $_SESSION['user_id']]
);

if (!$student) {
    http_response_code(404);
}

$academicRecord = $student ? one(
    $pdo,
    "SELECT s.student_Type, s.student_Year, m.major_Name, d.dept_Name,
            CASE s.student_Type
                WHEN 'Undergraduate' THEN u.undergraduate_student_Type
                WHEN 'Graduate' THEN g.graduate_Student_Type
            END AS enrollment_Type
     FROM Student s
     LEFT JOIN Undergraduate u ON u.student_ID=s.student_ID AND s.student_Type='Undergraduate'
     LEFT JOIN Graduate_Student g ON g.student_ID=s.student_ID AND s.student_Type='Graduate'
     LEFT JOIN Major m ON m.major_ID=s.major_ID
     LEFT JOIN Department d ON d.dept_ID=CASE s.student_Type
         WHEN 'Undergraduate' THEN u.dept_ID
         WHEN 'Graduate' THEN g.dept_ID
     END
     WHERE s.student_ID=?",
    [(int) $_SESSION['user_id']]
) : null;

page_start('My Profile', 'Student', 'student/information.php', null, false);

?>

<?php if (!$student): ?>
    <p class="error-message" role="alert">Student information could not be found.</p>
<?php else: ?>
    <?php
    $name = implode(' ', array_filter([
        $student['first_Name'],
        $student['middle_Name'],
        $student['last_Name']
    ], static fn($part) => $part !== null && $part !== ''));

    $level = $academicRecord['student_Type'] ?? null;
    $year = (int) ($academicRecord['student_Year'] ?? 0);
    $standing = $year > 0 ? 'Year ' . $year : null;
    if ($level === 'Undergraduate') {
        $standing = [1 => 'Freshman', 2 => 'Sophomore', 3 => 'Junior', 4 => 'Senior'][$year] ?? $standing;
    }
    $academic = [
        'Student Level' => $level,
        $level === 'Graduate' ? 'Graduate Year' : 'Class Standing' => $standing,
        'Enrollment Type' => $academicRecord['enrollment_Type'] ?? null,
        'Major' => $academicRecord['major_Name'] ?? null,
        'Department' => $academicRecord['dept_Name'] ?? null,
    ];
    $personal = [
        'First Name' => $student['first_Name'],
        'Middle Name' => $student['middle_Name'],
        'Last Name' => $student['last_Name'],
        'Gender' => $student['gender'],
        'Date of Birth' => $student['DOB'],
    ];
    $contact = [
        'University Email' => $student['user_Email'],
        'Street Address' => $student['street'],
        'City' => $student['city'],
        'State' => $student['state'],
        'ZIP Code' => $student['zip_Code']
    ];
    ?>

    <div class="profile-page">
        <header class="profile-heading">
            <p>My Profile</p>
            <h1><?= e($name) ?></h1>
        </header>
        <div class="profile-layout">
            <div class="profile-details">
                <?php foreach (['Academic Details' => $academic, 'Personal Details' => $personal, 'Contact Details' => $contact] as $heading => $fields): ?>
                    <section class="profile-section" aria-label="<?= e($heading) ?>">
                        <h2><?= e($heading) ?></h2>
                        <dl class="profile-fields">
                            <?php foreach ($fields as $label => $value): ?>
                                <div>
                                    <dt><?= e($label) ?></dt>
                                    <dd><?= e($value === null || $value === '' ? 'Not provided' : $value) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endforeach; ?>
            </div>
            <aside class="profile-account" aria-labelledby="profile-account-title">
                <h2 id="profile-account-title">Account Details</h2>
                <dl class="profile-fields">
                    <div><dt>User ID</dt><dd><?= e($student['user_ID']) ?></dd></div>
                    <div><dt>Role</dt><dd>Student</dd></div>
                    <div><dt>University</dt><dd>Northbridge University</dd></div>
                </dl>
            </aside>
        </div>
    </div>

<?php endif; ?>

<?php page_end(); ?>
