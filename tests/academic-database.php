<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/academic/test-database.php';
require dirname(__DIR__).'/includes/academic/repository.php';
require dirname(__DIR__).'/includes/academic/curriculum-evaluator.php';
require __DIR__.'/fixtures/academic/cis.php';
require __DIR__.'/fixtures/academic/mis.php';
use Northbridge\Academic\Repository;
use Northbridge\Academic\CisDegreeEvaluator;
use Northbridge\Academic\MisDegreeEvaluator;
use Northbridge\Academic\PrerequisiteEvaluator;
$pdo=academic_test_database();$root=dirname(__DIR__).'/database/staging/academic-alignment/';
$read=static fn($file)=>json_decode(file_get_contents($root.'rules/'.$file.'.json'),true,512,JSON_THROW_ON_ERROR);
$names=['course-metadata','approved-historical-course-versions','all-course-prerequisites','curricula','institutional-rules','general-education-mappings','project-decisions','recommended-interpretations','evaluator-capabilities'];
$hash=hash_init('sha256');foreach ($names as $name) hash_update($hash,$name."\0".file_get_contents($root.'rules/'.$name.'.json'));$sha=hash_final($hash);
$q=$pdo->prepare('SELECT id FROM NB_CatalogVersion WHERE edition=? AND package_sha256=?');$q->execute(['2026-2027',$sha]);$catalog=(int)$q->fetchColumn();
$count=0;$assert=static function(bool $ok,string $label) use (&$count) {$count++;if (!$ok) throw new RuntimeException('FAIL: '.$label);};
$assert($catalog>0,'Explicit package version loaded');$repo=new Repository($pdo,$catalog);
foreach ($read('all-course-prerequisites')['courses'] as $r) $assert($repo->prerequisite($r['courseVersionOrCode'])==$r,'Prerequisite round trip '.$r['courseVersionOrCode']);
$programs=$pdo->prepare('SELECT definition,evaluator FROM NB_ProgramVersion WHERE catalog_id=?');$programs->execute([$catalog]);$loaded=$programs->fetchAll();
$expectedPrograms=in_array('--all-programs',$argv,true)?24:1;
$assert(count($loaded)===$expectedPrograms,'Expected program coverage');
$definitions=$read('curricula')['programs'];$groupCount=0;
foreach ($loaded as $row) {
    $p=json_decode($row['definition'],true,512,JSON_THROW_ON_ERROR);$source=array_values(array_filter($definitions,static fn($v)=>$v['program']===$p['program'] && $v['kind']===$p['kind']))[0];
    $assert($p==$source,'Program definition round trip '.$p['program']);
    $pilot=$p['program']==='Computer and Information Science, B.S.' && $p['kind']==='major';
    $mis=$p['program']==='Management Information Systems, B.S.' && $p['kind']==='major';
    $assert($row['evaluator']==='curriculum-v1','All registered program evaluators use the bound runtime');$groupCount+=count($p['groups']);
    $q=$pdo->prepare('SELECT g.definition FROM NB_RequirementGroup g JOIN NB_ProgramVersion p ON p.id=g.program_version_id WHERE p.catalog_id=? AND p.name=? AND p.kind=? ORDER BY g.ordinal');$q->execute([$catalog,$p['program'],$p['kind']]);
    $groups=array_map(static fn($raw)=>json_decode($raw,true,512,JSON_THROW_ON_ERROR),$q->fetchAll(PDO::FETCH_COLUMN));$assert($groups==$p['groups'],'Requirement group ordering preserved');
}
foreach (['institutional-rules','project-decisions','recommended-interpretations','evaluator-capabilities'] as $key) $assert($repo->policy($key)==$read($key),'Policy round trip '.$key);
foreach ($repo->policy('evaluator-capabilities')['runtimeSHA256'] as $path=>$sha256) $assert(hash_file('sha256',dirname(__DIR__).'/'.$path)===$sha256,'Stored evaluator matches tested runtime '.$path);
$q=$pdo->prepare('SELECT definition FROM NB_GEApproval WHERE catalog_id=? ORDER BY ordinal');$q->execute([$catalog]);$ge=array_map(static fn($raw)=>json_decode($raw,true,512,JSON_THROW_ON_ERROR),$q->fetchAll(PDO::FETCH_COLUMN));$assert($ge==$read('general-education-mappings')['rows'],'All GE source conditions preserved');
$expected=[];$aliases=$read('all-course-prerequisites')['canonicalLookupAliases'];
foreach ($read('course-metadata')['courses'] as $c) if (!isset($aliases[$c['courseCode']])) $expected[$c['courseCode']]=$c;
foreach ($read('approved-historical-course-versions') as $c) $expected[$c['versionID']]=$c;
$q=$pdo->prepare('SELECT version_key,definition FROM NB_CourseVersion WHERE catalog_id=?');$q->execute([$catalog]);$courses=$q->fetchAll();
$assert(count($courses)===count($expected),'Canonical course version count');foreach ($courses as $row) $assert(json_decode($row['definition'],true,512,JSON_THROW_ON_ERROR)==$expected[$row['version_key']],'Course metadata round trip '.$row['version_key']);
$program=$repo->program('Computer and Information Science, B.S.');$fixture=cis_complete_fixture($program);
$audit=(new CisDegreeEvaluator($repo->courseMetadata()))->evaluate($program,$fixture,$repo->policy('institutional-rules'));
$assert($audit['graduationReady']===true,'Database-backed complete synthetic degree');
$eligibility=(new PrerequisiteEvaluator())->evaluate($repo->prerequisite('CS5910')['rule'],$fixture,10);$assert($eligibility['status']==='satisfied','Database-backed senior project eligibility');
$misAudit=null;
$previousPackagePreserved=null;
$allProgramScenarios=0;
if ($expectedPrograms===24) {
    $misProgram=$repo->program('Management Information Systems, B.S.');$misFixture=mis_complete_fixture($misProgram);
    $misAudit=(new MisDegreeEvaluator($repo->courseMetadata()))->evaluate($misProgram,$misFixture,$repo->policy('institutional-rules'));
    $assert($misAudit['graduationReady']===true,'Database-backed MIS synthetic completion');
    $assert(count($misAudit['groups'][2]['selectedCourses'])===2,'Database-backed MIS distinct two-of-three choice');
    $analytics=(new PrerequisiteEvaluator())->evaluate($repo->prerequisite('MA2701')['rule'],['attempts'=>[cis_attempt('MA2000','C'),cis_attempt('MA2300','C')]],10);
    $assert($analytics['status']==='satisfied','Database-backed explicit analytics prerequisite');
    $registry=$repo->programs();$engine=new \Northbridge\Academic\CurriculumEvaluator($repo->courseMetadata(),$registry);
    foreach (json_decode(file_get_contents(__DIR__.'/fixtures/academic/all-programs.json'),true)['cases'] as $case) {
        $p=$repo->program($case['name'],$case['kind']);$result=$engine->evaluate($p,$case['student'],$repo->policy('institutional-rules'));
        $assert($result['programComplete']===true,'Database-backed program path '.$case['name'].'/'.$case['concentration']);
        $assert($case['kind']==='major'?$result['graduationReady']===true:$result['graduationReady']===false,'Degree versus minor completion semantics');$allProgramScenarios++;
    }
    $initial=json_decode(file_get_contents($root.'cis-pilot-initial-receipt.json'),true,512,JSON_THROW_ON_ERROR);
    $q=$pdo->prepare('SELECT id FROM NB_CatalogVersion WHERE package_sha256=?');$q->execute([$initial['packageSHA256']]);$previousId=$q->fetchColumn();
    if ($previousId && (int)$previousId!==$catalog) {
        $previous=new Repository($pdo,(int)$previousId);
        $assert($previous->prerequisite('MA2701')['rule']['type']==='review-required','Earlier package prerequisite is not rewritten');
        $q=$pdo->prepare('SELECT evaluator FROM NB_ProgramVersion WHERE catalog_id=? AND name=?');$q->execute([$previousId,'Management Information Systems, B.S.']);
        $assert($q->fetchColumn()==='review-required','Earlier MIS evaluator registration retained');
        $previousPackagePreserved=true;
    }
    $misMilestone=json_decode(file_get_contents($root.'mis-pilot-receipt.json'),true,512,JSON_THROW_ON_ERROR);
    $q=$pdo->prepare('SELECT id FROM NB_CatalogVersion WHERE package_sha256=?');$q->execute([$misMilestone['packageSHA256']]);$misMilestoneId=$q->fetchColumn();
    if ($misMilestoneId && (int)$misMilestoneId!==$catalog) {
        $q=$pdo->prepare('SELECT evaluator FROM NB_ProgramVersion WHERE catalog_id=? AND name=?');
        $q->execute([$misMilestoneId,'Management Information Systems, B.S.']);$assert($q->fetchColumn()==='mis-pilot-v1','Prior MIS pilot registration retained');
        $q->execute([$misMilestoneId,'Business Administration, B.S.']);$assert($q->fetchColumn()==='review-required','Prior unimplemented program status retained');
    }
}
$receipt=json_decode(file_get_contents($root.'test-database-receipt.json'),true,512,JSON_THROW_ON_ERROR);
$private=json_decode(file_get_contents($receipt['privateSnapshotDirectory'].'/receipt.json'),true,512,JSON_THROW_ON_ERROR);
$legacy=academic_legacy_fingerprints($pdo);$original=[];foreach ($private['tableFingerprints'] as $name=>$f) $original[$name]=[$f['rows'],$f['checksum']];
$assert($legacy===$original,'All 47 test legacy tables exactly match private snapshot');
// Read the working source only to prove it still matches the snapshot. Never issue writes here.
$database=$_ENV['DB_NAME']??'northbridge_university';
$pdo=new PDO('mysql:host='.($_ENV['DB_HOST']??'127.0.0.1').';port='.($_ENV['DB_PORT']??'3306').';dbname='.$database.';charset=utf8mb4',$_ENV['DB_USER']??'root',$_ENV['DB_PASS']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$assert($database===$receipt['sourceDatabase'],'Working connection remains on original source');
$assert(academic_legacy_fingerprints($pdo)===$original,'Working source rows/checksums unchanged');
echo json_encode(['status'=>'passed','checks'=>$count,'catalogId'=>$catalog,'packageSHA256'=>$sha,'programs'=>count($loaded),'courses'=>count($courses),'prerequisites'=>count($read('all-course-prerequisites')['courses']),'requirementGroups'=>$groupCount,'generalEducationRows'=>count($ge),'sourceAndLegacyTablesUnchanged'=>47,'syntheticDegreeStatus'=>$audit['status'],'syntheticMISDegreeStatus'=>$misAudit['status']??null,'allProgramScenarios'=>$allProgramScenarios,'syntheticCS5910Eligibility'=>$eligibility['status'],'previousPackagePreserved'=>$previousPackagePreserved,'portalConfigurationChanged'=>false],JSON_PRETTY_PRINT),"\n";
