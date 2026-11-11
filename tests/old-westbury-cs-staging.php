<?php
declare(strict_types=1);

$root = dirname(__DIR__) . '/database/staging/old-westbury-cs';
$read = static fn($file) => json_decode(file_get_contents($root . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$review = $read('review.json');
$manifest = $read('generated/manifest.json');
$sections = $read('generated/reference-sections.json');
$courses = $read('generated/reference-courses.json');
$mappings = $read('generated/candidate-mappings.json');
$snapshot = $read('generated/northbridge-academic-snapshot.json');
$assert($manifest['databaseWrites'] === false && $manifest['testDatabaseCreated'] === false, 'Reference-only scope');
$assert($manifest['totalSourceSections'] === 913, 'Complete source count');
$assert(count($sections) === $manifest['referenceSections'], 'Section count');
$assert(count(array_unique(array_column($sections, 'courseReferenceNumber'))) === count($sections), 'Unique reference CRNs');
$assert(array_unique(array_column($sections, 'term')) === ['202609'], 'Fall 2026 only');
$assert(count(array_filter($sections, static fn($s) => $s['subject'] === 'CS')) === 26, '26 CS subject sections');
$assert(count(array_filter($courses, static fn($c) => str_starts_with($c['code'], 'CS'))) === 21, '21 CS subject courses');
$assert(count($mappings) === count($snapshot['courses']), 'Every local undergraduate CS course reviewed');
$assert($review['catalogYear'] === null && $review['requirementsExample']['verified'] === false, 'Catalog not assumed approved');
$assert(count($review['requirementsExample']['core']) === 10
    && in_array('CS3911', $review['requirementsExample']['core'], true)
    && !in_array('CS3910', $review['requirementsExample']['core'], true), 'Approved Northbridge core uses CS3911 without a CS3910 alternative');
foreach ($mappings as $mapping) {
    $assert($mapping['approved'] === false && $mapping['status'] === 'needs-review', 'No automatic equivalencies');
}
$assert($review['requirementsExample']['coreCredits'] + $review['requirementsExample']['electiveCredits']
    + $review['requirementsExample']['mathCredits'] === 68, 'Provisional requirement arithmetic');
foreach ($courses as $course) {
    $assert(!str_contains($course['title'], '&amp;'), 'Titles decoded');
}
echo "$checks staging checks passed. No database connection or writes in this test.\n";
