<?php
require_once __DIR__.'/../includes/layout.php';
require_role(['Faculty']);
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/faculty-rosters.php';
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='GET'){
    http_response_code(405);header('Allow: GET');exit('Course rosters are read-only.');
}
$facultyID=(int)$_SESSION['user_id'];
$semesters=faculty_roster_semesters($pdo,$facultyID);
$timeline=require __DIR__.'/../config/academic-timeline.php';
$default=null;foreach($semesters as$option)if($option['semester_Name']===$timeline['current_semester'])$default=$option['semester_ID'];
$semesterID=filter_var($_GET['semester_id']??$default,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
$semester=null;foreach($semesters as$option)if((int)$option['semester_ID']===$semesterID)$semester=$option;
if(!$semester){http_response_code(400);exit('Select a valid semester.');}
$sections=meeting_schedule_group_sections(faculty_schedule_meetings($pdo,$facultyID,$semester['semester_Name']));
$crnInput=$_GET['crn']??'';
$crn=$crnInput===''?($sections[0]['CRN']??null):filter_var($crnInput,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
if($crn===false){http_response_code(400);exit('Select a valid course section.');}
$selected=null;foreach($sections as$section)if((int)$section['CRN']===$crn)$selected=$section;
if($crn!==null&&!$selected){http_response_code(404);exit('Course section could not be found.');}
$students=$selected?faculty_section_roster($pdo,$facultyID,$semesterID,(int)$selected['CRN']):[];
if($students===null){http_response_code(404);exit('Course section could not be found.');}
page_start('Course Rosters','Faculty','faculty/rosters.php');
?>
<div class="student-schedule faculty-rosters">
    <form class="student-schedule-filter" method="get">
        <label for="roster-semester">Semester</label>
        <select id="roster-semester" name="semester_id" onchange="this.form.elements.crn.value=''; this.form.requestSubmit();">
            <?php foreach($semesters as$option): ?>
                <option value="<?= e($option['semester_ID']) ?>"<?= (int)$option['semester_ID']===$semesterID?' selected':'' ?>><?= e($option['semester_Name']) ?><?= $option['semester_Name']===$timeline['current_semester']?' — Current':($option['semester_Name']===$timeline['next_semester']?' — Next':'') ?></option>
            <?php endforeach; ?>
        </select>
        <label for="roster-section">Course Section</label>
        <select id="roster-section" name="crn" onchange="this.form.requestSubmit();"<?= !$sections?' disabled':'' ?>>
            <?php if(!$sections): ?><option value="">No assigned sections</option><?php endif; ?>
            <?php foreach($sections as$section): ?>
                <option value="<?= e($section['CRN']) ?>"<?= (int)$section['CRN']===$crn?' selected':'' ?>><?= e(($section['display_course_ID']??$section['course_ID']).' · '.($section['display_section']??$section['section_No']).' · '.$section['course_Name'].' ('.($section['display_CRN']??$section['CRN']).')') ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">View Roster</button>
    </form>
    <h2><?= e($semester['semester_Name']) ?></h2>
    <?php if(!$selected): ?>
        <p class="muted">No assigned classes for <?= e($semester['semester_Name']) ?>.</p>
    <?php else: ?>
        <h3><?= e(($selected['display_course_ID']??$selected['course_ID']).' · '.$selected['course_Name']) ?></h3>
        <p>Section <?= e($selected['display_section']??$selected['section_No']) ?> · CRN <?= e($selected['display_CRN']??$selected['CRN']) ?> · <?= count($students) ?> <?= count($students)===1?'student':'students' ?></p>
        <?php foreach($selected['meeting_Groups']as$meeting): ?>
            <p><?= e($meeting['meeting_Days'].' · '.meeting_schedule_interval($meeting)) ?> · <?= e($meeting['Bldg_Name']??'Building unassigned') ?><?= $meeting['room_Number']!==null?' / '.e($meeting['room_Number']):'' ?><?php if($meeting['meeting_Date_Range']??null): ?> · <?= e($meeting['meeting_Date_Range']) ?><?php endif; ?></p>
        <?php endforeach; ?>
        <?php if(!$students): ?><p class="muted">No students are enrolled in this section.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <caption>Students in <?= e(($selected['display_course_ID']??$selected['course_ID']).' · '.($selected['display_section']??$selected['section_No'])) ?></caption>
                <thead><tr><th scope="col">Student ID</th><th scope="col">Name</th><th scope="col">University Email</th><th scope="col">Student Type</th><th scope="col">Year</th><th scope="col">Major</th></tr></thead>
                <tbody><?php foreach($students as$student): ?>
                    <tr><td><?= e($student['student_ID']) ?></td><td><?= e(implode(' ',array_filter([$student['first_Name'],$student['middle_Name'],$student['last_Name']],static fn($p)=>$p!==null&&$p!==''))) ?></td><td><?= e($student['user_Email']) ?></td><td><?= e($student['student_Type']) ?></td><td><?= e($student['student_Year']) ?></td><td><?= e($student['major_Name']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php page_end(); ?>
