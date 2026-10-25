<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['Admin']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/admin.php';
require_admin($pdo);
$pdo->exec("SET time_zone = '+00:00'");
header('Cache-Control: no-store');

$fields = [
    'first_Name' => ['First Name', 50],
    'middle_Name' => ['Middle Name', 50],
    'last_Name' => ['Last Name', 50],
    'gender' => ['Gender', 20],
    'DOB' => ['Date of Birth', null],
    'street' => ['Street Address', 100],
    'city' => ['City', 50],
    'state' => ['State', 50],
    'zip_Code' => ['ZIP Code', 10],
    'user_Email' => ['University Email', 255]
];
function editable_user(PDO $pdo, int $id, bool $lock = false): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.user_ID, u.user_Type, u.first_Name, u.middle_Name, u.last_Name,
                u.gender, u.DOB, u.street, u.city, u.state, u.zip_Code, l.user_Email
         FROM User u JOIN Login l ON l.user_ID = u.user_ID
         WHERE u.user_ID = ?' . ($lock ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}
function record_version(array $record): string
{
    return hash_hmac('sha256', json_encode($record, JSON_THROW_ON_ERROR), $_SESSION['update_user_token']);
}
function text_input(array $input, string $key): string
{
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}
$_SESSION['update_user_token'] ??= bin2hex(random_bytes(32));
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$input = $isPost ? $_POST : $_GET;
$id = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$user = $id ? editable_user($pdo, $id) : null;
$error = '';
$notice = $_SESSION['update_user_notice'] ?? '';
unset($_SESSION['update_user_notice']);
$note = text_input($_POST, 'request_note');
$version = $user ? record_version($user) : '';
$values = $user ?? [];
if ($id && !$user) {
    http_response_code(404);
    $error = 'User record could not be found.';
}
if ($isPost) {
    $version = text_input($_POST, 'record_version');
    foreach ($fields as $field => [$label, $limit]) {
        $values[$field] = text_input($_POST, $field);
        if ($field === 'middle_Name' && $values[$field] === '') {
            $values[$field] = null;
        } elseif ($values[$field] === '' && $error === '') {
            $error = 'Please fill in all required fields.';
        }
        if ($limit !== null && mb_strlen((string) $values[$field], 'UTF-8') > $limit) {
            $error = $label . ' must be ' . $limit . ' characters or fewer.';
        }
    }
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) $values['DOB'])
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $values['DOB']) : false;
    if ($error === '' && (!$date || $date->format('Y-m-d') !== $values['DOB'])) {
        $error = 'Please enter a valid date of birth.';
    }
    if ($error === '' && !filter_var($values['user_Email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid university email.';
    }
    if ($error === '' && ($note === '' || mb_strlen($note, 'UTF-8') > 1000)) {
        $error = 'Describe the user’s requested change in 1–1000 characters.';
    }
    if (!hash_equals($_SESSION['update_user_token'], text_input($_POST, 'form_token'))) {
        $error = 'Please reload the page and try again.';
    }
    if (!$user && $error === '') {
        $error = 'Select a valid user record.';
    }
    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $before = editable_user($pdo, $id, true);
            if (!$before || !hash_equals(record_version($before), $version)) {
                $pdo->rollBack();
                $error = 'This record changed after you opened it. Reload the user record before saving.';
            } else {
                $after = $before;
                foreach (array_keys($fields) as $field) {
                    $after[$field] = $values[$field];
                }
                if ($after === $before) {
                    $pdo->rollBack();
                    $notice = 'No information changed.';
                } else {
                    $userFields = array_filter(array_keys($fields), static fn($field) => $field !== 'user_Email');
                    $stmt = $pdo->prepare('UPDATE User SET ' . implode(', ', array_map(static fn($field) => "`$field` = ?", $userFields)) . ' WHERE user_ID = ?');
                    $params = array_map(static fn($field) => $after[$field], $userFields);
                    $params[] = $id;
                    $stmt->execute($params);
                    $stmt = $pdo->prepare('UPDATE Login SET user_Email = ? WHERE user_ID = ?');
                    $stmt->execute([$after['user_Email'], $id]);
                    $stmt = $pdo->prepare(
                        'INSERT INTO User_Update_Log (user_ID, admin_ID, request_Note, before_Data, after_Data)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$id, (int) $_SESSION['user_id'], $note,
                        json_encode($before, JSON_THROW_ON_ERROR), json_encode($after, JSON_THROW_ON_ERROR)]);
                    $pdo->commit();
                    if ($id === (int) $_SESSION['user_id']) {
                        $_SESSION['name'] = $after['first_Name'] . ' ' . $after['last_Name'];
                        $_SESSION['email'] = $after['user_Email'];
                    }
                    $_SESSION['update_user_notice'] = 'User information updated successfully.';
                    header('Location: update-user.php?user_id=' . $id);
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = ($e->errorInfo[1] ?? null) === 1062
                ? 'That email is already assigned to another user.'
                : 'Unable to save the update and its history. Please try again.';
        }
    }
}
$search = text_input($_GET, 'q');
$results = [];
if ($search !== '') {
    $nameTerms = preg_split('/\s+/u', str_replace('.', '', $search), -1, PREG_SPLIT_NO_EMPTY);
    $nameConditions = array_fill(0, count($nameTerms),
        "LOCATE(?, REPLACE(CONCAT_WS(' ', u.first_Name, u.middle_Name, u.last_Name), '.', '')) > 0");
    $stmt = $pdo->prepare(
        "SELECT u.user_ID, u.first_Name, u.middle_Name, u.last_Name, u.user_Type, l.user_Email
         FROM User u JOIN Login l ON l.user_ID = u.user_ID
         WHERE CAST(u.user_ID AS CHAR) = ?
            OR LOCATE(?, l.user_Email) > 0
            OR (" . ($nameConditions ? implode(' AND ', $nameConditions) : 'FALSE') . ")
         ORDER BY u.last_Name, u.first_Name, u.user_ID LIMIT 51"
    );
    $stmt->execute([$search, $search, ...$nameTerms]);
    $results = $stmt->fetchAll();
}
$history = [];
if ($user) {
    $stmt = $pdo->prepare('SELECT log_ID, admin_ID, updated_At, request_Note, before_Data, after_Data FROM User_Update_Log WHERE user_ID = ? ORDER BY log_ID DESC LIMIT 20');
    $stmt->execute([$id]);
    $history = $stmt->fetchAll();
}
page_start('Update User Information', 'Admin', 'admin/update-user.php');
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="alert success" role="status"><?= e($notice) ?></div><?php endif; ?>
<form method="get" action="update-user.php" class="user-form">
    <div class="form-group">
        <label for="q">Find a user by ID, name, or email</label>
        <input id="q" name="q" type="search" value="<?= e($search) ?>" required>
    </div>
    <button class="btn" type="submit">Search</button>
</form>
<?php if ($search !== ''): ?>
    <?php if (!$results): ?><p>No matching users found.</p><?php else: ?>
        <?php if (count($results) > 50): ?><p>Showing the first 50 matches. Refine your search to find a specific user.</p><?php endif; ?>
        <div class="table-scroll"><table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>User Type</th><th>Action</th></tr></thead>
            <tbody><?php foreach (array_slice($results, 0, 50) as $result): ?>
                <?php $resultName = implode(' ', array_filter([
                    $result['first_Name'], $result['middle_Name'], $result['last_Name']
                ], static fn($part) => $part !== null && $part !== '')); ?>
                <tr><td><?= e($result['user_ID']) ?></td><td><?= e($resultName) ?></td><td><?= e($result['user_Email']) ?></td><td><?= e($result['user_Type']) ?></td><td><a class="btn small" href="update-user.php?user_id=<?= e($result['user_ID']) ?>">Select</a></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
<?php endif; ?>
<?php if ($user): ?>
    <h2>User <?= e($user['user_ID']) ?> · <?= e($user['user_Type']) ?></h2>
    <form method="post" action="update-user.php" class="user-form">
        <input type="hidden" name="user_id" value="<?= e($id) ?>">
        <input type="hidden" name="form_token" value="<?= e($_SESSION['update_user_token']) ?>">
        <input type="hidden" name="record_version" value="<?= e($version) ?>">
        <?php foreach ($fields as $field => [$label, $limit]): ?>
            <div class="form-group">
                <label for="<?= e($field) ?>"><?= e($label) ?> (<?= $field === 'middle_Name' ? 'optional' : 'required' ?>)</label>
                <input id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === 'DOB' ? 'date' : ($field === 'user_Email' ? 'email' : 'text') ?>" value="<?= e($values[$field] ?? '') ?>" <?= $limit !== null ? 'maxlength="' . e($limit) . '"' : '' ?> <?= $field !== 'middle_Name' ? 'required' : '' ?>>
            </div>
        <?php endforeach; ?>
        <div class="form-group">
            <label for="request_note">User’s requested change (required)</label>
            <textarea id="request_note" name="request_note" rows="3" maxlength="1000" required><?= e($note) ?></textarea>
        </div>
        <button type="submit" class="btn">Save Update</button>
    </form>
    <h2 id="update-history">Update History</h2>
    <?php if (!$history): ?><p>No updates recorded.</p><?php else: ?>
        <p>Showing the latest <?= count($history) ?> updates. Times are in UTC.</p>
        <?php foreach ($history as $entry): ?>
            <?php $before = json_decode($entry['before_Data'], true); $after = json_decode($entry['after_Data'], true); ?>
            <details class="update-history">
                <summary><?= e($entry['updated_At']) ?> UTC · Admin ID <?= e($entry['admin_ID']) ?></summary>
                <p><?= e($entry['request_Note']) ?></p>
                <div class="table-scroll"><table><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>
                    <?php foreach ($fields as $field => [$label]): ?>
                        <?php if (($before[$field] ?? null) !== ($after[$field] ?? null)): ?>
                            <tr><td><?= e($label) ?></td><td><?= e($before[$field] ?? '') ?></td><td><?= e($after[$field] ?? '') ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody></table></div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
<?php page_end(); ?>
