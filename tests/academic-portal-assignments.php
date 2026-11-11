<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/config/database.php';
require dirname(__DIR__).'/includes/academic-portal.php';
if (!preg_match('/^northbridge_ow_rebuild_2026_[4-9][0-9]*$/D',$database)) throw new RuntimeException('Isolated rebuilt database required.');
$checks=0;
$check=static function($ok)use(&$checks):void {if (!$ok) throw new RuntimeException('Assignment guard assertion failed.');$checks++;};
$q=$pdo->query('SELECT cs.* FROM Course_Section cs JOIN NB_AcademicSection s ON s.CRN=cs.CRN WHERE cs.semester_ID=1 ORDER BY cs.CRN LIMIT 1');$before=$q->fetch();
$check(academic_portal_validate_assignment($pdo,$before,$before));
foreach (['course_ID','section_No','time_Slot_ID'] as $key) {
 $after=$before;$after[$key]=$key==='course_ID'?'CS0000':$after[$key]+1;$rejected=false;
 try {academic_portal_validate_assignment($pdo,$before,$after);} catch (DomainException $e) {$rejected=str_contains($e->getMessage(),'official meeting times');}
 $check($rejected);
}
$q=$pdo->prepare('SELECT f.faculty_ID FROM Faculty f WHERE NOT EXISTS (SELECT 1 FROM Faculty_Department d JOIN Course c ON c.dept_ID=d.dept_ID WHERE d.faculty_ID=f.faculty_ID AND c.course_ID=?) LIMIT 1');$q->execute([$before['course_ID']]);$after=$before;$after['faculty_ID']=(int)$q->fetchColumn();$rejected=false;
try {academic_portal_validate_assignment($pdo,$before,$after);} catch (DomainException $e) {$rejected=str_contains($e->getMessage(),'appointed');}$check($rejected);
$a=['day_Of_Week'=>'Monday','start_Time'=>'09:00:00','end_Time'=>'10:00:00','start_Date'=>'2026-09-01','end_Date'=>'2026-10-15'];
$b=$a;$b['start_Date']='2026-10-16';$b['end_Date']='2026-12-01';$check(!academic_portal_meetings_overlap([$a],[$b]));
$b=$a;$b['start_Time']='10:00:00';$b['end_Time']='11:00:00';$check(!academic_portal_meetings_overlap([$a],[$b]));
$b=$a;$b['start_Time']='09:30:00';$check(academic_portal_meetings_overlap([$a],[$b]));
$a['start_Date']='2026-09-01';$a['end_Date']='2026-09-06';$b=$a;$check(!academic_portal_meetings_overlap([$a],[$b]));
echo json_encode(['status'=>'official-meeting-and-fictional-assignment-guards-passed','checks'=>$checks,'databaseWrites'=>false]),"\n";
