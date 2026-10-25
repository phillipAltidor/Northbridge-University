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
        ], JSON_THROW_ON_ERROR);
        if (!isset($groups[$key])) $groups[$key] = ['meeting' => $meeting, 'days' => []];
        $groups[$key]['days'][$meeting['day_Of_Week']] = true;
    }
    $rows = [];
    foreach ($groups as $group) {
        $row = $group['meeting'];
        $row['meeting_Days'] = implode('/', array_intersect_key($dayLabels, $group['days']));
        unset($row['day_Of_Week']);
        $rows[] = $row;
    }
    return $rows;
}

function meeting_schedule_week(array $meetings, DateTimeImmutable $today): array
{
    $monday = $today->setTime(0, 0)->modify('-' . ((int) $today->format('N') - 1) . ' days');
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->modify('+' . $i . ' days');
        $rows = array_values(array_filter($meetings, static fn($row) => $row['day_Of_Week'] === $date->format('l')));
        usort($rows, static fn($a, $b) => [$a['start_Time'], $a['course_ID'], $a['CRN']] <=> [$b['start_Time'], $b['course_ID'], $b['CRN']]);
        $week[] = ['date' => $date, 'today' => $date->format('Y-m-d') === $today->format('Y-m-d'), 'meetings' => $rows];
    }
    return $week;
}

function meeting_schedule_time(string $time): string
{
    return DateTimeImmutable::createFromFormat('!H:i:s', $time)->format('g:i A');
}
