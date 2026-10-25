<?php

require_once __DIR__ . '/../includes/layout.php';
require_role(['Faculty']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Cache-Control: no-store');

$account = one(
    $pdo,
    'SELECT u.user_ID, u.first_Name, u.middle_Name, u.last_Name,
            u.gender, u.DOB, u.street, u.city, u.state, u.zip_Code, l.user_Email
     FROM User u JOIN Login l ON l.user_ID=u.user_ID
     WHERE u.user_ID=? AND u.user_Type=?',
    [(int) $_SESSION['user_id'], 'Faculty']
);
if (!$account) {
    http_response_code(404);
    exit('Personal information could not be found.');
}
$name = implode(' ', array_filter([
    $account['first_Name'], $account['middle_Name'], $account['last_Name']
], static fn($part) => $part !== null && $part !== ''));
$personal = [
    'First Name' => $account['first_Name'], 'Middle Name' => $account['middle_Name'],
    'Last Name' => $account['last_Name'], 'Gender' => $account['gender'], 'Date of Birth' => $account['DOB'],
];
$contact = [
    'University Email' => $account['user_Email'], 'Street Address' => $account['street'],
    'City' => $account['city'], 'State' => $account['state'], 'ZIP Code' => $account['zip_Code'],
];
page_start('My Profile', 'Faculty', 'faculty/information.php', null, false);
?>
<div class="profile-page">
    <header class="profile-heading"><p>My Profile</p><h1><?= e($name) ?></h1></header>
    <div class="profile-layout">
        <div class="profile-details">
            <?php foreach (['Personal Details' => $personal, 'Contact Details' => $contact] as $heading => $fields): ?>
                <section class="profile-section" aria-label="<?= e($heading) ?>">
                    <h2><?= e($heading) ?></h2>
                    <dl class="profile-fields">
                        <?php foreach ($fields as $label => $value): ?>
                            <div><dt><?= e($label) ?></dt><dd><?= e($value === null || $value === '' ? 'Not provided' : $value) ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            <?php endforeach; ?>
        </div>
        <aside class="profile-account" aria-labelledby="profile-account-title">
            <h2 id="profile-account-title">Account Details</h2>
            <dl class="profile-fields">
                <div><dt>User ID</dt><dd><?= e($account['user_ID']) ?></dd></div>
                <div><dt>Role</dt><dd>Faculty</dd></div>
                <div><dt>University</dt><dd>Northbridge University</dd></div>
            </dl>
        </aside>
    </div>
</div>
<?php page_end(); ?>
