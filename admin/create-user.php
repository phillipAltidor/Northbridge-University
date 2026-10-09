<?php

require_once __DIR__ . '/../includes/layout.php';
require_role(['Admin']);
require_once __DIR__ . '/../config/database.php';

header('Cache-Control: no-store');

$stmt = $pdo->prepare('SELECT security_Level FROM Admin WHERE user_ID = ?');
$stmt->execute([(int) $_SESSION['user_id']]);
$admin = $stmt->fetch();

if (($admin['security_Level'] ?? '') !== 'Full-Access') {
    http_response_code(403);
    exit('403 - You do not have permission to create users.');
}

if (empty($_SESSION['create_user_token'])) {
    $_SESSION['create_user_token'] = bin2hex(random_bytes(32));
}

$roles = ['Student', 'Faculty', 'Admin', 'StatStaff'];
$genders = ['Male', 'Female', 'Other'];
$fields = [
    'first_name' => ['First Name', 50],
    'middle_name' => ['Middle Name', 50],
    'last_name' => ['Last Name', 50],
    'street' => ['Street Address', 100],
    'city' => ['City', 50],
    'state' => ['State', 50],
    'zip_code' => ['ZIP Code', 10]
];
$values = array_fill_keys(array_merge(array_keys($fields), ['gender', 'dob', 'user_type']), '');
$error = '';
$created = $_SESSION['created_user'] ?? null;
unset($_SESSION['created_user']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $field => $value) {
        $values[$field] = is_string($_POST[$field] ?? null)
            ? trim($_POST[$field])
            : '';
    }

    $token = $_POST['form_token'] ?? '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $values['dob']);

    if (!is_string($token) || !hash_equals($_SESSION['create_user_token'], $token)) {
        $error = 'Please reload the page and try again.';
    } else {
        foreach ($values as $field => $value) {
            if ($field !== 'middle_name' && $value === '') {
                $error = 'Please fill in all required fields.';
                break;
            }
        }

        if ($error === '') {
            foreach ($fields as $field => [$label, $limit]) {
                if (mb_strlen($values[$field], 'UTF-8') > $limit) {
                    $error = $label . ' must be ' . $limit . ' characters or fewer.';
                    break;
                }
            }
        }

        if ($error === '' && !in_array($values['gender'], $genders, true)) {
            $error = 'Please select a valid gender.';
        }

        if ($error === '' && !in_array($values['user_type'], $roles, true)) {
            $error = 'Please select a valid user type.';
        }

        if ($error === '' && (!$date || $date->format('Y-m-d') !== $values['dob'])) {
            $error = 'Please enter a valid date of birth.';
        }
    }

    if ($error === '') {
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE',
            $values['first_name'] . $values['last_name']);
        $emailName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name ?: ''));
        $emailName = $emailName !== '' ? $emailName : 'user';
        $password = bin2hex(random_bytes(12));
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Retry duplicate-key conflicts if another admin creates a user concurrently.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $pdo->beginTransaction();

                $userID = ((int) $pdo->query('SELECT MAX(user_ID) FROM User')->fetchColumn()) + 1;
                $email = $emailName . '@northbridge.edu';
                $stmt = $pdo->prepare('SELECT 1 FROM Login WHERE user_Email = ?');
                $stmt->execute([$email]);

                $suffix = $userID;
                while ($stmt->fetchColumn()) {
                    $email = $emailName . $suffix++ . '@northbridge.edu';
                    $stmt->execute([$email]);
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO User
                     (user_ID, first_Name, middle_Name, last_Name, gender, DOB,
                      street, city, state, zip_Code, user_Type)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $userID,
                    $values['first_name'],
                    $values['middle_name'] !== '' ? $values['middle_name'] : null,
                    $values['last_name'],
                    $values['gender'],
                    $values['dob'],
                    $values['street'],
                    $values['city'],
                    $values['state'],
                    $values['zip_code'],
                    $values['user_type']
                ]);

                $stmt = $pdo->prepare(
                    'INSERT INTO Login
                     (user_ID, user_Email, user_Password, no_Of_Tries, lock_var, user_Type)
                     VALUES (?, ?, ?, 0, 0, ?)'
                );
                $stmt->execute([$userID, $email, $hashedPassword, $values['user_type']]);

                $pdo->commit();

                $_SESSION['created_user'] = [
                    'user_id' => $userID,
                    'email' => $email,
                    'password' => $password
                ];
                $_SESSION['create_user_token'] = bin2hex(random_bytes(32));
                header('Location: create-user.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (($e->errorInfo[1] ?? null) !== 1062 || $attempt === 2) {
                    $error = 'Unable to create the user.';
                    break;
                }
            }
        }
    }
}

page_start('Create User', 'Admin', 'admin/create-user.php');

?>

<div class="user-form">
    <?php if ($error): ?>
        <div class="error-message" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($created): ?>
        <div class="success-message" role="status">
            <p>User created successfully.</p>
            <p><strong>User ID:</strong> <?= e($created['user_id']) ?></p>
            <p><strong>University Email:</strong> <?= e($created['email']) ?></p>
            <p><strong>Initial Password:</strong> <?= e($created['password']) ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="create-user.php">
        <input type="hidden" name="form_token" value="<?= e($_SESSION['create_user_token']) ?>">

        <?php foreach ($fields as $field => [$label, $limit]): ?>
            <?php if ($field === 'street'): ?>
                <div class="form-group">
                    <label for="gender">Gender (required)</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select</option>
                        <?php foreach ($genders as $gender): ?>
                            <option value="<?= e($gender) ?>" <?= $values['gender'] === $gender ? 'selected' : '' ?>>
                                <?= e($gender) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="dob">Date of Birth (required)</label>
                    <input type="date" id="dob" name="dob" value="<?= e($values['dob']) ?>" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="<?= e($field) ?>">
                    <?= e($label) ?> (<?= $field === 'middle_name' ? 'optional' : 'required' ?>)
                </label>
                <input
                    type="text"
                    id="<?= e($field) ?>"
                    name="<?= e($field) ?>"
                    maxlength="<?= e($limit) ?>"
                    value="<?= e($values[$field]) ?>"
                    <?= $field !== 'middle_name' ? 'required' : '' ?>
                >
            </div>
        <?php endforeach; ?>

        <div class="form-group">
            <label for="user_type">User Type (required)</label>
            <select id="user_type" name="user_type" required>
                <option value="">Select User Type</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role) ?>" <?= $values['user_type'] === $role ? 'selected' : '' ?>>
                        <?= e($role) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="login-button">Create User</button>
    </form>
</div>

<?php page_end(); ?>
