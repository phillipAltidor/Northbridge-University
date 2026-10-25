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
                <?php foreach (['Personal Details' => $personal, 'Contact Details' => $contact] as $heading => $fields): ?>
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
