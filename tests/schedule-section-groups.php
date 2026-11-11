<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/meeting-schedule.php';
$base=['CRN'=>100,'course_ID'=>'CS2510','course_Credits'=>4,'Bldg_Name'=>'Test building','room_Number'=>'101','faculty_Name'=>'Test faculty','start_Time'=>'09:50:00','end_Time'=>'11:30:00','start_Date'=>'2026-08-24','end_Date'=>'2026-12-16'];
$meetings=[$base+['day_Of_Week'=>'Monday'],$base+['day_Of_Week'=>'Wednesday'],array_replace($base,['day_Of_Week'=>'Wednesday','start_Time'=>'11:40:00','end_Time'=>'12:40:00'])];
$grouped=meeting_schedule_group_sections($meetings);
if(count($grouped)!==1||$grouped[0]['course_Credits']!==4||count($grouped[0]['meeting_Groups'])!==2)throw new RuntimeException('One enrollment must retain both time groups and one credit value.');
$groups=$grouped[0]['meeting_Groups'];
if($groups[0]['meeting_Days']!=='Mon/Wed'||$groups[1]['meeting_Days']!=='Wed'||meeting_schedule_interval($groups[1])!=='11:40 AM - 12:40 PM')throw new RuntimeException('Grouping must not invent a Monday second meeting.');
$week=meeting_schedule_week($meetings,new DateTimeImmutable('2026-11-08'));
if(array_sum(array_map(static fn($d)=>count($d['meetings']),$week))!==3)throw new RuntimeException('Calendar must preserve three actual meetings.');
echo "PASS: one section row preserves all exact meetings and credits\n";
