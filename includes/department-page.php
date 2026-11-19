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
            $destination = $paths[$mode] . '?faculty_ID=' . department_positive_id($_POST['faculty_ID'] ?? null, 'faculty member');
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
$selectedFaculty = $values['faculty_ID'] ?? '';
if ($mode === 'assign' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $selectedFaculty = is_string($_GET['faculty_ID'] ?? null) ? $_GET['faculty_ID'] : '';
    $values['faculty_ID'] = $selectedFaculty;
}
$selectedPerson = null;
foreach ($faculty as $person) {
    if ((string) $person['faculty_ID'] === $selectedFaculty) {
        $selectedPerson = $person;
        break;
    }
}
$assignments = [];
if ($mode === 'assign' && $selectedPerson !== null) {
    $stmt = $pdo->prepare('SELECT fd.dept_ID, fd.percent_Time, fd.date_Of_Appointment, d.dept_Name FROM Faculty_Department fd JOIN Department d ON d.dept_ID = fd.dept_ID WHERE fd.faculty_ID = ? ORDER BY d.dept_Name');
    $stmt->execute([$selectedPerson['faculty_ID']]);
    $assignments = $stmt->fetchAll();
}
$action = app_url($paths[$mode]) . ($mode === 'update' && $departmentID !== null ? '?dept_ID=' . $departmentID : '');
page_start($titles[$mode], 'Admin', $paths[$mode], null, false);
require __DIR__ . '/department-view.php';
page_end();
