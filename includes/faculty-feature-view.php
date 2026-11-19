<?php
if (!isset($mode,$title,$path,$data,$filters,$values,$error,$message)) { http_response_code(404); exit; }
require_once __DIR__.'/faculty-feature-ui.php';
?>
<link rel="stylesheet" href="<?= e(app_url('assets/css/faculty-features.css')) ?>">
<section class="faculty-features">
<header class="ff-heading"><p>FACULTY PORTAL</p><h1><?= e($title) ?></h1><span><?= in_array($mode,['password','address','attendance','grades'],true)?'Manage your account and assigned classes':'Academic information · Read only' ?></span></header>
<?php if ($message): ?><div class="ff-notice ff-success" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="ff-notice ff-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (in_array($mode,['password','address'],true)): ?>
<div class="ff-layout"><section class="ff-card"><h2><?= $mode==='password'?'Choose a new password':'Your mailing address' ?></h2>
<form method="post" action="<?= e(app_url($path)) ?>">
<input type="hidden" name="token" value="<?= e($_SESSION['faculty_form_token']) ?>">
<input type="hidden" name="version" value="<?= e($data['version']??'') ?>">
<?php if ($mode==='password'): ?>
<p>Enter your current password, then a new password between 12 and 72 bytes long.</p>
<?php faculty_input('current_password','Current password','','password',255,true); faculty_input('new_password','New password','','password',72,true); faculty_input('confirmation','Confirm new password','','password',72,true); ?>
<?php else: ?>
<p>All address fields are required.</p>
<?php faculty_input('street','Street address',$values['street']??'','text',100,true); ?>
<div class="ff-fields"><?php foreach (['city'=>['City',50],'state'=>['State / region',50],'zip_Code'=>['ZIP / postal code',10]] as $key=>[$label,$length]) faculty_input($key,$label,$values[$key]??'','text',$length,true); ?></div>
<?php endif; ?>
<div class="ff-actions"><button class="btn" type="submit"><?= $mode==='password'?'Change Password':'Save Address' ?></button><a href="<?= e(app_url('faculty/information.php')) ?>">Back to My Profile</a></div>
</form></section><aside class="ff-card ff-accent"><h2>Your account</h2><p><?= $mode==='password'?'A password change signs other sessions out when they next access the portal. Your current session stays signed in.':'Your name, user ID and university email remain managed by the university.' ?></p></aside></div>
<?php elseif ($mode==='master-schedule'): ?>
<form method="get" class="ff-card"><h2>Find course sections</h2><div class="ff-fields">
<?php faculty_select('semester_id','Semester',array_column($data['semesters']??[],'semester_Name','semester_ID'),$filters['semester_id']??'',true); faculty_select('dept_ID','Department',array_column($data['departments']??[],'dept_Name','dept_ID'),$filters['dept_ID']??''); faculty_select('level','Level',['Undergraduate'=>'Undergraduate','Graduate'=>'Graduate'],$filters['level']??''); faculty_input('q','Course name or code',$filters['q']??''); faculty_input('professor','Faculty name',$filters['professor']??''); faculty_select('day','Day',array_combine(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']),$filters['day']??''); faculty_input('start','Meeting overlaps after',$filters['start']??'','time'); faculty_input('end','Meeting overlaps before',$filters['end']??'','time'); faculty_input('building','Building name',$filters['building']??''); faculty_input('room','Room number',$filters['room']??''); ?>
</div><div class="ff-actions"><button class="btn">Search Schedule</button><a href="<?= e(app_url($path)) ?>">Clear filters</a></div></form>
<?php if (isset($data['page'])): $paging=$data['page']; ?>
<p><?= e($paging['total']) ?> matching sections · Showing <?= e($paging['start']) ?>–<?= e($paging['end']) ?></p>
<?php if (!$paging['rows']): ?><div class="ff-empty">No sections match these filters.</div><?php else: ?>
<div class="ff-scroll" tabindex="0" role="region" aria-label="Master schedule"><table><caption>Semester master schedule</caption><?php schedule_columns_header('',true); ?><tbody><?php foreach ($paging['rows'] as $section) schedule_columns_row($section,$data['meetings'][$section['CRN']]??[],null,true); ?></tbody></table></div>
<?php endif; ?>
<nav class="ff-actions" aria-label="Schedule pages"><?php if ($paging['page']>1) faculty_link($mode,array_merge($filters,['page'=>$paging['page']-1]),'Previous'); ?><span>Page <?= e($paging['page']) ?> of <?= e($paging['pages']) ?></span><?php if ($paging['page']<$paging['pages']) faculty_link($mode,array_merge($filters,['page'=>$paging['page']+1]),'Next'); ?></nav>
<?php endif; ?>
<?php elseif (in_array($mode,['attendance','grades'],true)): ?>
<section class="ff-card"><h2>Choose your course section</h2><form method="get" class="ff-select-row">
<?php $options=[]; foreach ($data['sections']??[] as $s) $options[$s['CRN']]=$s['semester_Name'].' · '.$s['course_ID'].' · Section '.$s['section_No'].' · '.$s['course_Name']; faculty_select('crn','Assigned section',$options,$data['section']['CRN']??'',true); ?><button class="btn">Load Roster</button></form></section>
<?php if (isset($data['section'],$data['marks'])): ?>
<section class="ff-card"><h2><?= e($data['section']['course_Name']) ?></h2><p><?= e($data['section']['semester_Name']) ?> · Section <?= e($data['section']['section_No']) ?><?= $mode==='attendance'?' · '.e($data['date']).' · America/New_York':'' ?></p>
<?php if ($data['closed']!==null): ?><div class="ff-notice" role="status"><?= e($data['closed']) ?></div><?php endif; ?>
<?php if (!$data['marks']): ?><p class="ff-empty">No enrolled students are available for this section.</p><?php else: ?>
<p><?= $mode==='attendance'?'Record only today’s attendance. Blank entries are left unchanged.':'Assign final grades within the approved window. Existing grades require an administrator to change them. Blank entries are left unchanged.' ?></p>
<form method="post" action="<?= e(app_url($path).'?crn='.$data['section']['CRN']) ?>"><input type="hidden" name="token" value="<?= e($_SESSION['faculty_form_token']) ?>"><input type="hidden" name="version" value="<?= e($data['version']) ?>">
<fieldset <?= $data['closed']!==null?'disabled':'' ?>><legend class="ff-sr-only"><?= e($title) ?></legend><div class="ff-scroll"><table><thead><tr><th scope="col">Student ID</th><th scope="col">Student</th><th scope="col"><?= $mode==='attendance'?'Attendance':'Final grade' ?></th></tr></thead><tbody>
<?php foreach ($data['marks'] as $student): $current=$student[$mode==='attendance'?'Present_Absent':'Grade']; ?><tr><td><?= e($student['student_ID']) ?></td><td><?= e($student['last_Name'].', '.$student['first_Name']) ?></td><td>
<?php if ($mode==='grades' && $current!==null): ?><?= e($current) ?> <small>(recorded)</small><?php else: ?>
<label class="ff-sr-only" for="mark-<?= e($student['student_ID']) ?>"><?= e($title.' for '.$student['first_Name'].' '.$student['last_Name']) ?></label><select id="mark-<?= e($student['student_ID']) ?>" name="marks[<?= e($student['student_ID']) ?>]"><option value="">Not recorded</option><?php foreach ($data['options'] as $option): ?><option value="<?= e($option) ?>" <?= $current===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select>
<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><button class="btn">Save <?= $mode==='attendance'?'Attendance':'Grades' ?></button></fieldset></form>
<?php endif; ?></section>
<?php else: ?><p class="ff-empty">Select a section to view its students and recording availability.</p><?php endif; ?>
<?php elseif (in_array($mode,['students','advisees','transcript'],true)): ?>
<?php if ($mode==='students'): ?><p>Students currently enrolled in your sections and students assigned to you for advising.</p><?php elseif ($mode==='advisees'): ?><p>Only students assigned to you as their advisor are listed.</p><?php else: ?><p>Search by student ID, first name or last name to open a read-only unofficial transcript.</p><?php endif; ?>
<form method="get" class="ff-card ff-select-row"><?php faculty_input('q','Student ID or name',$filters['q']??''); ?><button class="btn">Search</button></form>
<?php if (isset($data['person'])): ?><section class="ff-card"><h2><?= e($data['person']['first_Name'].' '.$data['person']['last_Name']) ?></h2><p>Student <?= e($data['person']['student_ID']) ?> · <?= e($data['person']['major_Name']) ?></p><p>Unofficial academic record. In-progress and upcoming courses do not award earned credit. Credits below are the course’s credit value.</p>
<?php faculty_table($data['attempts'],['semester_Name'=>'Semester','course_ID'=>'Course','course_Name'=>'Course name','course_Credits'=>'Course credits','Grade'=>'Grade','status'=>'Term status'],'Course attempts'); ?></section>
<?php else: ?>
<?php faculty_table($data['students']??[],['student_ID'=>'Student ID','first_Name'=>'First name','last_Name'=>'Last name','user_Email'=>'University email','student_Type'=>'Student type','student_Year'=>'Year','major_Name'=>'Major'],'Students',static function($s) { faculty_link('transcript',['student_id'=>$s['student_ID']],'Transcript'); echo '<a href="'.e(app_url('faculty/degree-audit.php').'?student_id='.$s['student_ID']).'">Degree audit</a>'; }); ?>
<p class="ff-help">Showing up to 100 matches. Narrow the search if needed.</p>
<?php endif; ?>
<?php elseif ($mode==='departments'): ?>
<?php $departmentRows=$data['rows']??[]; foreach ($departmentRows as &$row) { $row['chair']=trim(($row['first_Name']??'').' '.($row['last_Name']??'')); $row['location']=($row['Bldg_Name']??'Unassigned').' / '.($row['room_Number']??'—'); } unset($row); faculty_table($departmentRows,['dept_Name'=>'Department','Email'=>'Email','Phone_No'=>'Phone','chair'=>'Chair','dept_Manager'=>'Manager','location'=>'Office'],'Academic departments',static function($r){foreach (['courses'=>'Courses','majors'=>'Majors','minors'=>'Minors'] as $route=>$label) faculty_link($route,['dept_ID'=>$r['dept_ID']],$label);}); ?>
<?php elseif (in_array($mode,['courses','majors','minors'],true)): ?>
<form method="get" class="ff-card ff-select-row"><?php faculty_select('dept_ID','Department',array_column($data['departments']??[],'dept_Name','dept_ID'),$filters['dept_ID']??'',true); ?><button class="btn">View <?= e(ucfirst($mode)) ?></button></form>
<?php if (isset($data['rows'])): ?>
<?php if ($mode==='courses') faculty_table($data['rows'],['course_ID'=>'Course','course_Name'=>'Course name','course_Credits'=>'Credits','course_Type'=>'Level','course_DSC'=>'Description'],'Department courses',static fn($r)=>faculty_link('prerequisites',['course_id'=>$r['course_ID']],'Prerequisites'));
elseif ($mode==='majors') faculty_table($data['rows'],['major_Name'=>'Major'],'Department majors',static fn($r)=>faculty_link('major-requirements',['major_id'=>$r['major_ID']],'Requirements'));
else faculty_table($data['rows'],['minor_Name'=>'Minor'],'Department minors'); ?>
<?php else: ?><p class="ff-empty">Choose a department to view its <?= e($mode) ?>.</p><?php endif; ?>
<?php elseif ($mode==='prerequisites'): ?>
<form method="get" class="ff-card ff-select-row"><?php $options=[];foreach($data['courses']??[] as $c)$options[$c['course_ID']]=$c['course_ID'].' · '.$c['course_Name'];faculty_select('course_id','Course',$options,$filters['course_id']??'',true); ?><button class="btn">View Prerequisites</button></form>
<?php if (isset($data['course'])): ?><section class="ff-card"><h2><?= e($data['course']['course_ID'].' · '.$data['course']['course_Name']) ?></h2><p><?= e($data['course']['course_DSC']) ?></p>
<?php if (isset($data['rule'])): ?><h3>Reviewed prerequisite rule</h3><p><?= e(\Northbridge\Academic\audit_prerequisite_text($data['rule'])) ?></p><p class="ff-help">Permission, proficiency and review requirements remain part of this rule; displaying a course does not grant registration eligibility.</p>
<?php else: ?>
<?php faculty_table($data['rows'],['prerequisite_course_ID'=>'Prerequisite','course_Name'=>'Course name','min_Grade_Req'=>'Minimum grade'],'Recorded course prerequisites'); ?><p class="ff-help">These are the database’s recorded prerequisites. An empty list does not establish that all proficiency or permission requirements are satisfied.</p>
<?php endif; ?></section><?php endif; ?>
<?php elseif (in_array($mode,['major-requirements','major-courses'],true)): ?>
<form method="get" class="ff-card ff-select-row"><?php faculty_select('major_id','Major',array_column($data['majors']??[],'major_Name','major_ID'),$filters['major_id']??'',true); ?><button class="btn">View Requirements</button></form>
<?php if (isset($data['major'])): ?><section class="ff-card"><h2><?= e($data['major']['major_Name']) ?></h2>
<?php if (isset($data['program'])): ?><p>Reviewed <?= e($data['program']['catalogEdition']??'') ?> program requirements. Alternatives and concentrations are shown separately.</p><ul class="ff-rules">
<?php foreach (['groups'=>'Course requirements','concentrations'=>'Concentrations','policies'=>'Program policies','degreeRequirements'=>'Degree requirements','countingPolicy'=>'Course counting policy','generalEducationPolicy'=>'General education','notes'=>'Notes'] as $key=>$label) if (isset($data['program'][$key]) && $data['program'][$key]!==[]) faculty_rule_tree($data['program'][$key],$label); ?></ul>
<?php else: ?><p>Requirements recorded for this major in the connected database.</p><?php faculty_table($data['rows'],['course_ID'=>'Course','course_Name'=>'Course name','course_Credits'=>'Credits','min_Grade_Req'=>'Minimum grade'],'Recorded major courses'); ?><p class="ff-help">These rows may not include every general-education, concentration, residency or elective rule. Use the degree audit for an individual undergraduate evaluation.</p><?php endif; ?>
</section><?php endif; ?>
<?php endif; ?>
</section>
