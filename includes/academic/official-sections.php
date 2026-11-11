<?php
declare(strict_types=1);
namespace Northbridge\Academic;
const OFFICIAL_SECTION_FIELDS=['courseReferenceNumber','subject','courseNumber','courseDisplay','subjectCourse','courseTitle','sequenceNumber','term','termDesc','scheduleTypeDescription','instructionalMethod','instructionalMethodDescription','partOfTerm','creditHours','creditHourLow','creditHourHigh','creditHourIndicator','openSection','maximumEnrollment','meetings'];
const OFFICIAL_MEETING_FIELDS=['beginTime','endTime','startDate','endDate','monday','tuesday','wednesday','thursday','friday','saturday','sunday','meetingScheduleType','meetingType','meetingTypeDescription'];
function validate_official_sections(array $source): void
{
    if(($source['term']??null)!=='202609'||($source['description']??null)!=='Fall 2026')throw new \InvalidArgumentException('Unexpected source term.');
    $seen=[];$count=0;
    foreach($source['sections'] as $s){
        if(array_diff(array_keys($s),OFFICIAL_SECTION_FIELDS)||array_diff(OFFICIAL_SECTION_FIELDS,array_keys($s)))throw new \InvalidArgumentException('Unapproved/missing section fields.');
        if(!is_string($s['courseReferenceNumber'])||!preg_match('/^\d+$/',$s['courseReferenceNumber'])||!is_string($s['sequenceNumber'])||$s['term']!==$source['term'])throw new \InvalidArgumentException('Invalid section identity; CRN/labels must remain strings.');
        $key=$s['term'].':'.$s['courseReferenceNumber'];if(isset($seen[$key]))throw new \InvalidArgumentException('Duplicate source term/CRN.');$seen[$key]=true;
        foreach($s['meetings'] as $m){
            if(array_diff(array_keys($m),OFFICIAL_MEETING_FIELDS)||array_diff(OFFICIAL_MEETING_FIELDS,array_keys($m)))throw new \InvalidArgumentException('Unapproved/missing meeting fields.');
            foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)if(!is_bool($m[$day])&&$m[$day]!==null)throw new \InvalidArgumentException('Meeting days must preserve source booleans/null.');
            foreach(['beginTime','endTime'] as $field)if($m[$field]!==null&&(!is_string($m[$field])||!preg_match('/^\d{4}$/',$m[$field])))throw new \InvalidArgumentException('Meeting times must preserve four-character source values.');
            $count++;
        }
    }
    if(count($source['sections'])!==$source['totalSections']||$count!==$source['totalMeetings'])throw new \InvalidArgumentException('Incomplete official section/meeting coverage.');
}
function official_iso_date(?string $source): ?string
{
    if($source===null||$source==='')return null;
    $date=\DateTimeImmutable::createFromFormat('!m/d/Y',$source);
    if(!$date||$date->format('m/d/Y')!==$source)throw new \InvalidArgumentException('Invalid official meeting date.');
    return $date->format('Y-m-d');
}
