<?php
declare(strict_types=1);
require_once __DIR__.'/meeting-schedule.php';
require_once __DIR__.'/academic-portal.php';

/** Presentation order is independent of physical database-column order. */
function schedule_columns_header(string $actionLabel = '', bool $showSeats = false): void
{
    $labels=['CRN','Course','Course Name','Section','Professor','Days','Time','Building / Room'];
    if($showSeats)array_push($labels,'Total Seats','Available Seats');
    if($actionLabel!=='')$labels[]=$actionLabel;
    echo '<thead><tr>';foreach($labels as$label)echo '<th scope="col">'.e($label).'</th>';echo '</tr></thead>';
}

/** Render one actual section, preserving all day/time/date/location groups. */
function schedule_columns_row(array $section,array $groups,?array $action=null,bool $showSeats=false): void
{
    $professor=$section['faculty_Name']??implode(' ',array_filter([$section['first_Name']??null,$section['middle_Name']??null,$section['last_Name']??null],static fn($p)=>$p!==null&&$p!==''));
    $days=['Mon'=>'Monday','Tue'=>'Tuesday','Wed'=>'Wednesday','Thu'=>'Thursday','Fri'=>'Friday','Sat'=>'Saturday','Sun'=>'Sunday'];
    ?>
    <tr<?= count($groups)>1?' class="schedule-multiple-meetings"':'' ?>>
        <td><?= e($section['display_CRN']??$section['CRN']) ?></td>
        <td><?= e($section['display_course_ID']??$section['course_ID']) ?></td>
        <td><?= e($section['course_Name']) ?></td>
        <td><?= e((int)$section['section_No']) ?></td>
        <td><?= e($professor) ?></td>
        <td><?php foreach($groups as$group): ?><div class="schedule-meeting-detail"><?= e(implode(', ',array_map(static fn($d)=>$days[$d]??$d,explode('/',$group['meeting_Days'])))) ?></div><?php endforeach; ?></td>
        <td><?php foreach($groups as$group): ?><div class="student-section-meeting schedule-meeting-detail"><?= e(meeting_schedule_interval($group)) ?><?php if($group['meeting_Date_Range']??null): ?><br><span class="muted"><?= e($group['meeting_Date_Range']) ?></span><?php endif; ?></div><?php endforeach; ?></td>
        <td><?php foreach($groups as$group): ?><div class="schedule-meeting-detail"><?= e($group['Bldg_Name']??'Building unassigned') ?><?= ($group['room_Number']??null)!==null?' / '.e($group['room_Number']):'' ?></div><?php endforeach; ?></td>
        <?php if($showSeats): ?><td><?= e($section['maximum_Seats']) ?></td><td><?= e($section['available_Seats']) ?></td><?php endif; ?>
        <?php if($action!==null): ?><td><a href="<?= e($action['url']) ?>"><?= e($action['label']) ?></a></td><?php endif; ?>
    </tr>
    <?php
}

/** Fetch exact meetings for only the master-schedule rows currently displayed. */
function schedule_columns_meetings(PDO $pdo,array $sections): array
{
    if(!$sections)return[];
    $ids=array_values(array_unique(array_column($sections,'CRN')));
    $relation=academic_portal_meeting_relation($pdo);
    $q=$pdo->prepare('SELECT mt.* FROM '.$relation.' mt WHERE mt.CRN IN('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$raw=[];
    foreach($q as$meeting)$raw[$meeting['CRN']][]=$meeting;
    $out=[];
    foreach($sections as$section){
        $section['faculty_Name']=implode(' ',array_filter([$section['first_Name'],$section['middle_Name'],$section['last_Name']],static fn($p)=>$p!==null&&$p!==''));
        $rows=[];foreach($raw[$section['CRN']]??[]as$meeting)$rows[]=array_replace($section,$meeting);
        if(!$rows)$rows[]=array_replace($section,['day_Of_Week'=>null,'start_Time'=>null,'end_Time'=>null]);
        $out[$section['CRN']]=meeting_schedule_group_days($rows);
    }
    return$out;
}
