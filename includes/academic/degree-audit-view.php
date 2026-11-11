<?php
declare(strict_types=1);
namespace Northbridge\Academic;

function audit_label(string $state): string
{
    return ['satisfied'=>'Complete','unsatisfied'=>'Still required','review-required'=>'Needs review','conditional'=>'In progress','planned'=>'Planned','alternative'=>'Alternative'][$state]??'Needs review';
}
function audit_badge(string $state,?string $label=null): void
{
    ?><span class="audit-status audit-<?=\e($state)?>"><?=\e($label??audit_label($state))?></span><?php
}
function audit_requirement_text(array $node,?string $concentration=null): string
{
    $credits=isset($node['minimumCredits'])?' Minimum '.$node['minimumCredits'].' credits.':'';
    return match($node['type']) {
        'all-courses','all-course-versions'=>'Complete every course below.'.$credits,
        'choose-distinct-courses','choose-by-selector','choose-from-union'=>'Choose '.($node['minimumCount']??'enough').' distinct eligible courses.'.$credits,
        'choose-distinct-units'=>'Choose '.($node['minimumCount']??'enough').' distinct course or lecture/lab units.'.$credits,
        'choose-credits'=>'Complete eligible elective credits.'.$credits,
        'choose-one-path'=>'Complete one of these paths. The paths are alternatives.',
        'allocation-group'=>'Meet every distribution group using distinct courses.'.$credits,
        'selected-concentration'=>'Concentration: '.($concentration??'advisor selection needed').'.'.$credits,
        'choose-distinct-domain-courses'=>'Choose courses from '.$node['minimumDomains'].' different subject areas.',
        'reviewed-evidence'=>is_string($node['description']??null)?$node['description']:'Advisor-approved mathematics and four distinct qualifying science semesters are required.',
        default=>'Review the program requirement with your advisor.',
    };
}
function audit_requirement_title(array $node): string
{
    return ['all-courses'=>'Required courses','all-course-versions'=>'Required courses','choose-distinct-courses'=>'Course choices','choose-by-selector'=>'Eligible electives','choose-from-union'=>'Elective choices','choose-credits'=>'Elective credits','choose-distinct-units'=>'Course and laboratory choices','allocation-group'=>'Course distribution','selected-concentration'=>'Concentration','choose-one-path'=>'Choose one path','choose-distinct-domain-courses'=>'Foundation areas','reviewed-evidence'=>'Advisor or completion review'][$node['type']]??'Requirement';
}
function audit_options(array $node, array $program, DegreeAuditService $service): array
{
    $options=[];
    foreach ($node['courses']??$node['eligibleCourses']??[] as $c) {
        $code=is_array($c)?$c['courseCode']:$c;$version=is_array($c)?($c['catalogVersion']??null):null;
        $options[$code.'@'.$version]=['code'=>$code,'version'=>$version];
    }
    foreach ($node['options']??[] as $o) foreach (is_string($o)?[$o]:($o['courses']??[]) as $code) $options[$code]=['code'=>$code,'version'=>null];
    return array_values($options);
}
function audit_course_table(array $options,array $node,array $group,array $program,array $audit,DegreeAuditService $service): void
{
    if (!$options) return;
    ?><div class="audit-table-scroll"><table class="audit-course-table"><thead><tr><th scope="col">Course</th><th scope="col">Credits</th><th scope="col">Minimum grade</th><th scope="col">Your progress</th><th scope="col">Grade</th></tr></thead><tbody><?php
    foreach ($options as $option) {
        $code=$option['code'];$version=$option['version'];$course=$service->course($code,$version);
        $minimum=$node['minimumGrade']??$program['policies']['courseMinimumGrades'][$code]??$program['policies']['minimumGrade']??$program['policies']['biologyMinimumGrade']??null;
        $records=array_values(array_filter($audit['attempts'],static fn($a)=>$a['courseCode']===$code && ($version===null || $a['catalogVersion']===$version)));
        $record=null;foreach ($records as $a) if ($a['phase']==='completed' && !in_array($a['grade'],['I','W','NR'],true)) $record=$a;
        $applied=in_array($code,$group['codes']??[],true);$state='unsatisfied';$label='Not completed';
        if ($record) {
            $meets=Transcript::meets($record,$minimum);$state=$meets===true?'satisfied':($meets===null?'review-required':'unsatisfied');
            $label=$meets===true?($applied?'Applied to this group':'Completed — not applied here'):($meets===null?'Grade/equivalency review':'Required grade not met');
        } else foreach (['in-progress','planned'] as $phase) {foreach ($records as $a) if ($a['phase']===$phase) {$record=$a;$state=$phase==='planned'?'planned':'conditional';$label=$phase==='planned'?'Planned':'In progress';break;}if ($record) break;}
        ?><tr><td><strong><?=\e($code)?></strong> <?=\e($course['title'])?><?php if ($version && $version!=='2026-2027'):?><br><small><?=\e($version)?> course version</small><?php endif;?></td><td><?=\e($course['credits']??'Needs review')?></td><td><?=\e($minimum??'Passing grade')?></td><td><?php audit_badge($state,$label);?><?php if ($record):?><br><small><?=\e(DegreeAuditService::termName($record['termOrder']))?></small><?php endif;?></td><td><?=\e($record && $record['phase']==='completed'?($record['grade']??'—'):'—')?></td></tr><?php
    }
    ?></tbody></table></div><?php
}
function audit_render_node(array $node,array $group,array $program,array $audit,DegreeAuditService $service,?string $concentration=null,int $depth=0): void
{
    ?><p><?=\e(audit_requirement_text($node,$concentration))?></p><?php
    if ($node['type']==='reviewed-evidence') {
        ?><p class="audit-muted"><?=\e($group['reason']??'A recorded academic review is needed before this requirement can be marked complete.')?></p><?php return;
    }
    $options=audit_options($node,$program,$service);
    if ($options) audit_course_table($options,$node,$group,$program,$audit,$service);
    $selectors=$node['type']==='choose-by-selector'?[$node]:($node['selectors']??[]);
    foreach ($selectors as $selector) {
        $subjects=implode('/',(array)($selector['subject']??[]));$level=isset($selector['levels'])?implode('/',array_map(static fn($l)=>(string)$l,$selector['levels'])):($selector['minimumLevel']??'approved');
        ?><p class="audit-muted">Eligible selection: <?=\e($subjects)?>, <?=\e($level)?> level<?=isset($selector['classification'])?' · '.\e($selector['classification']):''?>.<?=!empty($selector['excludeCourses'])?' Excludes '.\e(implode(', ',$selector['excludeCourses'])).'.':''?></p><?php
        $available=[];foreach ($audit['attempts'] as $a) {
            preg_match('/^([A-Z]+)(\d+)/',$a['courseCode'],$m);$n=(int)$m[2];
            if (!in_array($m[1],(array)($selector['subject']??[]),true) || in_array($a['courseCode'],$selector['excludeCourses']??[],true)) continue;
            if (isset($selector['levels']) && !in_array((int)(floor($n/1000)*1000),$selector['levels'],true)) continue;
            if (isset($selector['minimumLevel']) && $n<$selector['minimumLevel']) continue;
            if (isset($selector['classification']) && !in_array($selector['classification'],$a['classifications']??[],true)) continue;
            $available[$a['courseCode']]=['code'=>$a['courseCode'],'version'=>$a['catalogVersion']];
        }
        if ($available) audit_course_table(array_values($available),$node,$group,$program,$audit,$service);
        $catalogOptions=$service->eligibleOptions($selector);
        if ($catalogOptions) {
            ?><details class="audit-nested"><summary>View eligible catalog options</summary><?php audit_course_table($catalogOptions,$node,$group,$program,$audit,$service);?></details><?php
        }
    }
    $children=$node['groups']??[];
    if ($node['type']==='selected-concentration') $children=$program['concentrations'][$concentration??'']['groups']??[];
    if ($node['type']==='choose-one-path') foreach ($node['paths'] as $name=>$path) {
        ?><details class="audit-nested"><summary><?=\e($name)?><?=($group['path']??null)===$name?' — evaluated path':''?></summary><?php audit_render_node($path,($group['path']??null)===$name?$group:[],$program,$audit,$service,$concentration,$depth+1);?></details><?php
    }
    foreach ($children as $i=>$child) {
        ?><details class="audit-nested"<?=($group['children'][$i]['status']??'')!=='satisfied'?' open':''?>><summary><?=\e(($i+1).'. '.audit_requirement_title($child))?></summary><?php audit_render_node($child,$group['children'][$i]??[],$program,$audit,$service,$concentration,$depth+1);?></details><?php
    }
    if ($node['type']==='choose-distinct-domain-courses') foreach ($node['domains'] as $name=>$codes) {
        ?><details class="audit-nested"><summary><?=\e(ucfirst($name))?></summary><?php audit_course_table(array_map(static fn($c)=>['code'=>$c,'version'=>null],$codes),$node,$group,$program,$audit,$service);?></details><?php
    }
}
function audit_check_label(string $key): string
{
    return ['total-credits'=>'Total earned credits','institutional-residency'=>'Credits earned at Northbridge','upper-division'=>'Upper-division credits','liberal-arts'=>'Liberal-arts credits','transfer-limit'=>'Transfer credit limit','institutional-gpa'=>'Cumulative GPA','upper-major'=>'Upper-division major credits','transfer-major-residency'=>'Major residency for transfer students','writing'=>'Writing proficiency','math-proficiency'=>'Mathematics proficiency','general-education'=>'General education','outside-major'=>'Minor credits outside the major','major-overlap'=>'Major/minor overlap','majorCredits'=>'Major credits','minimumCredits'=>'Program credits','practice-hours'=>'Applied practice hours'][$key]??ucfirst(str_replace(['-','_'],' ',preg_replace('/([a-z])([A-Z])/','$1 $2',$key)));
}
function audit_prerequisite_text(array $rule): string
{
    $type=$rule['type']??'';
    if ($type==='and'||$type==='or') return '('.implode($type==='and'?' AND ':' OR ',array_map(__NAMESPACE__.'\\audit_prerequisite_text',$rule['rules'])).')';
    if ($type==='course'||$type==='approved-transfer-course') return ($type==='approved-transfer-course'?'approved transfer equivalent of ':'').$rule['courseCode'].(isset($rule['minimumGrade'])?' with '.$rule['minimumGrade'].' or higher':'');
    if (in_array($type,['corequisite-condition','completed-or-concurrent-condition'],true)) return 'before or alongside: '.audit_prerequisite_text($rule['rule']);
    if ($type==='completed-writing-proficiency') return 'completed Composition I and II with C or higher';
    if ($type==='standing-in-program') return $rule['standing'].' standing or higher in '.$rule['program'];
    if ($type==='minimum-earned-credits') return $rule['minimum'].' earned credits';
    if ($type==='earned-subject-credits') return $rule['minimum'].' earned '.$rule['subject'].' credits';
    if ($type==='minimum-institutional-gpa') return 'cumulative GPA of '.$rule['minimum'].' or higher';
    if ($type==='approval') return 'permission from the '.str_replace('-',' ',$rule['authority']);
    if ($type==='proficiency'||$type==='completed-or-concurrent-proficiency') return ['ECI'=>'Composition I proficiency','ECII'=>'Composition II proficiency','MATH_PROFICIENCY'=>'mathematics proficiency'][$rule['key']]??'reviewed writing proficiency';
    if ($type==='scoped-evidence' && ($rule['scope']??null)==='placement:MA1020-entry') return 'approved mathematics entry placement';
    if ($type==='completed-laboratory-science') return 'completed laboratory science';
    if ($type==='no-course-prerequisites') return 'no course prerequisites listed';
    if ($type==='subject-course-count') return $rule['minimumCourses'].' qualifying '.$rule['subject'].' course(s)';
    return 'recorded academic approval or source review';
}
function audit_render(array $audit,DegreeAuditService $service): void
{
    if ($audit['availability']!=='available') {
        ?><p class="audit-notice"><?=\e($audit['availability']==='graduate-not-configured'?'Degree Audit currently supports undergraduate programs. Graduate audit requirements have not been configured.':'This undergraduate record needs a catalog/program review before an audit can be calculated.')?></p><?php return;
    }
    $person=$audit['person'];$name=implode(' ',array_filter([$person['first_Name'],$person['middle_Name'],$person['last_Name']]));$primary=$audit['majors'][0];$evaluation=$primary['evaluation'];$totals=$evaluation['totals']??$evaluation['bestPartialAudit']['totals']??[];
    $allReady=true;foreach ($audit['majors'] as $major) if (!($major['evaluation']['graduationReady']??false)) $allReady=false;
    foreach ($audit['minors'] as $minor) if (!($minor['evaluation']['programComplete']??false)) $allReady=false;
    ?><div class="degree-audit" data-audit-student="<?=\e($person['student_ID'])?>">
    <header class="audit-heading"><div><p class="audit-eyebrow">2026–2027 catalog · As of <?=\e($audit['asOf'])?></p><h2><?=\e($name)?></h2><p><?=\e($primary['name'])?><?=!empty($primary['concentration'])?' · '.\e($primary['concentration']):''?></p></div><?php audit_badge($allReady?'satisfied':'unsatisfied',$allReady?'Degree requirements met':'Degree requirements remaining');?></header>
    <p class="audit-notice">Completed, passing work counts toward this audit. In-progress and planned courses are shown separately and do not count as earned credits. An incomplete audit is expected while you are studying.</p>
    <div class="audit-metrics"><div><span>Earned degree credits</span><strong><?=\e($audit['earnedCredits'])?> / <?=\e($primary['program']['degreeRequirements']['minimumTotalCredits']??'—')?></strong></div><div><span>In progress</span><strong><?=\e($audit['inProgressCredits'])?> credits</strong></div><div><span>Planned</span><strong><?=\e($audit['plannedCredits'])?> credits</strong></div><div><span>Cumulative GPA</span><strong><?=isset($totals['institutionalGPA'])?\e(number_format($totals['institutionalGPA'],2)):'—'?></strong></div></div>
    <?php foreach ($audit['progression'] as $notice):?><section class="audit-advising"><h3>Check your progress into <?=\e($notice['major'])?></h3><p>Your current schedule has no required core course for this major. Prerequisite or foundation courses may still be needed. Review these remaining required courses with your advisor.</p><ul><?php foreach ($notice['courses'] as $c):?><li><strong><?=\e($c['courseCode'])?> — <?=\e($c['title'])?></strong>: <?=\e(match($c['prerequisite']['status']){'satisfied'=>'prerequisites met','conditional'=>'prerequisites depend on pending grades or concurrent enrollment','unsatisfied'=>'prerequisites not yet met',default=>'prerequisites need academic review'})?> for <?=\e($notice['targetTerm'])?>.<br><small>Prerequisites: <?=\e(audit_prerequisite_text($c['rule']))?>.</small></li><?php endforeach;?></ul><small>This checks prerequisites; it does not confirm that a section is offered or register you for a course.</small></section><?php endforeach;?>
    <?php foreach (array_merge($audit['majors'],$audit['minors']) as $index=>$item):$program=$item['program'];$result=$item['evaluation'];$groups=$result['groups']??$result['bestPartialAudit']['groups']??[];?>
    <section class="audit-program"><header class="audit-program-heading"><h2><?=\e($item['name'])?></h2><?php audit_badge($result['programStatus']??$result['status']);?></header>
    <?php if (!$program):?><p>Program requirements need an approved catalog definition.</p><?php else:foreach ($program['groups'] as $i=>$node):$group=$groups[$i]??['status'=>'review-required','codes'=>[]];?>
    <details class="audit-group"<?=($group['status']??'')!=='satisfied'?' open':''?>><summary><span><?=\e(($i+1).'. '.audit_requirement_title($node))?></span><?php audit_badge($group['status']??'review-required');?></summary><div class="audit-group-body"><?php audit_render_node($node,$group,$program,$audit,$service,$item['concentration']);?></div></details>
    <?php endforeach;?><details class="audit-group"><summary><span><?=($program['kind']??'')==='major'?'College and program checks':'Minor credit, grade and overlap checks'?></span></summary><div class="audit-group-body audit-check-list"><?php foreach ($result['checks']??$result['bestPartialAudit']['checks']??[] as $key=>$check):if (str_starts_with($key,'group-')) continue;?><section><div><h3><?=\e(audit_check_label($key))?></h3><p><?=\e($check['reason'])?></p></div><?php audit_badge($check['status']);?></section><?php endforeach;?></div></details><?php endif;?></section>
    <?php endforeach;?>
    <?php if (!$audit['minors']):?><p class="audit-muted">No minor is currently declared.</p><?php endif;?>
    <section class="audit-program"><h2>General education domains</h2><p>Approved overlaps can satisfy multiple domains, but course credits are counted once. The separate general education check also verifies the minimum distinct credits.</p><div class="audit-table-scroll"><table><thead><tr><th scope="col">Domain</th><th scope="col">Progress</th><th scope="col">Your courses</th></tr></thead><tbody><?php foreach ($audit['generalEducation'] as $domain):$phase=$domain['completed']?'completed':($domain['in-progress']?'in-progress':($domain['planned']?'planned':null));?><tr><td><?=\e($domain['name'])?></td><td><?php audit_badge($phase==='completed'?'satisfied':($phase==='in-progress'?'conditional':($phase==='planned'?'planned':'unsatisfied')));?></td><td><?=\e($phase?implode(', ',$domain[$phase==='completed'?'completed':$phase]):'No qualifying course completed')?></td></tr><?php endforeach;?></tbody></table></div></section>
    <section class="audit-program"><h2>Courses on your record</h2><div class="audit-table-scroll"><table><thead><tr><th scope="col">Course</th><th scope="col">Semester</th><th scope="col">Credits</th><th scope="col">Grade</th><th scope="col">Status</th></tr></thead><tbody><?php foreach ($audit['attempts'] as $a):?><tr><td><strong><?=\e($a['courseCode'])?></strong> <?=\e($a['title'])?></td><td><?=\e(DegreeAuditService::termName($a['termOrder']))?></td><td><?=\e($a['credits'])?></td><td><?=\e($a['phase']==='completed'?($a['grade']??'—'):'—')?></td><td><?=\e(['completed'=>'Completed','in-progress'=>'In progress','planned'=>'Planned'][$a['phase']])?></td></tr><?php endforeach;?></tbody></table></div></section>
    <p class="audit-muted">This read-only audit reflects the recorded catalog, grades and approvals. “Needs review” identifies evidence or a decision that an advisor or registrar must confirm. Viewing an audit does not change your schedule, grades or declared programs.</p></div><?php
}
