<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/auth.php';
require dirname(__DIR__) . '/includes/admin-navigation.php';
$_SERVER['SCRIPT_NAME'] = '/admin/create-department.php';
$_SESSION['department_form_token'] = 'fixture-token';
$paths = ['create'=>'admin/create-department.php','update'=>'admin/update-department.php','assign'=>'admin/assign-faculty-department.php'];
$titles = ['create'=>'Create Department','update'=>'Update Department','assign'=>'Assign Faculty to Departments'];
$departments = [['dept_ID'=>1,'dept_Name'=>'Computer Science'], ['dept_ID'=>2,'dept_Name'=>'Mathematics']];
$faculty = [['faculty_ID'=>1,'first_Name'=>'Alex','last_Name'=>'Example','faculty_Type'=>'Full-Time']];
$offices = [['office_ID'=>'ADM101','room_Number'=>101,'Bldg_Name'=>'Administration']];
$checks = 0;
foreach (['create','update','update-loaded','assign','assign-loaded','readonly','error'] as $state) {
    $mode = str_starts_with($state, 'update') ? 'update' : (str_starts_with($state, 'assign') ? 'assign' : 'create');
    $canWrite = $state !== 'readonly';
    $departmentID = $state === 'update-loaded' ? 1 : null;
    $values = $departmentID ? ['dept_Name'=>'Computer Science','Email'=>'cs@example.test','Phone_No'=>'555-0100','office_ID'=>'ADM101','chair_ID'=>'1','dept_Manager'=>'Example manager'] : [];
    $error = $state === 'error' ? 'That email address already belongs to another department.' : '';
    if ($state === 'error') $values['dept_Name'] = '<script>alert(1)</script>';
    $message = '';
    $selectedFaculty = $state === 'assign-loaded' ? '1' : '';
    $selectedPerson = $selectedFaculty ? $faculty[0] : null;
    $assignments = $selectedFaculty ? [['dept_ID'=>1,'dept_Name'=>'Computer Science','percent_Time'=>'50.00','date_Of_Appointment'=>'2026-01-01']] : [];
    $version = 'fixture-version';
    $action = '/'.$paths[$mode].($departmentID ? '?dept_ID=1' : '');
    ob_start(); require dirname(__DIR__) . '/includes/department-view.php'; $html = ob_get_clean();
    $dom = new DOMDocument(); @$dom->loadHTML($html); $xp = new DOMXPath($dom);
    $expectPost = in_array($state, ['create','update-loaded','assign-loaded','readonly','error'], true);
    if ($xp->query('//form[@method="post"]')->length !== (int) $expectPost) throw new RuntimeException("Unexpected form for $state");
    if ($xp->query('//nav[@class="department-tabs"]/a[@aria-current="page"]')->length !== 1) throw new RuntimeException('Active action missing');
    if ($state === 'readonly' && $xp->query('//fieldset[@disabled]')->length !== 1) throw new RuntimeException('Read-only form enabled');
    if ($state === 'assign-loaded' && $xp->query('//select[@id="dept_ID"]/option[@value="1"][@disabled]')->length !== 1) throw new RuntimeException('Duplicate department available');
    if ($state === 'error' && ($xp->query('//script')->length || $xp->query('//*[@role="alert"]')->length !== 1)) throw new RuntimeException('Unsafe error rendering');
    foreach ($xp->query('//input[not(@type="hidden")]|//select') as $input) {
        $id = $input->getAttribute('id');
        if ($id === '' || $xp->query('//label[@for="'.$id.'"]')->length !== 1) throw new RuntimeException('Field label missing');
    }
    $checks++;
    if (isset($argv[1])) {
        $directory = $argv[1];
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        ob_start(); admin_navigation($paths[$mode]); $nav = ob_get_clean();
        $css = file_get_contents(dirname(__DIR__).'/assets/css/style.css') . file_get_contents(dirname(__DIR__).'/assets/css/admin-departments.css');
        $html = preg_replace('/<link[^>]+>/', '', $html);
        file_put_contents($directory.'/'.$state.'.html', '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Department preview — fictional fixtures</title><style>'.$css.'</style><header class="topbar"><div class="brand"><div class="seal">N</div><div><b>Northbridge University</b><small>Admin Portal · Design preview</small></div></div></header><div class="shell"><aside class="sidebar admin-sidebar">'.$nav.'</aside><main class="content">'.$html.'</main></div></html>');
    }
}
echo "PASS: $checks rendered form states, labels, active tabs, read-only/duplicate controls and escaping.\n";
