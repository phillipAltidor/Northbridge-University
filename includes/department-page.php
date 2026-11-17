<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';
require_role(['Admin']);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/admin.php';
require_admin($pdo);
require_once __DIR__ . '/department-management.php';

header('Cache-Control: no-store');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit('Method not allowed.');
}
$mode = $departmentMode ?? '';
if (!in_array($mode, ['create', 'update', 'assign'], true)) {
    http_response_code(404);
    exit;
}
$paths = ['create' => 'admin/create-department.php', 'update' => 'admin/update-department.php', 'assign' => 'admin/assign-faculty-department.php'];
$titles = ['create' => 'Create Department', 'update' => 'Update Department', 'assign' => 'Assign Faculty to Departments'];
$canWrite = department_can_write((int) $_SESSION['user_id']);
$_SESSION['department_form_token'] ??= bin2hex(random_bytes(32));
$error = '';
$message = $_SESSION['department_success'] ?? '';
unset($_SESSION['department_success']);
$values = [];
$version = '';
$departmentID = null;

try {
    if ($mode === 'update' && isset($_GET['dept_ID']) && $_GET['dept_ID'] !== '') {
        $departmentID = department_positive_id($_GET['dept_ID'], 'department');
        $stmt = $pdo->prepare('SELECT * FROM Department WHERE dept_ID = ?');
        $stmt->execute([$departmentID]);
        $values = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$values) {
            $values = [];
            throw new DomainException('The selected department does not exist.');
        }
        $version = department_version($values);
    }
} catch (DomainException $exception) {
    http_response_code(400);
    $error = $exception->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Keep submitted values after a validation error; never accept a posted ID
    // for an update target. Its selector remains in the form action URL.
    $values = [];
    foreach (['dept_Name', 'office_ID', 'Email', 'Phone_No', 'chair_ID', 'dept_Manager', 'faculty_ID', 'dept_ID', 'percent_Time', 'date_Of_Appointment'] as $field) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $version = is_string($_POST['version'] ?? null) ? $_POST['version'] : '';
    try {
        if (!$canWrite) {
            http_response_code(403);
            throw new DomainException('Your account has view-only access to department management.');
        }
        if (!is_string($_POST['form_token'] ?? null)
            || !hash_equals($_SESSION['department_form_token'], $_POST['form_token'])) {
            http_response_code(403);
            throw new DomainException('Your form expired. Reload the page and try again.');
        }
        if ($error !== '') {
            throw new DomainException($error);
        }
        if ($mode === 'assign') {
            department_assign_faculty($pdo, $_POST);
            $_SESSION['department_success'] = 'Faculty member successfully assigned to the department.';
            $destination = $paths[$mode];
        } else {
            if ($mode === 'update' && $departmentID === null) {
                throw new DomainException('Select a department before saving.');
            }
            $savedID = department_save($pdo, $_POST, $mode === 'update' ? $departmentID : null, $version);
            $_SESSION['department_success'] = $mode === 'create'
                ? 'Department successfully created. Department ID: ' . $savedID . '.'
                : 'Department successfully updated.';
            $destination = 'admin/update-department.php?dept_ID=' . $savedID;
        }
        $_SESSION['department_form_token'] = bin2hex(random_bytes(32));
        header('Location: ' . app_url($destination), true, 303);
        exit;
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        http_response_code(409);
        $error = 'Unable to save because of a database conflict. Reload the page and try again.';
    }
}

$departments = $pdo->query('SELECT dept_ID, dept_Name FROM Department ORDER BY dept_Name, dept_ID')->fetchAll();
$faculty = $pdo->query("SELECT f.faculty_ID, f.faculty_Type, u.first_Name, u.last_Name FROM Faculty f JOIN User u ON u.user_ID = f.faculty_ID WHERE u.user_Type = 'Faculty' ORDER BY u.last_Name, u.first_Name, f.faculty_ID")->fetchAll();
$offices = $mode !== 'assign' ? $pdo->query("SELECT o.office_ID, r.room_Number, b.Bldg_Name FROM Office o JOIN Room r ON r.room_ID = o.office_ID JOIN Building b ON b.Bldg_ID = r.Bldg_ID WHERE r.room_Type = 'Office' ORDER BY b.Bldg_Name, r.room_Number, o.office_ID")->fetchAll() : [];
$assignments = $mode === 'assign' ? $pdo->query('SELECT fd.faculty_ID, fd.dept_ID, fd.percent_Time, fd.date_Of_Appointment, d.dept_Name FROM Faculty_Department fd JOIN Department d ON d.dept_ID = fd.dept_ID ORDER BY fd.faculty_ID, d.dept_Name')->fetchAll() : [];
$selectedFaculty = $values['faculty_ID'] ?? '';
if ($mode === 'assign' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $selectedFaculty = is_string($_GET['faculty_ID'] ?? null) ? $_GET['faculty_ID'] : '';
    $values['faculty_ID'] = $selectedFaculty;
}
$action = app_url($paths[$mode]) . ($mode === 'update' && $departmentID !== null ? '?dept_ID=' . $departmentID : '');
page_start($titles[$mode], 'Admin', $paths[$mode]);
?>
<div class="user-form">
    <?php if ($message !== ''): ?><div class="success-message" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="error-message" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$canWrite): ?><p role="status">Your account has view-only access to department management.</p><?php endif; ?>

    <?php if ($mode === 'update'): ?>
    <form method="get" action="<?= e(app_url($paths[$mode])) ?>">
        <div class="form-group"><label for="department-selector">Select Department</label>
            <select id="department-selector" name="dept_ID" required>
                <option value="">Select a department</option>
                <?php foreach ($departments as $department): ?>
                <option value="<?= e($department['dept_ID']) ?>" <?= (int) $department['dept_ID'] === $departmentID ? 'selected' : '' ?>><?= e($department['dept_Name'] . ' (ID ' . $department['dept_ID'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div><button class="btn" type="submit">Load Department</button>
    </form>
    <?php endif; ?>

    <?php if ($mode !== 'update' || ($departmentID !== null && $values !== [])): ?>
    <form method="post" action="<?= e($action) ?>">
        <input type="hidden" name="form_token" value="<?= e($_SESSION['department_form_token']) ?>">
        <input type="hidden" name="version" value="<?= e($version) ?>">
        <fieldset <?= !$canWrite ? 'disabled' : '' ?>>
        <legend><?= e($titles[$mode]) ?></legend>
        <?php if ($mode === 'assign'): ?>
            <p>Full-time faculty may belong to three departments; part-time faculty may belong to one. Existing appointments are retained.</p>
            <div class="form-group"><label for="faculty_ID">Faculty Member (required)</label>
                <select id="faculty_ID" name="faculty_ID" required>
                    <option value="">Select a faculty member</option>
                    <?php foreach ($faculty as $person): ?><option value="<?= e($person['faculty_ID']) ?>" <?= (string) $person['faculty_ID'] === $selectedFaculty ? 'selected' : '' ?>><?= e($person['last_Name'] . ', ' . $person['first_Name'] . ' — ' . $person['faculty_ID'] . ' — ' . $person['faculty_Type']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="dept_ID">Department (required)</label>
                <select id="dept_ID" name="dept_ID" required><option value="">Select a department</option>
                    <?php foreach ($departments as $department): ?><option value="<?= e($department['dept_ID']) ?>" <?= (string) $department['dept_ID'] === ($values['dept_ID'] ?? '') ? 'selected' : '' ?>><?= e($department['dept_Name'] . ' (ID ' . $department['dept_ID'] . ')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="percent_Time">Percent Time (required)</label><input id="percent_Time" name="percent_Time" type="number" min="0" max="100" step="0.01" value="<?= e($values['percent_Time'] ?? '0') ?>" aria-describedby="allocation-help" required></div>
            <p id="allocation-help">Total time across departments must stay at or below 100%. Enter 0 for a courtesy appointment.</p>
            <div class="form-group"><label for="date_Of_Appointment">Appointment Date (required)</label><input id="date_Of_Appointment" name="date_Of_Appointment" type="date" max="<?= e((new DateTimeImmutable('today', new DateTimeZone('America/New_York')))->format('Y-m-d')) ?>" value="<?= e($values['date_Of_Appointment'] ?? (new DateTimeImmutable('today', new DateTimeZone('America/New_York')))->format('Y-m-d')) ?>" required></div>
        <?php else: ?>
            <?php if ($mode === 'update'): ?><p><strong>Department ID:</strong> <?= e($departmentID) ?></p><?php else: ?><p>A department ID is assigned when you save.</p><?php endif; ?>
            <?php foreach (['dept_Name' => ['Department Name', 100, 'text'], 'Email' => ['Department Email', 255, 'email'], 'Phone_No' => ['Phone Number', 20, 'tel'], 'dept_Manager' => ['Department Manager', 100, 'text']] as $field => [$label, $length, $type]): ?>
            <div class="form-group"><label for="<?= e($field) ?>"><?= e($label) ?> (required)</label><input id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" maxlength="<?= e($length) ?>" value="<?= e($values[$field] ?? '') ?>" required></div>
            <?php endforeach; ?>
            <div class="form-group"><label for="office_ID">Department Office (required)</label><select id="office_ID" name="office_ID" required><option value="">Select an office</option>
                <?php foreach ($offices as $office): ?><option value="<?= e($office['office_ID']) ?>" <?= $office['office_ID'] === ($values['office_ID'] ?? '') ? 'selected' : '' ?>><?= e($office['Bldg_Name'] . ' — Room ' . $office['room_Number'] . ' (' . $office['office_ID'] . ')') ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label for="chair_ID">Department Chair (required)</label><select id="chair_ID" name="chair_ID" required><option value="">Select a faculty member</option>
                <?php foreach ($faculty as $person): ?><option value="<?= e($person['faculty_ID']) ?>" <?= (string) $person['faculty_ID'] === (string) ($values['chair_ID'] ?? '') ? 'selected' : '' ?>><?= e($person['last_Name'] . ', ' . $person['first_Name'] . ' (ID ' . $person['faculty_ID'] . ')') ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <button class="btn" type="submit"><?= $mode === 'assign' ? 'Assign Faculty' : ($mode === 'create' ? 'Create Department' : 'Save Department') ?></button>
        </fieldset>
    </form>
    <?php endif; ?>
</div>

<?php if ($mode === 'assign'): ?>
<h2>Current Department Assignments</h2>
<form method="get" action="<?= e(app_url($paths[$mode])) ?>" class="user-form">
    <div class="form-group"><label for="faculty-filter">View Faculty Assignments</label><select id="faculty-filter" name="faculty_ID"><option value="">Select a faculty member</option>
        <?php foreach ($faculty as $person): ?><option value="<?= e($person['faculty_ID']) ?>" <?= (string) $person['faculty_ID'] === $selectedFaculty ? 'selected' : '' ?>><?= e($person['last_Name'] . ', ' . $person['first_Name'] . ' (ID ' . $person['faculty_ID'] . ')') ?></option><?php endforeach; ?>
    </select></div><button class="btn" type="submit">View Assignments</button>
</form>
<?php $visibleAssignments = array_filter($assignments, static fn(array $row): bool => (string) $row['faculty_ID'] === $selectedFaculty); ?>
<?php if ($selectedFaculty === ''): ?><p>Select a faculty member to view their existing appointments.</p>
<?php elseif (!$visibleAssignments): ?><p>No department assignments found for the selected faculty member.</p>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Department</th><th>Percent Time</th><th>Appointment Date</th></tr></thead><tbody>
<?php foreach ($visibleAssignments as $assignment): ?><tr><td><?= e($assignment['dept_Name']) ?></td><td><?= e($assignment['percent_Time']) ?>%</td><td><?= e($assignment['date_Of_Appointment']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php endif; ?>
<?php page_end(); ?>
