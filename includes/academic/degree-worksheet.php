<?php
declare(strict_types=1);
namespace Northbridge\Academic;
require_once __DIR__.'/degree-audit-view.php';

function worksheet_icon(string $state,string $label=''): void
{
    $label=$label?:audit_label($state);
    $symbol=['satisfied'=>'✓','unsatisfied'=>'○','conditional'=>'◐','planned'=>'◐','review-required'=>'!'][$state]??'!';
    ?><span class="ws-icon ws-<?=\e($state)?>" role="img" aria-label="<?=\e($label)?>" title="<?=\e($label)?>"><?=\e($symbol)?></span><?php
}
function worksheet_status(string $state): void
{
    ?><span class="ws-status ws-<?=\e($state)?>"><?=\e(['satisfied'=>'Complete','conditional'=>'In progress','planned'=>'Preregistered','unsatisfied'=>'Not complete','review-required'=>'Needs review'][$state]??'Needs review')?></span><?php
}
function worksheet_course_link(string $code,?string $version,DegreeAuditService $service,array &$dialogs): void
{
    $id='ws-course-'.substr(hash('sha256',$code.'|'.($version??'2026-2027')),0,16);
    $dialogs[$id]=$service->courseDetails($code,$version);
    ?><button type="button" class="ws-course-link" data-course-dialog="<?=\e($id)?>"><?=\e(preg_replace('/^([A-Z]+)(\d+)/','$1 $2',$code))?></button><?php
}
function worksheet_record(array $audit,string $code,?string $version=null,?array $program=null): ?array
{
    $rows=array_values(array_filter($audit['attempts'],static fn($a)=>$a['courseCode']===$code && ($version===null || $a['catalogVersion']===$version)));
    $completed=null;
    foreach ($rows as $a) if ($a['phase']==='completed' && !in_array($a['grade'],['I','W','NR'],true)) {
        if (!isset($program['policies']['departmentalGradeRule']) || !$completed || (Transcript::POINTS[$a['grade']]??-1)>(Transcript::POINTS[$completed['grade']]??-1)) $completed=$a;
    }
    if ($completed) return $completed;
    foreach (['in-progress','planned'] as $phase) foreach ($rows as $a) if ($a['phase']===$phase) return $a;
    return $rows?end($rows):null;
}
function worksheet_course_rows(array $options,array $node,array $group,array $program,array $audit,DegreeAuditService $service,array &$dialogs): void
{
    foreach ($options as $option) {
        $code=$option['code'];$version=$option['version'];$course=$service->courseDetails($code,$version);$a=worksheet_record($audit,$code,$version,$program);
        $minimum=$node['minimumGrade']??$program['policies']['courseMinimumGrades'][$code]??$program['policies']['minimumGrade']??$program['policies']['biologyMinimumGrade']??null;
        $applied=in_array($code,$group['codes']??[],true);$state='unsatisfied';
        if ($a) $state=$a['phase']==='completed'?($applied || Transcript::meets($a,$minimum)===true?'satisfied':(Transcript::meets($a,$minimum)===null?'review-required':'unsatisfied')):($a['phase']==='planned'?'planned':'conditional');
        $registered=(bool)array_filter($audit['attempts'],static fn($r)=>$r['courseCode']===$code && ($version===null||$r['catalogVersion']===$version) && in_array($r['phase'],['in-progress','planned'],true));
        ?><tr><td class="ws-icon-cell"><?php worksheet_icon($state);?></td><td class="ws-code"><?php worksheet_course_link($code,$version,$service,$dialogs);?></td><td><?=\e($course['title'])?><?php if ($version && $version!=='2026-2027'):?><small class="ws-prereq"><?=\e($version)?> version</small><?php endif;?><?php if (!$registered && $state!=='satisfied'):?><small class="ws-prereq">Prerequisites: <?=\e(audit_prerequisite_text($course['prerequisiteRule']))?></small><?php endif;?><?php if ($minimum && $minimum!==($program['policies']['minimumGrade']??null)):?><small class="ws-prereq">Minimum grade: <?=\e($minimum)?></small><?php endif;?></td><td class="ws-grade"><?=\e($a?($a['phase']==='completed'?($a['grade']??'—'):($a['phase']==='planned'?'PR':'IP')):'—')?></td><td class="ws-credit"><?=\e($a && $a['phase']!=='completed'?'('.$a['credits'].')':($a['credits']??$course['credits']??'—'))?></td><td class="ws-term"><?=\e($a?DegreeAuditService::termName($a['termOrder']):'—')?></td></tr><?php
    }
}
function worksheet_table_head(): void
{
    ?><thead><tr><th scope="col" class="ws-icon-cell"><span class="ws-sr-only">Status</span></th><th scope="col">Course</th><th scope="col">Title</th><th scope="col">Grade</th><th scope="col">Credits</th><th scope="col">Term</th></tr></thead><?php
}
function worksheet_node(array $node,array $group,array $program,array $audit,DegreeAuditService $service,array &$dialogs,?string $concentration=null): void
{
    if ($node['type']==='reviewed-evidence') {
        ?><div class="ws-check-row"><?php worksheet_icon($group['status']??'review-required');?><div><?=\e(audit_requirement_text($node,$concentration))?></div></div><?php return;
    }
    $options=audit_options($node,$program,$service);
    if ($options) {
        if (!in_array($node['type'],['all-courses','all-course-versions'],true)):?><p class="ws-choice-text"><?=\e(audit_requirement_text($node,$concentration))?></p><?php endif;
        ?><div class="ws-table-scroll"><table class="ws-table"><?php worksheet_table_head();?><tbody><?php worksheet_course_rows($options,$node,$group,$program,$audit,$service,$dialogs);?></tbody></table></div><?php
    }
    foreach ($node['type']==='choose-by-selector'?[$node]:($node['selectors']??[]) as $selector) {
        $options=$service->eligibleOptions($selector);$records=[];
        foreach ($options as $o) if (worksheet_record($audit,$o['code'],$o['version'])) $records[]=$o;
        ?><p class="ws-choice-text"><?=\e(audit_requirement_text($node,$concentration))?></p><?php
        if ($records):?><div class="ws-table-scroll"><table class="ws-table"><?php worksheet_table_head();?><tbody><?php worksheet_course_rows($records,$node,$group,$program,$audit,$service,$dialogs);?></tbody></table></div><?php endif;
        if (($group['status']??'')!=='satisfied' && $options):?><details class="ws-options"><summary>Eligible course options</summary><div class="ws-table-scroll"><table class="ws-table"><?php worksheet_table_head();?><tbody><?php worksheet_course_rows($options,$node,$group,$program,$audit,$service,$dialogs);?></tbody></table></div></details><?php endif;
    }
    $children=$node['groups']??[];
    if ($node['type']==='selected-concentration') {
        ?><h4 class="ws-subheading"><?=\e($concentration??'Concentration needs advisor review')?></h4><?php
        $children=$program['concentrations'][$concentration??'']['groups']??[];
    }
    if ($node['type']==='choose-one-path') {
        ?><p class="ws-choice-text">Complete one path.</p><?php
        foreach ($node['paths'] as $name=>$path):?><details class="ws-options"<?=($group['path']??null)===$name?' open':''?>><summary><?=\e($name)?></summary><?php worksheet_node($path,($group['path']??null)===$name?$group:[],$program,$audit,$service,$dialogs,$concentration);?></details><?php endforeach;
    }
    foreach ($children as $i=>$child) {worksheet_node($child,$group['children'][$i]??[],$program,$audit,$service,$dialogs,$concentration);}
    if ($node['type']==='choose-distinct-domain-courses') foreach ($node['domains'] as $name=>$codes):?><h4 class="ws-subheading"><?=\e(ucfirst($name))?></h4><div class="ws-table-scroll"><table class="ws-table"><?php worksheet_table_head();?><tbody><?php worksheet_course_rows(array_map(static fn($c)=>['code'=>$c,'version'=>null],$codes),$node,$group,$program,$audit,$service,$dialogs);?></tbody></table></div><?php endforeach;
}
function worksheet_block_start(string $id,string $title,string $state,array $meta=[]): void
{
    ?><details class="audit-block" id="<?=\e($id)?>" open><summary><span><?=\e($title)?></span><?php worksheet_status($state);?><span class="ws-chevron" aria-hidden="true"></span></summary><?php if ($meta):?><div class="ws-meta"><?php foreach ($meta as $label=>$value):?><span><?=\e($label)?>: <strong><?=\e($value)?></strong></span><?php endforeach;?></div><?php endif;?><div class="ws-block-body"><?php
}
function worksheet_attempt_table(array $rows,array $audit,DegreeAuditService $service,array &$dialogs): void
{
    ?><div class="ws-table-scroll"><table class="ws-table"><?php worksheet_table_head();?><tbody><?php foreach ($rows as $a):$state=$a['phase']==='completed'?(Transcript::meets($a,null)===true?'satisfied':'unsatisfied'):($a['phase']==='planned'?'planned':'conditional');?><tr><td class="ws-icon-cell"><?php worksheet_icon($state);?></td><td class="ws-code"><?php worksheet_course_link($a['courseCode'],$a['catalogVersion'],$service,$dialogs);?></td><td><?=\e($a['title'])?><?php $repeat=count(array_filter($audit['attempts'],static fn($r)=>$r['courseCode']===$a['courseCode']&&$r['catalogVersion']===$a['catalogVersion']));if ($repeat>1):?> <small>(R)</small><?php endif;?></td><td><?=\e($a['phase']==='completed'?($a['grade']??'—'):($a['phase']==='planned'?'PR':'IP'))?></td><td><?=\e($a['phase']==='completed'?(Transcript::meets($a,null)===true?$a['credits']:0):'('.$a['credits'].')')?></td><td class="ws-term"><?=\e(DegreeAuditService::termName($a['termOrder']))?></td></tr><?php endforeach;?></tbody></table></div><?php
}
function worksheet_render(array $audit,DegreeAuditService $service): void
{
    if ($audit['availability']!=='available') {audit_render($audit,$service);return;}
    $attributeDialogs=[];$dialogs=[];$p=$audit['person'];$primary=$audit['majors'][0];$result=$primary['evaluation'];$totals=$result['totals']??$result['bestPartialAudit']['totals']??[];$checks=$result['checks']??$result['bestPartialAudit']['checks']??[];
    $name=implode(' ',array_filter([$p['first_Name'],$p['middle_Name'],$p['last_Name']]));$required=$primary['program']['degreeRequirements']['minimumTotalCredits']??120;
    $standing=[1=>'Freshman',2=>'Sophomore',3=>'Junior',4=>'Senior'][$p['student_Year']]??'Undergraduate';
    $degree=str_contains($primary['name'],', B.A.')?'Bachelor of Arts':(str_contains($primary['name'],', B.S.')?'Bachelor of Science':'Undergraduate degree');
    $allReady=!array_filter($audit['majors'],static fn($m)=>!($m['evaluation']['graduationReady']??false))&&!array_filter($audit['minors'],static fn($m)=>!($m['evaluation']['programComplete']??false));
    $pending=$audit['inProgressCredits']+$audit['plannedCredits']>0;$overall=$allReady?'satisfied':($pending?'conditional':'unsatisfied');
    ?><div class="degree-worksheet" data-audit-student="<?=\e($p['student_ID'])?>"><section class="ws-student-card" aria-label="Student summary"><div class="ws-identity"><div><span>Student ID</span><strong><?=\e($p['student_ID'])?></strong></div><div><span>Name</span><strong><?=\e($name)?></strong></div><div><span>Degree</span><strong><?=\e($degree)?></strong></div></div><dl class="ws-facts"><div><dt>Level</dt><dd>Undergraduate</dd></div><div><dt>Classification</dt><dd><?=\e($standing)?></dd></div><div><dt>Major</dt><dd><?=\e($primary['name'])?></dd></div><?php if ($audit['minors']):?><div><dt>Minor</dt><dd><?=\e(implode('; ',array_column($audit['minors'],'name')))?></dd></div><?php endif;?><div><dt>Catalog</dt><dd>2026–2027</dd></div><div><dt>Earned credits</dt><dd><?=\e($audit['earnedCredits'])?></dd></div><div><dt>In progress</dt><dd><?=\e($audit['inProgressCredits'])?></dd></div><div><dt>Preregistered</dt><dd><?=\e($audit['plannedCredits'])?></dd></div><div><dt>GPA</dt><dd><?=isset($totals['institutionalGPA'])?\e(number_format($totals['institutionalGPA'],2)):'—'?></dd></div></dl></section><div class="ws-toolbar"><small>Audit date <?=\e($audit['asOf'])?></small><button type="button" class="ws-toggle" data-audit-toggle>Collapse all</button></div><?php
    worksheet_block_start('ws-degree','Degree in '.$degree,$overall,['Credits required'=>$required,'Credits earned'=>$audit['earnedCredits'],'In progress'=>$audit['inProgressCredits'],'Preregistered'=>$audit['plannedCredits']]);
    $gpa=$totals['institutionalGPA']??null;
    ?><div class="ws-check-row"><?php worksheet_icon($gpa===null?'unsatisfied':($checks['institutional-gpa']['status']??'review-required'));?><span><?=\e($gpa===null?'Minimum GPA — not yet available':(($checks['institutional-gpa']['status']??'')==='satisfied'?'You meet the minimum GPA requirement':'Minimum GPA requirement not met'))?></span></div><?php
    $proficiencyState=Result::combine(array_values($audit['entryProficiency']))['status'];
    $geState=$checks['general-education']['status']??'review-required';
    // Pending domain coursework is progress, not an earned completion or an error.
    if ($geState!=='satisfied') {
        $hasCurrent=(bool)array_filter($audit['generalEducation'],static fn($d)=>!empty($d['in-progress']));
        $hasPlanned=(bool)array_filter($audit['generalEducation'],static fn($d)=>!empty($d['planned']));
        if ($hasCurrent) $geState='conditional';
        elseif ($hasPlanned) $geState='planned';
    }
    $links=[['ws-proficiency','Northbridge Proficiency Requirements',$proficiencyState],['ws-ge','General Education Requirements',$geState],['ws-major-0','Major Requirements',$result['programStatus']??$result['status']],['ws-liberal','Liberal Arts Requirement',$checks['liberal-arts']['status']??'review-required'],['ws-upper','Upper Division Requirement',$checks['upper-division']['status']??'review-required']];
    foreach ($audit['minors'] as $i=>$minor) array_splice($links,3+$i,0,[['ws-major-'.(count($audit['majors'])+$i),'Minor Requirements: '.$minor['name'],$minor['evaluation']['programStatus']??$minor['evaluation']['status']]]);
    foreach ($links as [$anchor,$title,$state]):?><div class="ws-check-row"><?php worksheet_icon($state);?><a href="#<?=\e($anchor)?>"><?=\e($title)?></a></div><?php endforeach;?>
    <details class="ws-options"><summary>Additional degree requirements</summary><?php
    foreach (['total-credits','institutional-residency','transfer-limit'] as $key) if (isset($checks[$key])):
        $label=audit_check_label($key);$detail=$checks[$key]['reason'];
        if ($key==='transfer-limit') {
            $label=match($checks[$key]['status']) {'satisfied'=>'Within transfer credit limit','unsatisfied'=>'Transfer credit limit exceeded',default=>'Transfer credit limit needs review'};
            $detail=($totals['transferCredits']??0).' accepted · Maximum '.($primary['program']['degreeRequirements']['maximumTransferCredits']??80);
        }
        ?><div class="ws-check-row"><?php worksheet_icon($checks[$key]['status']);?><span><?=\e($label)?></span><small><?=\e($detail)?></small></div><?php endif;?></details></div></details><?php
    $proficiencyState=Result::combine(array_values($audit['entryProficiency']))['status'];
    worksheet_block_start('ws-proficiency','Northbridge Proficiency Requirements',$proficiencyState);
    foreach (['writing'=>'Writing proficiency','mathematics'=>'Mathematics proficiency'] as $key=>$label):$c=$audit['entryProficiency'][$key];?><div class="ws-check-row"><?php worksheet_icon($c['status']);?><span><?=\e($label)?></span><small>Entry placement satisfied.</small></div><?php endforeach;?></div></details><?php
    ?><details class="ws-options"><summary>College writing and mathematics course requirements</summary><?php
    foreach (['writing'=>'Composition I and II','math-proficiency'=>'College mathematics'] as $key=>$label) if (isset($checks[$key])):?><div class="ws-check-row"><?php worksheet_icon($checks[$key]['status']);?><span><?=\e($label)?></span><small><?=\e($key==='writing'?'Complete Composition I and II with C or higher, or approved equivalents.':'Complete a qualifying mathematics course with C or higher, or an approved equivalent.')?></small></div><?php endif;?></details><?php
    worksheet_block_start('ws-ge','General Education Requirements',$geState);
    ?><div class="ws-table-scroll"><table class="ws-table ws-domain-table"><thead><tr><th scope="col"><span class="ws-sr-only">Status</span></th><th scope="col">Requirement</th><th scope="col">Course / Title</th><th scope="col">Grade</th><th scope="col">Credits</th><th scope="col">Term</th></tr></thead><tbody><?php
    foreach ($audit['generalEducation'] as $domain) {
        $phase=$domain['completed']?'completed':($domain['in-progress']?'in-progress':($domain['planned']?'planned':null));$codes=$phase?$domain[$phase]:[];$state=$phase==='completed'?'satisfied':($phase==='in-progress'?'conditional':($phase==='planned'?'planned':'unsatisfied'));
        $a=$codes?worksheet_record($audit,reset($codes)):null;
        ?><tr><td class="ws-icon-cell"><?php worksheet_icon($state);?></td><td><?=\e($domain['name'])?></td><td><?php if ($a):worksheet_course_link($a['courseCode'],$a['catalogVersion'],$service,$dialogs);?> <span><?=\e($a['title'])?></span><?php else:?>Still need 4 credits in <?php
            $attributeId='ws-attribute-'.substr(hash('sha256',$domain['name']),0,12);
            $eligible=$service->domainCourses($domain['name']);
            $attributeDialogs[$attributeId]=['domain'=>$domain,'courses'=>$eligible];
            ?><a class="ws-course-link" href="#<?=\e($attributeId)?>" data-course-dialog="<?=\e($attributeId)?>"><?=\e($domain['attributeCode']??$domain['name'])?></a> — <?php foreach ($eligible as $i=>$code):if ($i) echo ', ';worksheet_course_link($code,null,$service,$dialogs);endforeach;?>.<?php endif;?></td><td><?=\e($a?($a['phase']==='completed'?$a['grade']:($a['phase']==='planned'?'PR':'IP')):'—')?></td><td><?=\e($a?($a['phase']==='completed'?$a['credits']:'('.$a['credits'].')'):'—')?></td><td class="ws-term"><?=\e($a?DegreeAuditService::termName($a['termOrder']):'—')?></td></tr><?php
    }
    ?></tbody></table></div></div></details><?php
    $used=[];
    foreach (array_merge($audit['majors'],$audit['minors']) as $index=>$item) {
        $program=$item['program'];$evaluation=$item['evaluation'];$groups=$evaluation['groups']??$evaluation['bestPartialAudit']['groups']??[];
        foreach ($evaluation['selectedCourses']??[] as $code) $used[$code]=true;
        $state=$evaluation['programStatus']??$evaluation['status'];if ($state==='unsatisfied' && $pending) $state='conditional';
        worksheet_block_start('ws-major-'.$index,(($program['kind']??'')==='minor'?'Minor in ':'Major in ').preg_replace('/, (B\.[AS]\.|Minor)$/','',$item['name']),$state,['Catalog year'=>'2026–2027']);
        if (!$program):?><p>Approved requirements need academic review.</p><?php else:
        if (isset($program['policies']['minimumGrade'])):?><p class="ws-choice-text">A grade of <?=\e($program['policies']['minimumGrade'])?> or higher is required in the courses counted toward this program.</p><?php endif;
        foreach ($program['groups'] as $i=>$node):$group=$groups[$i]??['status'=>'review-required','codes'=>[]];?><h3 class="ws-subheading"><?php worksheet_icon($group['status']??'review-required');?> <?=\e(audit_requirement_title($node))?></h3><?php worksheet_node($node,$group,$program,$audit,$service,$dialogs,$item['concentration']);endforeach;
        $additional=array_filter($evaluation['checks']??[],static fn($k)=>!str_starts_with($k,'group-')&&!in_array($k,['total-credits','institutional-gpa','institutional-residency','transfer-limit','liberal-arts','upper-division','writing','math-proficiency','general-education'],true),ARRAY_FILTER_USE_KEY);
        if ($additional):?><details class="ws-options"><summary>Additional program requirements</summary><?php foreach ($additional as $key=>$check):?><div class="ws-check-row"><?php worksheet_icon($check['status']);?><span><?=\e(audit_check_label($key))?></span><small><?=\e($check['reason'])?></small></div><?php endforeach;?></details><?php endif;
        endif;?></div></details><?php
    }
    foreach (['liberal-arts'=>['Liberal Arts Requirement','liberalArts','minimumLiberalArtsCredits','liberalArtsCredits'],'upper-division'=>['Upper Division Requirement','upperDivision','minimumUpperDivisionCredits','upperDivisionCredits']] as $key=>[$title,$flag,$minimumKey,$totalKey]) {
        $latest=(new Transcript(array_values(array_filter($audit['attempts'],static fn($a)=>$a['phase']==='completed'))))->latest();
        $rows=array_values(array_filter($latest,static fn($a)=>Transcript::meets($a,null)===true && ($a[$flag]??false) && Evidence::approved($a['attributeApproval']??null,'attributes:'.$a['courseCode'])));
        worksheet_block_start($key==='liberal-arts'?'ws-liberal':'ws-upper',$title,$checks[$key]['status']??'review-required',['Credits required'=>$primary['program']['degreeRequirements'][$minimumKey]??'—','Credits applied'=>$totals[$totalKey]??0]);
        if ($rows): worksheet_attempt_table($rows,$audit,$service,$dialogs);else:?><p class="ws-empty">No qualifying completed courses.</p><?php endif;?></div></details><?php
    }
    foreach ($audit['generalEducation'] as $domain) foreach ($domain['completed'] as $code) $used[$code]=true;
    $fallThrough=[];$insufficient=[];$current=[];$future=[];
    foreach ($audit['attempts'] as $a) {
        if ($a['phase']==='in-progress') $current[]=$a;
        elseif ($a['phase']==='planned') $future[]=$a;
        elseif (Transcript::meets($a,null)!==true) $insufficient[]=$a;
        elseif (!isset($used[$a['courseCode']])) $fallThrough[]=$a;
    }
    foreach ([['Fall Through',$fallThrough,'ws-fallthrough'],['Insufficient',$insufficient,'ws-insufficient'],['In-progress',$current,'ws-current'],['Preregistered',$future,'ws-planned']] as [$title,$rows,$id]) if ($rows) {
        worksheet_block_start($id,$title,$id==='ws-insufficient'?'unsatisfied':($id==='ws-current'?'conditional':($id==='ws-planned'?'planned':'satisfied')),['Credits'=>array_sum(array_map(static fn($a)=>$a['phase']==='completed'?(Transcript::meets($a,null)===true?$a['credits']:0):$a['credits'],$rows)),'Classes'=>count($rows)]);
        worksheet_attempt_table($rows,$audit,$service,$dialogs);?></div></details><?php
    }
    ?><section class="ws-legend" aria-label="Audit legend"><h3>Legend</h3><div><?php foreach (['satisfied'=>'Complete','unsatisfied'=>'Not complete','conditional'=>'In progress — qualifying courses underway; requirements remain','planned'=>'Preregistered — qualifying courses scheduled','review-required'=>'Needs advisor review'] as $state=>$label):?><span><?php worksheet_icon($state,$label);?> <?=\e($label)?></span><?php endforeach;?><span><b>(R)</b> Repeated class</span></div><p>IP = in progress · PR = preregistered · Parenthesized credits are not earned. This audit is a planning guide, not an official transcript or degree award.</p></section>
    <?php foreach ($attributeDialogs as $id=>$entry):$domain=$entry['domain'];?><dialog class="ws-course-dialog" id="<?=\e($id)?>" aria-labelledby="<?=\e($id)?>-title"><header><h2 id="<?=\e($id)?>-title"><?=\e(($domain['attributeCode']??'').' — '.$domain['name'])?></h2><button type="button" data-dialog-close aria-label="Close attribute details">×</button></header><p>Courses approved for this general education attribute. Check course prerequisites and semester availability before registering.</p><div class="ws-attribute-courses"><?php foreach ($entry['courses'] as $code):?><p><?php worksheet_course_link($code,null,$service,$dialogs);?> — <?=\e($service->course($code)['title'])?></p><?php endforeach;?></div></dialog><?php endforeach;?>
    <?php foreach ($dialogs as $id=>$course):?><dialog class="ws-course-dialog" id="<?=\e($id)?>" aria-labelledby="<?=\e($id)?>-title"><header><h2 id="<?=\e($id)?>-title"><?=\e(preg_replace('/^([A-Z]+)(\d+)/','$1 $2',$course['courseCode']))?> — <?=\e($course['title'])?></h2><button type="button" data-dialog-close aria-label="Close course details">×</button></header><p class="ws-course-meta"><?=\e($course['credits']??'Credit value needs review')?> credits · <?=\e($course['catalogVersion'])?> catalog</p><h3>Description</h3><p><?=\e($course['description'])?></p><h3>Prerequisites / corequisites</h3><p><?=\e(audit_prerequisite_text($course['prerequisiteRule']))?></p><h3>Attributes</h3><?php if ($course['attributes']):?><ul><?php foreach ($course['attributes'] as $attribute):?><li><?=\e($attribute)?></li><?php endforeach;?></ul><?php else:?><p>Verified course attributes are not yet recorded.</p><?php endif;?><?php if ($course['generalEducationDomains']):?><h3>General education</h3><ul><?php foreach ($course['generalEducationDomains'] as $domain):?><li><?=\e($domain)?></li><?php endforeach;?></ul><?php endif;?></dialog><?php endforeach;?>
    </div><script src="<?=\e(\app_url('assets/js/degree-audit.js'))?>" defer></script><?php
}
