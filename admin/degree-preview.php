<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/layout.php';
require_role(['Admin']);
require_once __DIR__.'/../includes/admin.php';
require_admin($pdo);
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)) {http_response_code(405);header('Allow: GET, HEAD');exit('This preview is read-only.');}
require_once __DIR__.'/../includes/academic/preview.php';
$error='';$preview=null;
try {
    $cases=\Northbridge\Academic\preview_cases();
    $selection=\Northbridge\Academic\preview_selection($_GET,$cases);
    $preview=\Northbridge\Academic\preview_database();
    $package=\Northbridge\Academic\preview_package($preview);$repo=$package['repository'];
    $case=$selection['case'];$program=$repo->program($case['name'],$case['kind']);
    $student=\Northbridge\Academic\preview_student($case,$selection['view']);
    $audit=(new \Northbridge\Academic\CurriculumEvaluator($repo->courseMetadata(),$repo->programs()))->evaluate($program,$student,$repo->policy('institutional-rules'));
    $q=$preview->prepare('SELECT course_code,version_key,title FROM NB_CourseVersion WHERE catalog_id=?');$q->execute([$package['catalogId']]);$versionTitles=[];$titles=[];
    foreach ($q->fetchAll() as $row) {$versionTitles[$row['version_key']]=$row['title'];if ($row['version_key']===$row['course_code']) $titles[$row['course_code']]=$row['title'];}
    $groupAudit=$audit['groups']??$audit['bestPartialAudit']['groups']??[];$checks=$audit['checks']??$audit['bestPartialAudit']['checks']??[];
    $totals=$audit['totals']??$audit['bestPartialAudit']['totals']??[];
} catch (InvalidArgumentException $e) {http_response_code(400);$error=$e->getMessage();}
catch (Throwable $e) {http_response_code(503);$error='The academic preview is temporarily unavailable. Please try again after its test package has been verified.';}
finally {if ($preview instanceof PDO && $preview->inTransaction()) $preview->rollBack();}
page_start('Degree Evaluation Preview','Admin','admin/degree-preview.php');
?>
<link rel="stylesheet" href="<?=e(app_url('assets/css/degree-preview.css'))?>">
<div class="degree-preview">
<p class="preview-notice"><strong>Synthetic student · Read-only preview</strong><br>Explore the 2026–2027 requirements. These examples do not evaluate or change an enrolled student.</p>
<?php if ($error): ?><div class="alert error" role="alert"><?=e($error)?></div><?php else:
$programOptions=[];foreach ($cases as $c) $programOptions[$c['programKey']]=['name'=>$c['name'],'kind'=>$c['kind']];
$complete=count(array_filter($groupAudit,static fn($g)=>$g['status']==='satisfied'));
$reviewCount=count(array_filter($checks,static fn($c)=>$c['status']==='review-required'));
$pendingCredits=array_sum(array_map(static fn($a)=>$a['status']==='in-progress'?$a['credits']:0,$student['attempts']));
?>
<section class="preview-controls" aria-label="Choose an academic example">
<form method="get" action="<?=e(app_url('admin/degree-preview.php'))?>">
<label for="preview-program">Program<select id="preview-program" name="program"><?php foreach (['major'=>'Majors','minor'=>'Minors'] as $kind=>$label): ?><optgroup label="<?=e($label)?>"><?php foreach ($programOptions as $key=>$p): if ($p['kind']!==$kind) continue;?><option value="<?=e($key)?>" <?=$key===$case['programKey']?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></optgroup><?php endforeach;?></select></label>
<label for="preview-view">Synthetic student example<select id="preview-view" name="view"><?php foreach (['progress'=>'In-progress example','complete'=>'Complete example','review'=>'Transfer review example'] as $key=>$label): ?><option value="<?=e($key)?>" <?=$selection['view']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<button class="btn" type="submit">View evaluation</button>
</form>
<?php if (count($selection['options'])>1): ?><form method="get" action="<?=e(app_url('admin/degree-preview.php'))?>" class="preview-path-form"><input type="hidden" name="program" value="<?=e($case['programKey'])?>"><input type="hidden" name="view" value="<?=e($selection['view'])?>"><label for="preview-path">Track or course path<select id="preview-path" name="case"><?php foreach ($selection['options'] as $c): ?><option value="<?=e($c['caseKey'])?>" <?=$c['caseKey']===$case['caseKey']?'selected':''?>><?=e($c['label'])?></option><?php endforeach;?></select></label><button class="btn light" type="submit">View path</button></form><?php endif;?>
</section>
<section class="preview-summary" aria-label="Evaluation summary">
<div><p class="preview-eyebrow">2026–2027 catalog · <?=e($program['kind'])?></p><h2><?=e($case['name'])?></h2><p><?=e($case['label'])?></p></div>
<span class="preview-status status-<?=e($audit['status'])?>"><?=e($audit['status']==='satisfied'?'Example meets program requirements':\Northbridge\Academic\preview_status($audit['status']))?></span>
</section>
<div class="preview-metrics">
<div><span>Requirement groups complete</span><strong><?=e($complete)?> / <?=e(count($program['groups']))?></strong></div>
<div><span><?=$program['kind']==='major'?'Earned degree credits':'Credits applied to minor'?></span><strong><?=e($totals['earnedCredits']??$totals['programCredits']??'—')?><?php if (isset($program['degreeRequirements']['minimumTotalCredits'])): ?> / <?=e($program['degreeRequirements']['minimumTotalCredits'])?><?php endif;?></strong></div>
<div><span>In-progress credits</span><strong><?=e($pendingCredits)?></strong></div>
<div><span>Checks needing review</span><strong><?=e($reviewCount)?></strong></div>
</div>
<?php if ($program['kind']==='minor'):?><p class="preview-note">This result covers the minor. Degree completion and major/minor overlap are evaluated separately.</p><?php endif;?>
<?php if ($selection['view']==='review'):?><p class="preview-note">One course has been changed to a transfer example without an approved equivalency. It cannot count automatically.</p><?php endif;?>
<h2 class="preview-section-title">Program requirements</h2>
<div class="preview-requirements">
<?php foreach ($program['groups'] as $i=>$node):$g=$groupAudit[$i]??['status'=>'review-required','codes'=>[]];$matched=$g['codes']??[];$options=\Northbridge\Academic\preview_option_codes($node);?>
<section class="preview-requirement"><header><h3><?=e(($i+1).'. '.\Northbridge\Academic\preview_requirement_title($node))?></h3><span class="preview-status status-<?=e($g['status'])?>"><?=e(\Northbridge\Academic\preview_status($g['status']))?></span></header>
<p><?=e(\Northbridge\Academic\preview_requirement_text($node,$case['concentration']))?></p>
<?php if (in_array($node['type'],['all-courses','all-course-versions'],true)):
$missing=[];foreach ($node['courses'] as $required) {$code=is_array($required)?$required['courseCode']:$required;if (!in_array($code,$matched,true)) $missing[]=$code.(is_array($required)?' ('.$required['catalogVersion'].')':'');}
if ($missing):?><p><strong>Still required:</strong> <?=e(implode(', ',$missing))?></p><?php endif;endif;?>
<?php if ($matched):?><p><strong>Counted courses:</strong> <?=e(implode(', ',$matched))?> <span class="preview-muted">(<?=e($g['credits']??0)?> applied credits)</span></p><?php else:?><p class="preview-muted">No qualifying courses counted in this group.</p><?php endif;?>
<?php if ($options):?><details><summary><?=$g['status']==='satisfied'?'View eligible courses':'View remaining requirements and eligible options'?></summary><ul><?php foreach ($options as $option):?><li><?=e($option)?><?=isset($titles[$option])?' — '.e($titles[$option]):''?></li><?php endforeach;?></ul><p class="preview-muted">Options are alternatives where a choice is allowed; the whole list is not required.</p></details><?php endif;?>
<?php if (!empty($node['groups']) || $node['type']==='selected-concentration' || $node['type']==='choose-one-path'):
$nested=$node['groups']??($node['type']==='selected-concentration'?($program['concentrations'][$case['concentration']]['groups']??[]):array_values($node['paths']));?>
<details><summary>View distribution or path details</summary><?php foreach ($nested as $child):?><p><?=e(\Northbridge\Academic\preview_requirement_text($child))?></p><p class="preview-muted"><?=e(implode(' · ',\Northbridge\Academic\preview_option_codes($child)))?></p><?php endforeach;?></details><?php endif;?>
</section><?php endforeach;?>
</div>
<h2 class="preview-section-title">Other academic checks</h2><div class="preview-checks"><?php foreach ($checks as $key=>$check):if (str_starts_with($key,'group-')) continue;?><div><span class="preview-status status-<?=e($check['status'])?>"><?=e(\Northbridge\Academic\preview_status($check['status']))?></span><section><h3><?=e(\Northbridge\Academic\preview_check_label($key))?></h3><p><?=e($check['reason'])?></p><?php foreach ($check['children']??[] as $child): if ($child['status']==='satisfied') continue;?><p><?=e($child['reason'])?></p><?php endforeach;?></section></div><?php endforeach;?></div>
<h2 class="preview-section-title">Synthetic course history</h2><div class="preview-table-scroll"><table class="preview-table"><thead><tr><th scope="col">Course</th><th scope="col">Credits</th><th scope="col">Grade</th><th scope="col">Course status</th></tr></thead><tbody><?php foreach ($student['attempts'] as $a):$isPending=$a['status']==='in-progress';$transfer=$a['origin']==='transfer';?><tr><td><strong><?=e($a['courseCode'])?></strong><br><span class="preview-muted"><?=e($versionTitles[$a['courseCode'].'@'.($a['catalogVersion']??'')]??$titles[$a['courseCode']]??'Reviewed historical course version')?><?php if (($a['catalogVersion']??'2026-2027')!=='2026-2027'):?> <span>(<?=e($a['catalogVersion'])?> version)</span><?php endif;?></span></td><td><?=e($a['credits'])?></td><td><?=e($isPending?'—':($a['grade']??'—'))?></td><td><?=e($isPending?'In progress':($transfer?'Transfer — equivalency needs review':'Completed'))?></td></tr><?php endforeach;?></tbody></table></div>
<p class="preview-footnote">These examples use synthetic approvals and course attributes. Missing source details and real academic approvals still require review. This page cannot register courses, approve transfers, award a degree, or change a record.</p>
<?php endif;?></div>
<?php page_end(); ?>
