<?php
declare(strict_types=1);

/** Feature detection keeps the original database compatible until cutover. */
function academic_portal_enabled(PDO $pdo): bool
{
    static $cache;
    $cache ??= new WeakMap();
    if (isset($cache[$pdo])) return $cache[$pdo];
    $q=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='NB_AcademicRebuild'");
    return $cache[$pdo]=(int)$q->fetchColumn()===1;
}

/** Exact rows for rebuilt sections, protected legacy mappings for graduates. */
function academic_portal_meeting_relation(PDO $pdo): string
{
    $rebuilt=academic_portal_enabled($pdo);
    $legacy="SELECT cs.CRN,d.day_Of_Week,p.start_Time,p.end_Time,NULL AS start_Date,NULL AS end_Date
        FROM Course_Section cs JOIN Time_Slot_Day td ON td.time_Slot_ID=cs.time_Slot_ID
        JOIN Day d ON d.day_ID=td.day_ID JOIN Time_Slot_Period tp ON tp.time_Slot_ID=cs.time_Slot_ID
        JOIN Period p ON p.period_ID=tp.period_ID";
    if (!$rebuilt) return '('.$legacy.')';
    return '('.$legacy.' WHERE NOT EXISTS (SELECT 1 FROM NB_AcademicSection a WHERE a.CRN=cs.CRN)
        UNION ALL SELECT a.CRN,m.day_Of_Week,m.start_Time,m.end_Time,m.start_Date,m.end_Date
        FROM NB_AcademicSection a LEFT JOIN NB_AcademicMeeting m ON m.CRN=a.CRN)';
}

function academic_portal_source_labels(PDO $pdo,array $rows): array
{
    if (!$rows || !academic_portal_enabled($pdo)) return $rows;
    $ids=array_values(array_unique(array_column($rows,'CRN')));
    $q=$pdo->prepare('SELECT a.*,c.definition_JSON FROM NB_AcademicSection a JOIN Course_Section s ON s.CRN=a.CRN JOIN NB_AcademicCourse c ON c.course_ID=s.course_ID WHERE a.CRN IN('.implode(',',array_fill(0,count($ids),'?')).')');
    $q->execute($ids);$sources=[];
    foreach ($q as $r) $sources[$r['CRN']]=$r;
    foreach ($rows as &$r) if (isset($sources[$r['CRN']])) {
        $source=$sources[$r['CRN']];$definition=json_decode($source['definition_JSON'],true,512,JSON_THROW_ON_ERROR);
        $official=json_decode($source['source_JSON'],true,512,JSON_THROW_ON_ERROR);
        $capacity=filter_var($official['capacity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10]]);
        if ($capacity===false) throw new RuntimeException('Invalid imported section capacity.');
        $r['section_capacity']=$capacity;
        $northbridgeOnly=($official['sourceRelationship']??null)==='northbridge-only-in-person-pattern';
        if ($northbridgeOnly && $source['official_CRN']!==null) throw new RuntimeException('Northbridge-only section cannot have an official Old Westbury CRN.');
        $r['display_CRN']=$northbridgeOnly?'NB-'.$r['CRN']:($source['official_CRN']??'Conversion');
        $r['display_section']=(string)(int)($r['section_No']??$source['delivery_Group']);
        $r['source_section_reference']=$source['source_Section'];
        $r['display_course_ID']=$definition['courseCode'];
        $r['source_Term']=$source['source_Term'];
        if (array_key_exists('time_Slot_Day',$r)) {
            $starts=[];$ends=[];$dayLabels=[];$periods=[];
            $days=['monday'=>'M','tuesday'=>'T','wednesday'=>'W','thursday'=>'Th','friday'=>'F','saturday'=>'Sa','sunday'=>'Su'];
            foreach ($official['meetings'] as $m) {
                if (empty($m['beginTime']) || empty($m['endTime'])) continue;
                $labels=[];foreach ($days as $key=>$label) if ($m[$key]??false) {$labels[]=$label;$dayLabels[$label]=true;}
                $begin=\DateTimeImmutable::createFromFormat('!Hi',str_pad((string)$m['beginTime'],4,'0',STR_PAD_LEFT));
                $end=\DateTimeImmutable::createFromFormat('!Hi',str_pad((string)$m['endTime'],4,'0',STR_PAD_LEFT));
                if (!$begin || !$end) throw new RuntimeException('Invalid imported meeting time.');
                $label=implode('/',$labels);$starts[]=$label.' '.$begin->format('g:i A');$ends[]=$label.' '.$end->format('g:i A');
                $periods[]=$label.' '.$begin->format('H:i').'-'.$end->format('H:i').' ('.$m['startDate'].'–'.$m['endDate'].')';
            }
            $r['time_Slot_Day']=implode('/',array_keys($dayLabels))?:'TBA';
            $r['time_Slot_Period']=implode('; ',array_unique($periods))?:'TBA / untimed';
            $r['start_Time']=implode('; ',array_unique($starts))?:'TBA';
            $r['end_Time']=implode('; ',array_unique($ends))?:'TBA';
        }
    }
    unset($r);return $rows;
}

function academic_portal_meetings_overlap(array $left,array $right): bool
{
    foreach ($left as $a) foreach ($right as $b) {
        if (empty($a['day_Of_Week']) || $a['day_Of_Week']!==$b['day_Of_Week'] || $a['start_Time']>=$b['end_Time'] || $b['start_Time']>=$a['end_Time']) continue;
        $lo=max($a['start_Date']??'0001-01-01',$b['start_Date']??'0001-01-01');
        $hi=min($a['end_Date']??'9999-12-31',$b['end_Date']??'9999-12-31');
        if ($lo>$hi) continue;
        $date=new DateTimeImmutable($lo);$offset=(array_search($a['day_Of_Week'],['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],true)+1-(int)$date->format('N')+7)%7;
        if ($date->modify('+'.$offset.' days')->format('Y-m-d')<=$hi) return true;
    }
    return false;
}

/** Preserve official meetings while validating editable fictional resources. */
function academic_portal_validate_assignment(PDO $pdo,array $before,array $after): bool
{
    if (!academic_portal_enabled($pdo)) return false;
    $q=$pdo->prepare('SELECT c.dept_ID,a.definition_JSON FROM NB_AcademicSection s JOIN Course_Section cs ON cs.CRN=s.CRN JOIN Course c ON c.course_ID=cs.course_ID JOIN NB_AcademicCourse a ON a.course_ID=c.course_ID WHERE s.CRN=?');
    $q->execute([$before['CRN']]);$source=$q->fetch();if (!$source) return false;
    foreach (['course_ID','section_No','time_Slot_ID'] as $key) if ($before[$key]!==$after[$key]) throw new DomainException('The imported course, section group and official meeting times are fixed.');
    $q=$pdo->prepare('SELECT f.faculty_Type FROM Faculty f JOIN Faculty_Department d ON d.faculty_ID=f.faculty_ID WHERE f.faculty_ID=? AND d.dept_ID=?');$q->execute([$after['faculty_ID'],$source['dept_ID']]);$type=$q->fetchColumn();
    if ($type===false) throw new DomainException('Choose faculty appointed to the course department.');
    $q=$pdo->prepare('SELECT COUNT(*) FROM Course_Section WHERE semester_ID=? AND faculty_ID=? AND CRN<>?');$q->execute([$after['semester_ID'],$after['faculty_ID'],$after['CRN']]);
    if ((int)$q->fetchColumn()>=($type==='Full-Time'?2:1)) throw new DomainException('This assignment exceeds the faculty semester workload limit.');
    $definition=json_decode($source['definition_JSON'],true,512,JSON_THROW_ON_ERROR);
    if ($after['room_ID']!==null) {
        $q=$pdo->prepare('SELECT room_Type FROM Room WHERE room_ID=?');$q->execute([$after['room_ID']]);$room=$q->fetchColumn();
        if (!in_array($room,['Lecture','Lab'],true) || ($definition['labBearing'] && $room!=='Lab')) throw new DomainException('Choose an appropriate Lecture or Lab room for this course.');
    }
    $relation=academic_portal_meeting_relation($pdo);
    $q=$pdo->prepare("SELECT m.* FROM $relation m WHERE m.CRN=?");$q->execute([$before['CRN']]);$pattern=$q->fetchAll();
    if (array_filter($pattern,static fn($m)=>!empty($m['start_Time'])) && $after['room_ID']===null) throw new DomainException('A scheduled in-person meeting requires an assigned room.');
    $q=$pdo->prepare("SELECT m.* FROM Course_Section s JOIN $relation m ON m.CRN=s.CRN WHERE s.semester_ID=? AND s.CRN<>? AND (s.faculty_ID=? OR (? IS NOT NULL AND s.room_ID=?))");
    $q->execute([$after['semester_ID'],$before['CRN'],$after['faculty_ID'],$after['room_ID'],$after['room_ID']]);
    if (academic_portal_meetings_overlap($pattern,$q->fetchAll())) throw new DomainException('The faculty or room has an overlapping teaching assignment.');
    return true;
}
