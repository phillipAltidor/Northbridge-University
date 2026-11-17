<?php
// Presentation only; authentication, queries and POST handling live in department-page.php.
if (!isset($mode, $canWrite, $paths, $titles, $values, $departments, $faculty, $action)) {
    http_response_code(404);
    exit;
}
$descriptions = [
    'create' => 'Add an academic department with its contact details, office and leadership.',
    'update' => 'Choose a department, review its information and save your changes.',
    'assign' => 'Review a faculty member’s appointments and add a department assignment.',
];
$ready = count($faculty) > 0 && ($mode === 'assign' ? count($departments) > 0 : count($offices) > 0);
$today = (new DateTimeImmutable('today', new DateTimeZone('America/New_York')))->format('Y-m-d');
?>
<link rel="stylesheet" href="<?= e(app_url('assets/css/admin-departments.css')) ?>">
<section class="department-page" aria-labelledby="department-title">
    <header class="department-heading">
        <div><p class="department-eyebrow">ACADEMIC ADMINISTRATION / DEPARTMENTS</p>
        <h1 id="department-title"><?= e($titles[$mode]) ?></h1>
        <p><?= e($descriptions[$mode]) ?></p></div>
        <span class="department-access"><?= $canWrite ? 'Edit access' : 'View-only access' ?></span>
    </header>
    <nav class="department-tabs" aria-label="Department actions">
        <?php foreach ($paths as $key => $path): ?>
        <a href="<?= e(app_url($path)) ?>" <?= $mode === $key ? 'aria-current="page"' : '' ?>><?= e($titles[$key]) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($message !== ''): ?><div class="department-notice department-success" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="department-notice department-error" role="alert"><strong>Please review the following.</strong><br><?= e($error) ?></div><?php endif; ?>
    <?php if (!$canWrite): ?><div class="department-notice" role="status">You can review department information. Contact your system administrator if you need permission to save changes.</div><?php endif; ?>
    <?php if (!$ready): ?><div class="department-notice" role="status"><?= $mode === 'assign' ? 'A department and faculty record must exist before you can create an assignment.' : 'A faculty record and an office must exist before you can save a department.' ?></div><?php endif; ?>

    <div class="department-workspace">
    <div>
    <?php if ($mode === 'update' || $mode === 'assign'): ?>
    <section class="department-card" aria-labelledby="selection-title">
        <h2 id="selection-title">1. <?= $mode === 'update' ? 'Choose a department' : 'Choose a faculty member' ?></h2>
        <form method="get" action="<?= e(app_url($paths[$mode])) ?>" class="department-selector">
            <div class="form-group">
                <label for="record-selector"><?= $mode === 'update' ? 'Department' : 'Faculty member' ?></label>
                <select id="record-selector" name="<?= $mode === 'update' ? 'dept_ID' : 'faculty_ID' ?>" required>
                    <option value="">Select <?= $mode === 'update' ? 'a department' : 'a faculty member' ?></option>
                    <?php if ($mode === 'update'): foreach ($departments as $department): ?>
                    <option value="<?= e($department['dept_ID']) ?>" <?= (int) $department['dept_ID'] === $departmentID ? 'selected' : '' ?>><?= e($department['dept_Name'] . ' (ID ' . $department['dept_ID'] . ')') ?></option>
                    <?php endforeach; else: foreach ($faculty as $person): ?>
                    <option value="<?= e($person['faculty_ID']) ?>" <?= (string) $person['faculty_ID'] === $selectedFaculty ? 'selected' : '' ?>><?= e($person['last_Name'] . ', ' . $person['first_Name'] . ' — ' . $person['faculty_ID'] . ' — ' . $person['faculty_Type']) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <button class="btn" type="submit"><?= $mode === 'update' ? 'Load Department' : 'View Faculty' ?></button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($mode === 'create' || ($mode === 'update' && $departmentID !== null && $values !== []) || ($mode === 'assign' && $selectedPerson !== null)): ?>
    <section class="department-card">
    <form method="post" action="<?= e($action) ?>">
        <input type="hidden" name="form_token" value="<?= e($_SESSION['department_form_token']) ?>">
        <input type="hidden" name="version" value="<?= e($version) ?>">
        <fieldset <?= !$canWrite || !$ready ? 'disabled' : '' ?>>
        <legend><?= $mode === 'assign' ? '2. Add a department assignment' : ($mode === 'update' ? '2. Edit department details' : 'Department details') ?></legend>
        <p class="department-help">All fields are required.</p>
        <?php if ($mode === 'assign'): ?>
            <input type="hidden" name="faculty_ID" value="<?= e($selectedFaculty) ?>">
            <div class="department-person"><strong><?= e($selectedPerson['first_Name'] . ' ' . $selectedPerson['last_Name']) ?></strong><span><?= e($selectedPerson['faculty_Type']) ?> · Faculty ID <?= e($selectedFaculty) ?></span></div>
            <div class="form-group"><label for="dept_ID">Department</label>
                <select id="dept_ID" name="dept_ID" required><option value="">Select a department</option>
                    <?php $assignedIDs = array_map('intval', array_column($assignments, 'dept_ID')); ?>
                    <?php foreach ($departments as $department): $assigned = in_array((int) $department['dept_ID'], $assignedIDs, true); ?>
                    <option value="<?= e($department['dept_ID']) ?>" <?= (string) $department['dept_ID'] === ($values['dept_ID'] ?? '') ? 'selected' : '' ?> <?= $assigned ? 'disabled' : '' ?>><?= e($department['dept_Name'] . ($assigned ? ' — already assigned' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="department-fields">
                <div class="form-group"><label for="percent_Time">Time allocation (%)</label><input id="percent_Time" name="percent_Time" type="number" min="0" max="100" step="0.01" value="<?= e($values['percent_Time'] ?? '0') ?>" aria-describedby="allocation-help" required><p id="allocation-help" class="department-help">Use 0% for a courtesy appointment.</p></div>
                <div class="form-group"><label for="date_Of_Appointment">Appointment date</label><input id="date_Of_Appointment" name="date_Of_Appointment" type="date" max="<?= e($today) ?>" value="<?= e($values['date_Of_Appointment'] ?? $today) ?>" required></div>
            </div>
        <?php else: ?>
            <?php if ($mode === 'update'): ?><p class="department-record">Department ID <strong><?= e($departmentID) ?></strong></p><?php endif; ?>
            <div class="form-group"><label for="dept_Name">Department name</label><input id="dept_Name" name="dept_Name" maxlength="100" value="<?= e($values['dept_Name'] ?? '') ?>" required></div>
            <div class="department-fields">
                <div class="form-group"><label for="Email">Department email</label><input id="Email" name="Email" type="email" maxlength="255" value="<?= e($values['Email'] ?? '') ?>" required></div>
                <div class="form-group"><label for="Phone_No">Phone number</label><input id="Phone_No" name="Phone_No" type="tel" maxlength="20" value="<?= e($values['Phone_No'] ?? '') ?>" required></div>
            </div>
            <h3>Office &amp; leadership</h3>
            <div class="form-group"><label for="office_ID">Department office</label><select id="office_ID" name="office_ID" required><option value="">Select an office</option>
                <?php foreach ($offices as $office): ?><option value="<?= e($office['office_ID']) ?>" <?= $office['office_ID'] === ($values['office_ID'] ?? '') ? 'selected' : '' ?>><?= e($office['Bldg_Name'] . ' — Room ' . $office['room_Number'] . ' (' . $office['office_ID'] . ')') ?></option><?php endforeach; ?>
            </select></div>
            <div class="department-fields">
                <div class="form-group"><label for="chair_ID">Department chair</label><select id="chair_ID" name="chair_ID" required><option value="">Select a faculty member</option>
                    <?php foreach ($faculty as $person): ?><option value="<?= e($person['faculty_ID']) ?>" <?= (string) $person['faculty_ID'] === (string) ($values['chair_ID'] ?? '') ? 'selected' : '' ?>><?= e($person['last_Name'] . ', ' . $person['first_Name'] . ' (ID ' . $person['faculty_ID'] . ')') ?></option><?php endforeach; ?>
                </select></div>
                <div class="form-group"><label for="dept_Manager">Department manager</label><input id="dept_Manager" name="dept_Manager" maxlength="100" value="<?= e($values['dept_Manager'] ?? '') ?>" required></div>
            </div>
        <?php endif; ?>
        <div class="department-form-actions"><button class="btn" type="submit"><?= $mode === 'assign' ? 'Assign Faculty' : ($mode === 'create' ? 'Create Department' : 'Save Changes') ?></button><a href="<?= e(app_url($paths[$mode])) ?>">Cancel</a></div>
        </fieldset>
    </form>
    </section>
    <?php else: ?>
    <div class="department-empty"><h2><?= $mode === 'update' ? 'Ready to review a department' : 'Start with a faculty member' ?></h2><p><?= $mode === 'update' ? 'Choose a department above to load its contact details, office and leadership.' : 'Choose someone above to review their existing appointments before assigning a department.' ?></p></div>
    <?php endif; ?>
    </div>

    <aside class="department-guidance" aria-label="Department guidance">
        <?php if ($mode === 'assign'): ?>
        <section class="department-card"><p class="department-eyebrow">ASSIGNMENT LIMITS</p><h2>Check availability</h2>
            <dl class="department-limits"><div><dt>Full-time faculty</dt><dd>Up to 3 departments</dd></div><div><dt>Part-time faculty</dt><dd>1 department</dd></div><div><dt>Total time allocation</dt><dd>Up to 100%</dd></div></dl>
            <p class="department-help">Existing appointments are retained. Duplicate assignments cannot be added.</p>
        </section>
        <?php if ($selectedPerson !== null): ?>
        <section class="department-card"><h2>Current appointments</h2>
            <p><strong><?= count($assignments) ?> / <?= $selectedPerson['faculty_Type'] === 'Full-Time' ? 3 : 1 ?></strong> departments · <strong><?= e(number_format(array_sum(array_column($assignments, 'percent_Time')), 2)) ?>%</strong> allocated</p>
            <?php if (!$assignments): ?><p class="department-help">No department appointments yet.</p><?php endif; ?>
            <ul class="department-appointments"><?php foreach ($assignments as $assignment): ?><li><strong><?= e($assignment['dept_Name']) ?></strong><span><?= e($assignment['percent_Time']) ?>% · Since <?= e($assignment['date_Of_Appointment']) ?></span></li><?php endforeach; ?></ul>
        </section>
        <?php endif; ?>
        <?php else: ?>
        <section class="department-card"><p class="department-eyebrow">BEFORE YOU SAVE</p><h2>Department checklist</h2>
            <ul class="department-checklist"><li>Use a department email that is not assigned to another department.</li><li>Select an existing office and a faculty member to serve as chair.</li><li>Review the contact details and manager’s name.</li></ul>
            <p class="department-help"><?= $mode === 'create' ? 'A department ID is assigned after saving.' : 'The department ID stays the same when you update its details.' ?></p>
        </section>
        <section class="department-card department-next"><h2>Faculty appointments</h2><p>Selecting a chair does not add a department appointment. Manage faculty membership separately.</p><a href="<?= e(app_url($paths['assign'])) ?>">Assign faculty to departments &rarr;</a></section>
        <?php endif; ?>
    </aside>
    </div>
</section>
