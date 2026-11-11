<?php
declare(strict_types=1);
namespace Northbridge\Academic;
require_once __DIR__.'/repository.php';
require_once __DIR__.'/curriculum-evaluator.php';

/** Read-only test connection; never replaces the working portal's global PDO. */
function preview_database(): \PDO
{
    if (($_ENV['DB_NAME']??'')!=='northbridge_university' || !in_array($_ENV['DB_HOST']??'127.0.0.1',['localhost','127.0.0.1'],true)) throw new \RuntimeException('Unexpected academic preview configuration.');
    $db=new \PDO('mysql:host='.($_ENV['DB_HOST']??'127.0.0.1').';port='.($_ENV['DB_PORT']??'3306').';dbname=northbridge_ow_2026_test;charset=utf8mb4',$_ENV['DB_USER']??'root',$_ENV['DB_PASS']??'',[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]);
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    $db->beginTransaction();
    return $db;
}
function preview_package(\PDO $db): array
{
    // Deliberately pinned. A newer import does not change the public preview silently.
    $id=7;$sha='af788468dafce856c4ef61e4d428a42730e488ec3e9e96f969b0861f0ec2b032';
    $q=$db->prepare('SELECT edition,package_sha256,status FROM NB_CatalogVersion WHERE id=?');$q->execute([$id]);$row=$q->fetch();
    if (!$row || $row['package_sha256']!==$sha || $row['status']!=='isolated-test') throw new \RuntimeException('Reviewed preview package unavailable.');
    $repository=new Repository($db,$id);
    foreach ($repository->policy('evaluator-capabilities')['runtimeSHA256'] as $path=>$expected) {
        if (!in_array($path,['includes/academic/evaluator.php','includes/academic/curriculum-evaluator.php'],true) || hash_file('sha256',dirname(__DIR__,2).'/'.$path)!==$expected) throw new \RuntimeException('Preview evaluator package needs verification.');
    }
    return ['repository'=>$repository,'catalogId'=>$id,'edition'=>$row['edition'],'sha256'=>$sha];
}
function preview_cases(): array
{
    $raw=json_decode(file_get_contents(dirname(__DIR__,2).'/tests/fixtures/academic/all-programs.json'),true,512,JSON_THROW_ON_ERROR);
    $out=[];
    foreach ($raw['cases'] as $case) {
        $case['programKey']=substr(hash('sha256',$case['kind'].'|'.$case['name']),0,16);
        $case['caseKey']=substr(hash('sha256',json_encode([$case['kind'],$case['name'],$case['concentration'],$case['paths']],JSON_THROW_ON_ERROR)),0,16);
        $labels=array_filter(array_merge([$case['concentration']],array_values($case['paths'])));
        $case['label']=$labels?implode(' · ',$labels):'Standard requirements';
        $out[]=$case;
    }
    return $out;
}
function preview_selection(array $input,array $cases): array
{
    if (array_diff(array_keys($input),['program','case','view'])) throw new \InvalidArgumentException('Choose a program and example using the preview controls.');
    foreach ($input as $v) if (!is_string($v) || strlen($v)>80) throw new \InvalidArgumentException('Invalid preview selection.');
    $program=$input['program']??$cases[0]['programKey'];
    $options=array_values(array_filter($cases,static fn($c)=>$c['programKey']===$program));
    if (!$options) throw new \InvalidArgumentException('Choose an available program.');
    $key=$input['case']??$options[0]['caseKey'];
    $matches=array_values(array_filter($options,static fn($c)=>$c['caseKey']===$key));
    if (!$matches) throw new \InvalidArgumentException('Choose an available track or path for this program.');
    $view=$input['view']??'progress';
    if (!in_array($view,['complete','progress','review'],true)) throw new \InvalidArgumentException('Choose an available student example.');
    return ['case'=>$matches[0],'options'=>$options,'view'=>$view];
}
function preview_student(array $case,string $view): array
{
    $student=$case['student'];
    if ($view==='progress') {
        $n=max(1,(int)floor(count($student['attempts'])/2));
        $student['attempts']=array_slice($student['attempts'],0,min(count($student['attempts']),$n+2));
        foreach ($student['attempts'] as $i=>&$a) if ($i>=$n) {$a['status']='in-progress';unset($a['grade']);}unset($a);
        // Future or pending approvals are not borrowed from the complete sample.
        $student['approvals']=[];$student['proficiencies']=[];unset($student['geWaiver'],$student['mathProficiencyApproval']);
        $student['completedStandingCredits']=array_sum(array_map(static fn($a)=>$a['status']==='completed'?$a['credits']:0,$student['attempts']));
    } elseif ($view==='review') {
        $student['attempts'][0]['origin']='transfer';$student['isTransferStudent']=true;
        unset($student['attempts'][0]['equivalenceApproval']);
    }
    return $student;
}
function preview_status(string $status): string
{
    return ['satisfied'=>'Complete','unsatisfied'=>'Not complete','review-required'=>'Needs review','conditional'=>'In progress'][$status]??'Needs review';
}
function preview_requirement_title(array $node): string
{
    return ['all-courses'=>'Required courses','all-course-versions'=>'Required course versions','choose-distinct-courses'=>'Course choices','choose-credits'=>'Elective credits','choose-from-union'=>'Elective choices','choose-by-selector'=>'Eligible electives','allocation-group'=>'Course distribution','selected-concentration'=>'Concentration','choose-one-path'=>'Complete course path','choose-distinct-domain-courses'=>'Distinct subject areas','choose-distinct-units'=>'Elective course or lab bundles','reviewed-evidence'=>'Required approval'][$node['type']]??'Requirement';
}
/** Human-readable options, not a declaration that every alternative is required. */
function preview_requirement_text(array $node,?string $concentration=null): string
{
    $type=$node['type'];$credits=isset($node['minimumCredits'])?' At least '.$node['minimumCredits'].' credits.':'';
    if ($type==='reviewed-evidence') return is_string($node['description'])?$node['description']:'Approved math and four-semester science plan with its grade and advising requirements.';
    if ($type==='selected-concentration') return 'Complete the requirements for '.($concentration??'one selected concentration').'.'.$credits;
    if ($type==='all-courses'||$type==='all-course-versions') return 'Complete each required course.'.$credits;
    if ($type==='choose-one-path') return 'Complete one entire path: '.implode(' or ',array_keys($node['paths'])).'.';
    if ($type==='choose-distinct-domain-courses') return 'Choose courses from '.$node['minimumDomains'].' different foundation areas.';
    if ($type==='allocation-group') return 'Meet every distribution group with distinct courses.'.$credits;
    return 'Choose '.($node['minimumCount']??'enough').' distinct '.($type==='choose-distinct-units'?'course or lecture/lab units':'courses').'.'.$credits;
}
function preview_option_codes(array $node): array
{
    $out=[];
    foreach ($node['courses']??$node['eligibleCourses']??[] as $c) $out[]=is_array($c)?$c['courseCode'].' ('.$c['catalogVersion'].')':$c;
    foreach ($node['options']??[] as $c) {if (is_string($c)) $out[]=$c;elseif (isset($c['courses'])) $out[]=implode(' + ',$c['courses']);}
    if (isset($node['domains'])) foreach ($node['domains'] as $d=>$courses) $out[]=$d.': '.implode(', ',$courses);
    $selectors=$node['type']==='choose-by-selector'?[$node]:($node['selectors']??[]);
    foreach ($selectors as $s) {
        $out[]=implode('/',(array)($s['subject']??[])).' '.(isset($s['levels'])?implode('/',array_map(static fn($v)=>(string)$v,$s['levels'])).' level':('level '.($s['minimumLevel']??'as approved').'+')).(isset($s['classification'])?' · '.$s['classification']:'');
        if (!empty($s['excludeCourses'])) $out[]='Excluded: '.implode(', ',$s['excludeCourses']);
    }
    return array_values(array_unique($out));
}
function preview_check_label(string $key): string
{
    $labels=['total-credits'=>'Total earned credits','institutional-residency'=>'Credits earned here','upper-division'=>'Upper-division credits','liberal-arts'=>'Liberal-arts credits','transfer-limit'=>'Transfer credit limit','institutional-gpa'=>'Cumulative GPA','upper-major'=>'Upper-division major credits','transfer-major-residency'=>'Transfer student major residency','writing'=>'Writing proficiency','math-proficiency'=>'Math proficiency','general-education'=>'General education','outside-major'=>'Minor credits outside the major','major-overlap'=>'Major/minor overlap review'];
    return $labels[$key]??ucfirst(str_replace(['-','_'],' ',preg_replace('/([a-z])([A-Z])/','$1 $2',$key)));
}
