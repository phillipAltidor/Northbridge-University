<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/academic/test-database.php';
if (array_diff(array_slice($argv, 1), ['--all-programs'])) throw new RuntimeException('Only --all-programs is supported. Default: CIS pilot.');
$all = in_array('--all-programs', $argv, true);
$pdo = academic_test_database();
$before = academic_legacy_fingerprints($pdo);
$root = dirname(__DIR__) . '/database/staging/academic-alignment/rules/';
$names = ['course-metadata', 'approved-historical-course-versions', 'all-course-prerequisites', 'curricula', 'institutional-rules', 'general-education-mappings', 'project-decisions', 'recommended-interpretations', 'evaluator-capabilities'];
$data = []; $hash = hash_init('sha256');
foreach ($names as $name) {
    $raw = file_get_contents($root . $name . '.json');
    if ($raw === false) throw new RuntimeException('Missing rule input.');
    hash_update($hash, $name . "\0" . $raw);
    $data[$name] = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}
$sha = hash_final($hash);
$capabilities=$data['evaluator-capabilities'];
foreach ($capabilities['runtimeSHA256'] as $path=>$expected) {
    if (!in_array($path,['includes/academic/evaluator.php','includes/academic/curriculum-evaluator.php'],true) || hash_file('sha256',dirname(__DIR__).'/'.$path)!==$expected) throw new RuntimeException('Evaluator runtime changed; rebuild capabilities and test before import.');
}
if (count($capabilities['runtimeSHA256'])!==2 || $capabilities['evaluatorVersion']!=='curriculum-v1') throw new RuntimeException('Unsupported runtime capabilities.');
$encode = static fn(array $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/2026-11-07-academic-versions.sql');
// This migration contains only additive NB_ CREATE TABLE statements, no data writes.
foreach (explode(';', $sql) as $statement) if (trim($statement)) $pdo->exec($statement);
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT IGNORE INTO NB_CatalogVersion (edition,package_sha256) VALUES ('2026-2027',?)")->execute([$sha]);
    $q = $pdo->prepare('SELECT id FROM NB_CatalogVersion WHERE edition=? AND package_sha256=?');
    $q->execute(['2026-2027', $sha]); $catalog = (int)$q->fetchColumn();
    $insert = $pdo->prepare('INSERT IGNORE INTO NB_CourseVersion (catalog_id,version_key,course_code,title,credit_min,credit_max,definition) VALUES (?,?,?,?,?,?,?)');
    $metadata = [];
    foreach ($data['course-metadata']['courses'] as $course) {
        if (isset($data['all-course-prerequisites']['canonicalLookupAliases'][$course['courseCode']])) continue;
        $metadata[$course['courseCode']] = $course;
        $insert->execute([$catalog, $course['courseCode'], $course['courseCode'], $course['sourceTitle'], $course['creditMinimum'], $course['creditMaximum'], $encode($course)]);
    }
    foreach ($data['all-course-prerequisites']['courses'] as $record) {
        $key = $record['courseVersionOrCode'];
        if (str_contains($key, '@')) {
            $historical = null;
            foreach ($data['approved-historical-course-versions'] as $c) {
                if ($c['versionID'] === $key) $historical = $c;
            }
            // Preserve the complete historical evidence object, never borrow the newer title.
            if (!$historical) throw new RuntimeException('Historical version metadata missing: ' . $key);
            $insert->execute([$catalog,$key,$record['courseCode'],$historical['title'], $historical['credits'], $historical['credits'], $encode($historical)]);
        }
        $q = $pdo->prepare('SELECT id FROM NB_CourseVersion WHERE catalog_id=? AND version_key=?'); $q->execute([$catalog,$key]); $id=$q->fetchColumn();
        if (!$id) throw new RuntimeException('Prerequisite lacks a course version: '.$key);
        $pdo->prepare('INSERT IGNORE INTO NB_Prerequisite VALUES (?,?,?)')->execute([$id,$record['status'],$encode($record)]);
    }
    $programCount=0;
    foreach ($data['curricula']['programs'] as $program) {
        $pilot = $program['kind']==='major' && $program['program']==='Computer and Information Science, B.S.';
        if (!$all && !$pilot) continue;
        $matching=array_filter($capabilities['programs'],static fn($p)=>$p['program']===$program['program'] && $p['kind']===$program['kind'] && $p['evaluator']==='curriculum-v1');
        if (count($matching)!==1) throw new RuntimeException('Program lacks exact evaluator capability.');
        $evaluator='curriculum-v1';
        $pdo->prepare('INSERT IGNORE INTO NB_ProgramVersion (catalog_id,name,kind,evaluator,definition) VALUES (?,?,?,?,?)')->execute([$catalog,$program['program'],$program['kind'],$evaluator,$encode($program)]);
        $q=$pdo->prepare('SELECT id FROM NB_ProgramVersion WHERE catalog_id=? AND name=? AND kind=?');$q->execute([$catalog,$program['program'],$program['kind']]);$id=(int)$q->fetchColumn();
        foreach ($program['groups'] as $i=>$group) $pdo->prepare('INSERT IGNORE INTO NB_RequirementGroup (program_version_id,ordinal,definition) VALUES (?,?,?)')->execute([$id,$i+1,$encode($group)]);
        $programCount++;
    }
    foreach ($data['general-education-mappings']['rows'] as $i=>$row) $pdo->prepare('INSERT IGNORE INTO NB_GEApproval (catalog_id,ordinal,definition) VALUES (?,?,?)')->execute([$catalog,$i+1,$encode($row)]);
    foreach (['institutional-rules','project-decisions','recommended-interpretations','evaluator-capabilities'] as $key) $pdo->prepare('INSERT IGNORE INTO NB_AcademicPolicy VALUES (?,?,?)')->execute([$catalog,$key,$encode($data[$key])]);
    if (academic_legacy_fingerprints($pdo) !== $before) throw new RuntimeException('Legacy table verification failed.');
    $pdo->commit();
    echo json_encode(['database'=>'northbridge_ow_2026_test','catalogId'=>$catalog,'packageSHA256'=>$sha,'programsRequested'=>$programCount,'legacyTablesUnchanged'=>count($before),'portalConfigurationChanged'=>false],JSON_PRETTY_PRINT),"\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
