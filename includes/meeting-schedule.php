<?php

function meeting_schedule_group_days(array $meetings): array
{
    $dayLabels = ['Monday' => 'Mon', 'Tuesday' => 'Tue', 'Wednesday' => 'Wed',
        'Thursday' => 'Thu', 'Friday' => 'Fri', 'Saturday' => 'Sat', 'Sunday' => 'Sun'];
    $groups = [];
    foreach ($meetings as $meeting) {
        $key = json_encode([
            $meeting['CRN'], $meeting['start_Time'], $meeting['end_Time'],
            $meeting['Bldg_Name'], $meeting['room_Number'], $meeting['faculty_Name'],
            $meeting['start_Date']??null,$meeting['end_Date']??null,
        ], JSON_THROW_ON_ERROR);
        if (!isset($groups[$key])) $groups[$key] = ['meeting' => $meeting, 'days' => []];
        if (!empty($meeting['day_Of_Week'])) $groups[$key]['days'][$meeting['day_Of_Week']] = true;
    }
    $rows = [];
    foreach ($groups as $group) {
        $row = $group['meeting'];
        $row['meeting_Days'] = implode('/', array_intersect_key($dayLabels, $group['days'])) ?: 'TBA';
        $row['meeting_Date_Range']=!empty($row['start_Date'])?date('M j',strtotime($row['start_Date'])).' – '.date('M j',strtotime($row['end_Date'])):null;
        unset($row['day_Of_Week']);
        $rows[] = $row;
    }
    return $rows;
}

/** One enrollment row, with its exact meeting groups retained underneath. */
function meeting_schedule_group_sections(array $meetings): array
{
    $sections=[];
    foreach (meeting_schedule_group_days($meetings) as $meeting) {
        $key=(string)$meeting['CRN'];
        if (!isset($sections[$key])) $sections[$key]=$meeting+['meeting_Groups'=>[]];
        $sections[$key]['meeting_Groups'][]=$meeting;
    }
    return array_values($sections);
}

function meeting_schedule_week(array $meetings, DateTimeImmutable $today): array
{
    $monday = $today->setTime(0, 0)->modify('-' . ((int) $today->format('N') - 1) . ' days');
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->modify('+' . $i . ' days');
        $rows = array_values(array_filter($meetings, static fn($row) => $row['day_Of_Week'] === $date->format('l')
            && (empty($row['start_Date']) || $row['start_Date']<=$date->format('Y-m-d'))
            && (empty($row['end_Date']) || $row['end_Date']>=$date->format('Y-m-d'))));
        usort($rows, static fn($a, $b) => [$a['start_Time'], $a['course_ID'], $a['CRN']] <=> [$b['start_Time'], $b['course_ID'], $b['CRN']]);
        $week[] = ['date' => $date, 'today' => $date->format('Y-m-d') === $today->format('Y-m-d'), 'meetings' => $rows];
    }
    return $week;
}

function meeting_schedule_time(string $time): string
{
    return DateTimeImmutable::createFromFormat('!H:i:s', $time)->format('g:i A');
}

function meeting_schedule_interval(array $meeting): string
{
    if (empty($meeting['start_Time']) || empty($meeting['end_Time'])) return 'TBA / untimed';
    return meeting_schedule_time($meeting['start_Time']).' - '.meeting_schedule_time($meeting['end_Time']);
}
