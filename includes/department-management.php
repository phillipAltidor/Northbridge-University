<?php
declare(strict_types=1);

// Schema 2.3 has no admin security-level column. Explicitly grant write access
// for this feature in private server configuration; all other admins can view.
function department_can_write(int $adminID): bool
{
    $configured = $_ENV['ADMIN_DEPARTMENT_WRITER_IDS'] ?? '';
    if (!is_string($configured) || trim($configured) === '') {
        return false;
    }
    $ids = array_map('trim', explode(',', $configured));
    foreach ($ids as $id) {
        if (!preg_match('/^[1-9][0-9]*$/D', $id)) {
            return false;
        }
    }
    return in_array((string) $adminID, $ids, true);
}

function department_text(array $input, string $key, string $label, int $length): string
{
    $value = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    if ($value === '' || mb_strlen($value, 'UTF-8') > $length
        || preg_match('/[\x00-\x1f\x7f]/u', $value)) {
        throw new DomainException("$label is required and must be $length characters or fewer.");
    }
    return $value;
}

function department_positive_id($value, string $label): int
{
    if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)
        || strlen($value) > 10 || (int) $value > 2147483647) {
        throw new DomainException("Select a valid $label.");
    }
    return (int) $value;
}

function department_values(array $input): array
{
    $values = [
        'dept_Name' => department_text($input, 'dept_Name', 'Department name', 100),
        'office_ID' => department_text($input, 'office_ID', 'Office', 20),
        'Email' => department_text($input, 'Email', 'Email', 255),
        'Phone_No' => department_text($input, 'Phone_No', 'Phone number', 20),
        'chair_ID' => department_positive_id($input['chair_ID'] ?? null, 'department chair'),
        'dept_Manager' => department_text($input, 'dept_Manager', 'Department manager', 100),
    ];
    if (!filter_var($values['Email'], FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Enter a valid department email address.');
    }
    return $values;
}

function department_version(array $row): string
{
    $snapshot = [];
    foreach (['dept_ID', 'dept_Name', 'office_ID', 'Email', 'Phone_No', 'chair_ID', 'dept_Manager'] as $key) {
        $snapshot[$key] = (string) $row[$key];
    }
    return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
}

function department_save(PDO $pdo, array $input, ?int $departmentID, string $version = ''): int
{
    $values = department_values($input);
    // Retry a generated-ID collision or a transient deadlock. No partial writes.
    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            $pdo->beginTransaction();
            if ($departmentID !== null) {
                $stmt = $pdo->prepare('SELECT * FROM Department WHERE dept_ID = ? FOR UPDATE');
                $stmt->execute([$departmentID]);
                $before = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$before) {
                    throw new DomainException('The selected department no longer exists.');
                }
                if (!hash_equals(department_version($before), $version)) {
                    throw new DomainException('Another admin changed this department. Reload it before saving again.');
                }
            }
            $stmt = $pdo->prepare('SELECT o.office_ID FROM Office o JOIN Room r ON r.room_ID = o.office_ID WHERE o.office_ID = ? AND r.room_Type = ? FOR UPDATE');
            $stmt->execute([$values['office_ID'], 'Office']);
            if (!$stmt->fetchColumn()) {
                throw new DomainException('Select an existing office, not a classroom or lab.');
            }
            $stmt = $pdo->prepare('SELECT f.faculty_ID FROM Faculty f JOIN User u ON u.user_ID = f.faculty_ID WHERE f.faculty_ID = ? AND u.user_Type = ? FOR UPDATE');
            $stmt->execute([$values['chair_ID'], 'Faculty']);
            if (!$stmt->fetchColumn()) {
                throw new DomainException('Select an existing faculty member as department chair.');
            }
            $stmt = $pdo->prepare('SELECT dept_ID FROM Department WHERE Email = ? AND dept_ID <> ?');
            $stmt->execute([$values['Email'], $departmentID ?? 0]);
            if ($stmt->fetchColumn()) {
                throw new DomainException('That email address already belongs to another department.');
            }
            if ($departmentID === null) {
                $id = (int) $pdo->query('SELECT COALESCE(MAX(dept_ID), 0) + 1 FROM Department')->fetchColumn();
                if ($id > 2147483647) {
                    throw new DomainException('No department IDs are available.');
                }
                $stmt = $pdo->prepare('INSERT INTO Department (dept_ID, dept_Name, office_ID, Email, Phone_No, chair_ID, dept_Manager) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute(array_merge([$id], array_values($values)));
            } else {
                $id = $departmentID;
                $stmt = $pdo->prepare('UPDATE Department SET dept_Name = ?, office_ID = ?, Email = ?, Phone_No = ?, chair_ID = ?, dept_Manager = ? WHERE dept_ID = ?');
                $stmt->execute(array_merge(array_values($values), [$id]));
            }
            $pdo->commit();
            return $id;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof PDOException && in_array((int) ($error->errorInfo[1] ?? 0), [1062, 1213, 1205], true)) {
                if ($attempt < 2) {
                    continue;
                }
                throw new DomainException('The department could not be saved because of a conflicting change or duplicate email. Reload and try again.');
            }
            throw $error;
        }
    }
    throw new RuntimeException('Department save failed.');
}

function department_assign_faculty(PDO $pdo, array $input): void
{
    $facultyID = department_positive_id($input['faculty_ID'] ?? null, 'faculty member');
    $departmentID = department_positive_id($input['dept_ID'] ?? null, 'department');
    $percent = department_text($input, 'percent_Time', 'Percent time', 6);
    if (!preg_match('/^(?:100(?:\.0{1,2})?|[0-9]{1,2}(?:\.[0-9]{1,2})?)$/D', $percent)) {
        throw new DomainException('Percent time must be between 0 and 100, with at most two decimal places.');
    }
    $date = department_text($input, 'date_Of_Appointment', 'Appointment date', 10);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $today = new DateTimeImmutable('today', new DateTimeZone('America/New_York'));
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1000-01-01' || $date > $today->format('Y-m-d')) {
        throw new DomainException('Enter a valid appointment date on or before today.');
    }
    try {
        $pdo->beginTransaction();
        // Lock the faculty row first so concurrent assignments cannot exceed caps.
        $stmt = $pdo->prepare('SELECT f.faculty_Type FROM Faculty f JOIN User u ON u.user_ID = f.faculty_ID WHERE f.faculty_ID = ? AND u.user_Type = ? FOR UPDATE');
        $stmt->execute([$facultyID, 'Faculty']);
        $type = $stmt->fetchColumn();
        if (!in_array($type, ['Full-Time', 'Part-Time'], true)) {
            throw new DomainException('Select an existing full-time or part-time faculty member.');
        }
        $stmt = $pdo->prepare('SELECT dept_ID FROM Department WHERE dept_ID = ? FOR UPDATE');
        $stmt->execute([$departmentID]);
        if (!$stmt->fetchColumn()) {
            throw new DomainException('Select an existing department.');
        }
        $stmt = $pdo->prepare('SELECT dept_ID, percent_Time FROM Faculty_Department WHERE faculty_ID = ? FOR UPDATE');
        $stmt->execute([$facultyID]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $allocated = 0;
        foreach ($assignments as $assignment) {
            if ((int) $assignment['dept_ID'] === $departmentID) {
                throw new DomainException('This faculty member already belongs to that department.');
            }
            $allocated += (int) round((float) $assignment['percent_Time'] * 100);
        }
        $limit = $type === 'Full-Time' ? 3 : 1;
        if (count($assignments) >= $limit) {
            throw new DomainException($type . ' faculty can belong to at most ' . $limit . ($limit === 1 ? ' department.' : ' departments.'));
        }
        if ($allocated + (int) round((float) $percent * 100) > 10000) {
            throw new DomainException('Total department time cannot exceed 100%. Use 0% for a courtesy appointment.');
        }
        $stmt = $pdo->prepare('INSERT INTO Faculty_Department (faculty_ID, dept_ID, percent_Time, date_Of_Appointment) VALUES (?, ?, ?, ?)');
        $stmt->execute([$facultyID, $departmentID, $percent, $date]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
