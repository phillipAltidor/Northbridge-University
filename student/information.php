<?php

require_once __DIR__ . '/../includes/layout.php';

require_role(['Student']);

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

page_start('Student Information', 'Student', 'student/information.php');

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

    $information = [
        'User ID' => $student['user_ID'],
        'Name' => $name,
        'University Email' => $student['user_Email'],
        'Gender' => $student['gender'],
        'Date of Birth' => $student['DOB'],
        'Street Address' => $student['street'],
        'City' => $student['city'],
        'State' => $student['state'],
        'ZIP Code' => $student['zip_Code']
    ];
    ?>

    <div class="grid2">
        <?php foreach ($information as $label => $value): ?>
            <p>
                <strong><?= e($label) ?></strong><br>
                <?= e($value) ?>
            </p>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php page_end(); ?>
