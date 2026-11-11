<?php
declare(strict_types=1);
/** Read-only evaluation of actual replacement attempts, never fixture transcripts. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/academic/curriculum-evaluator.php';
$args=getopt('', ['candidate:', 'output:', 'normalized-output:']);
if (isset($args['normalized-output']) && !str_starts_with(realpath(dirname($args['normalized-output'])).'/','/private/tmp/')) throw new RuntimeException('Private normalized output required.');
foreach (['candidate','output'] as $key) {
    $path=$args[$key]??'';
    $parent=realpath(dirname($path));
    if (!$parent || !str_starts_with($parent.'/', '/private/tmp/')) throw new RuntimeException('Private temporary paths required.');
}
$read=static function(string $path): array {return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);};
$root=dirname(__DIR__);$r=$root.'/database/staging/academic-alignment/rules/';
$candidate=$read($args['candidate']);$programs=$read($r.'curricula.json')['programs'];$institutional=$read($r.'institutional-rules.json');
$properties=$candidate['reviewedDegreeProperties']??null;
if ($properties!==null && (!hash_equals($properties['sourceAttributeFileSHA256']??'',hash_file('sha256',$root.'/database/staging/academic-alignment/official-course-attributes.json')) || ($properties['status']??'')!=='reviewed-source-properties-not-student-approvals')) throw new RuntimeException('Source property registry changed; re-review the candidate.');
$metadata=[];foreach ($read($r.'course-metadata.json')['courses'] as $row) $metadata[$row['courseCode']]=$row;
$engine=new \Northbridge\Academic\CurriculumEvaluator($metadata,$programs);
$programIndex=[];foreach ($programs as $program) $programIndex[$program['program']]=$program;
$byStudent=[];foreach ($candidate['attempts'] as $attempt) $byStudent[$attempt['studentId']][]=$attempt;
$ge=[];foreach ($read($r.'general-education-mappings.json')['rows'] as $row) if (!$row['lectureLabBundleCandidate']) $ge[$row['sourceCourseCode']][]=$row;
$evidence=static fn(string $scope,array $extra=[]):array=>$extra+['status'=>'approved','scope'=>$scope,'approvalId'=>'rebuild-project-'.hash('sha256',$scope),'approvedBy'=>'Northbridge fictional-data rebuild policy','approvedAt'=>'2026-11-07'];
$results=[];$counts=[];$graduation=0;
foreach ($candidate['students'] as $person) {
    $sid=$person['studentId'];$student=['program'=>$person['major'],'isTransferStudent'=>false,'concentration'=>$person['concentration'],'attempts'=>[], 'approvals'=>[], 'geApprovals'=>[], 'proficiencies'=>[]];
    $completed=[];
    foreach ($byStudent[$sid]??[] as $i=>$row) {
        // Planned future registration is not a registrar transcript attempt.
        if ($row['status']==='planned') continue;
        $c=$candidate['courses'][$row['courseKey']];$code=$c['courseCode'];
        $attempt=['courseCode'=>$code,'status'=>$row['status'],'grade'=>$row['grade'],'credits'=>$c['credits'],'origin'=>'local','termOrder'=>(int)$row['termCode'],'attemptOrder'=>$i+1,'catalogVersion'=>$c['catalogVersion']];
        // Only the separately reviewed source registry can supply attributes.
        // Missing historical classifications stay unknown; never approve guesses.
        $facts=$properties['courseProperties'][$row['courseKey']]??null;
        if ($facts!==null) {
            if ($facts['courseCode']!==$code || $facts['catalogVersion']!==$c['catalogVersion']) throw new RuntimeException('Course/version property mismatch.');
            $attempt+=array_intersect_key($facts,array_flip(['upperDivision','level','liberalArts','classifications','labBearing']));
            $attempt['attributeApproval']=$evidence('attributes:'.$code,['basis'=>'reviewed-public-academic-sources','sourceReference'=>$facts['sectionAttributeEvidenceSHA256'],'sourceMetadataSHA256'=>$facts['sourceMetadataSHA256']]);
        }
        $student['attempts'][]=$attempt;
        if ($row['status']==='completed') $completed[$code]=$attempt;
    }
    // Stable within-term ordering makes bounded partial allocations and GPA
    // accumulation independent of generator or database retrieval order.
    usort($student['attempts'],static fn($a,$b)=>[$a['termOrder'],$a['courseCode'],$a['catalogVersion']]<=>[$b['termOrder'],$b['courseCode'],$b['catalogVersion']]);
    foreach ($student['attempts'] as $index=>&$attempt) $attempt['attemptOrder']=$index+1;
    unset($attempt);
    $student['completedStandingCredits']=array_sum(array_column($completed,'credits'));
    foreach (['CompositionI'=>'EL1000','CompositionII'=>'EL2216'] as $key=>$code) if (isset($completed[$code])) {
        $a=$completed[$code];$student['proficiencies'][$key]=$evidence('proficiency:'.$key,['basis'=>'course','courseCode'=>$code,'completed'=>true,'grade'=>$a['grade'],'termOrder'=>$a['termOrder'],'sourceReference'=>'supplied course entry and owner-authorized rebuilt course history']);
    }
    foreach ($completed as $code=>$attempt) if (isset($ge[$code])) {
        $student['geApprovals'][]=$evidence('ge:'.$code,['courseCode'=>$code,'domains'=>array_values(array_unique(array_column($ge[$code],'domain'))),'cohortAndEffectiveDateReviewed'=>true,'sourceReference'=>array_column($ge[$code],'sourceURL'),'applicationPolicy'=>'Owner requested the new general education requirements across rebuilt past histories; this is a Northbridge project policy.']);
    }
    $admission=$person['registrarEvidence']['school-of-education-admission']??null;
    if ($admission && ($admission['status']??null)==='approved' && ($admission['studentId']??null)===$sid) $student['approvals']['school-of-education-admission']=$admission;
    if (isset($args['normalized-output'])) $normalizedInputs[$sid]=$student;
    $major=$engine->evaluate($programIndex[$person['major']],$student,$institutional);
    // Minor overlap uses this actual allocation, never the generator's target list.
    $allocated=[];foreach ($major['groups']??[] as $group) foreach ($group['codes']??[] as $code) $allocated[$code]=true;
    if ($allocated) {$student['majorCourseCodes']=array_keys($allocated);$student['majorAllocationApproval']=$evidence('major-allocation',['basis'=>'actual-completed-history-evaluator-allocation','studentId'=>$sid]);}
    $minor=$person['minor'] ? $engine->evaluate($programIndex[$person['minor']],$student,$institutional) : null;
    foreach (['major'=>$major,'minor'=>$minor] as $kind=>$evaluation) if ($evaluation!==null) {
        $name=$person[$kind];$state=$evaluation['status'];$counts[$name][$state]=($counts[$name][$state]??0)+1;
    }
    if ($major['graduationReady']??false) $graduation++;
    $results[]=['studentId'=>$sid,'major'=>$person['major'],'minor'=>$person['minor'],'completedCredits'=>$student['completedStandingCredits'],'majorEvaluation'=>$major,'minorEvaluation'=>$minor];
}
umask(0077);file_put_contents($args['output'],json_encode(['status'=>'actual-replacement-history-review-not-graduation-certification','workingDatabaseChanged'=>false,'results'=>$results],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");chmod($args['output'],0600);
if (isset($args['normalized-output'])) {file_put_contents($args['normalized-output'],json_encode($normalizedInputs,JSON_THROW_ON_ERROR));chmod($args['normalized-output'],0600);}
$summary=['status'=>'actual-replacement-history-review-not-graduation-certification','asOf'=>'2026-11-07','studentsEvaluated'=>count($results),'graduationReady'=>$graduation,'classificationApprovalsFabricated'=>false,'reviewedSourcePropertyVersions'=>count($properties['courseProperties']??[]),'currentAndFutureCoursesCountedAsCompleted'=>false,'sourceClassificationReviewRequired'=>$properties===null,'programStatusCounts'=>$counts,'candidateFileSHA256'=>hash_file('sha256',$args['candidate']),'workingDatabaseChanged'=>false,'privateStudentIdentifiersPublished'=>false];
file_put_contents($root.'/database/staging/academic-alignment/rebuilt-student-degree-review.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
file_put_contents(dirname($args['output']).'/degree-summary.json',json_encode($summary,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo json_encode(['status'=>$summary['status'],'studentsEvaluated'=>count($results),'graduationReady'=>$graduation,'workingDatabaseChanged'=>false]),"\n";
