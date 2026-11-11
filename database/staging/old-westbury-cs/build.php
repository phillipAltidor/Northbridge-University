<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// This builder only reads academic records and writes isolated review artifacts.
$sourcePath = $argv[1] ?? '/private/tmp/old-westbury-fall-2026-complete.json';
$output = $argv[2] ?? __DIR__ . '/generated';
$review = json_decode(file_get_contents(__DIR__ . '/review.json'), true, 512, JSON_THROW_ON_ERROR);
$source = json_decode(file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
$data = $source['data'] ?? [];
if (count($data) !== 913 || (int)($source['reportedTotal'] ?? 0) !== 913
    || count(array_unique(array_column($data, 'courseReferenceNumber'))) !== 913) {
    throw new RuntimeException('Expected the complete reviewed 913-section Fall 2026 snapshot.');
}
foreach ($data as $section) {
    if (($section['term'] ?? null) !== '202609') {
        throw new RuntimeException('Mixed or unexpected source term.');
    }
}
$rules = $review['requirementsExample'];
$requirementCodes = array_unique(array_merge($rules['core'], $rules['electiveOptions'], $rules['mathRequired'], $rules['mathChooseOne']));
$referenceSections = array_values(array_filter($data, static fn($s) =>
    $s['subject'] === 'CS' || in_array($s['subjectCourse'], $requirementCodes, true)));
$referenceCourses = [];
foreach ($referenceSections as $section) {
    $code = $section['subjectCourse'];
    if (!isset($referenceCourses[$code])) {
        $referenceCourses[$code] = [
            'code' => $code,
            'title' => html_entity_decode($section['courseTitle'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'creditsLow' => $section['creditHourLow'], 'creditsHigh' => $section['creditHourHigh'],
            'requirementInScreenshot' => in_array($code, $requirementCodes, true),
            'level' => 'not supplied by this response; verify in catalog',
            'sectionCRNs' => [],
        ];
    }
    $referenceCourses[$code]['sectionCRNs'][] = $section['courseReferenceNumber'];
}
ksort($referenceCourses);

require dirname(__DIR__, 3) . '/config/database.php';
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
    $major = $pdo->query('SELECT major_ID, major_Name, dept_ID FROM Major WHERE major_ID = 1')->fetch();
    if (!$major || $major['major_Name'] !== 'Computer Science') {
        throw new RuntimeException('Expected Northbridge Computer Science major 1.');
    }
    $courses = $pdo->query("SELECT c.*, mr.min_Grade_Req FROM Course c
        LEFT JOIN Major_Requirements mr ON mr.course_ID=c.course_ID AND mr.major_ID=1
        WHERE c.dept_ID=1 AND c.course_Type='Undergraduate' ORDER BY c.course_ID")->fetchAll();
    $prerequisites = $pdo->query("SELECT cp.* FROM Course_Prerequisite cp JOIN Course c ON c.course_ID=cp.course_ID
        WHERE c.dept_ID=1 AND c.course_Type='Undergraduate' ORDER BY cp.course_ID, cp.prerequisite_course_ID")->fetchAll();
    $sections = $pdo->query("SELECT cs.* FROM Course_Section cs JOIN Course c ON c.course_ID=cs.course_ID
        JOIN Semester s ON s.semester_ID=cs.semester_ID
        WHERE c.dept_ID=1 AND c.course_Type='Undergraduate' AND s.semester_Name='Fall 2026' ORDER BY cs.CRN")->fetchAll();
    $periods = $pdo->query('SELECT * FROM Period ORDER BY period_ID')->fetchAll();
} finally {
    $pdo->rollBack();
}

$mappings = [];
foreach ($courses as $course) {
    $proposal = $review['candidateMappings'][$course['course_ID']] ?? ['candidates' => [], 'reason' => 'No defensible counterpart identified yet.'];
    $mappings[] = [
        'northbridgeCode' => $course['course_ID'], 'northbridgeTitle' => $course['course_Name'],
        'northbridgeCredits' => (int)$course['course_Credits'], 'requiredForMajor' => $course['min_Grade_Req'] !== null,
        'referenceCandidates' => $proposal['candidates'], 'reason' => $proposal['reason'],
        'status' => 'needs-review', 'approved' => false,
        'offeredCandidatesFall2026' => array_values(array_filter($proposal['candidates'], static fn($code) => isset($referenceCourses[$code]))),
    ];
}
$manifest = [
    'status' => 'draft-reference-only', 'builtAt' => date(DATE_ATOM),
    'source' => $review['sources'], 'sourceSHA256' => hash_file('sha256', $sourcePath),
    'sourceRetrievedAt' => $source['retrievedAt'] ?? null,
    'totalSourceSections' => count($data), 'referenceSections' => count($referenceSections),
    'csSubjectSections' => count(array_filter($referenceSections, static fn($s) => $s['subject'] === 'CS')),
    'referenceCourses' => count($referenceCourses), 'northbridgeCourses' => count($courses),
    'northbridgeRequiredCourses' => count(array_filter($courses, static fn($c) => $c['min_Grade_Req'] !== null)),
    'northbridgeFallSections' => count($sections),
    'requirementsNotOfferedInFallSnapshot' => array_values(array_diff($requirementCodes, array_keys($referenceCourses))),
    'databaseWrites' => false, 'testDatabaseCreated' => false, 'approvedMappings' => 0,
];
if (!is_dir($output) && !mkdir($output, 0755, true)) {
    throw new RuntimeException('Cannot create staging output directory.');
}
$write = static function (string $name, array $value) use ($output): void {
    if (file_put_contents($output . '/' . $name, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false) {
        throw new RuntimeException('Failed writing ' . $name);
    }
};
$write('manifest.json', $manifest);
$write('reference-sections.json', $referenceSections);
$write('reference-courses.json', array_values($referenceCourses));
$write('northbridge-academic-snapshot.json', compact('major', 'courses', 'prerequisites', 'sections', 'periods'));
$write('candidate-mappings.json', $mappings);
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
