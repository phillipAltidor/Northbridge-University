<?php
// Owner-confirmed semester boundaries, 2026-11-19. Regular attendance excludes
// published holidays, study/makeup days and finals (exam meeting times differ).
// Sources: https://www.oldwestbury.edu/fall-2026-academic-calendar
// https://www.oldwestbury.edu/degrees-programs/academic-calendars
$regularDates = static function(string $start, string $end, array $excluded): array {
    $dates=[]; $day=new DateTimeImmutable($start,new DateTimeZone('America/New_York'));
    while ($day->format('Y-m-d') <= $end) {
        $date=$day->format('Y-m-d'); if (!in_array($date,$excluded,true)) $dates[]=$date;
        $day=$day->modify('+1 day');
    }
    return $dates;
};
return [
    'Fall 2026' => [
        'starts_on'=>'2026-08-24', 'ends_on'=>'2026-12-16',
        'final_exams_start'=>'2026-12-10', 'final_exams_end'=>'2026-12-16',
        'attendance_dates'=>$regularDates('2026-08-24','2026-12-07',[
            '2026-09-07','2026-10-12','2026-10-13',
            '2026-11-25','2026-11-26','2026-11-27','2026-11-28','2026-11-29',
        ]),
        'grading'=>[],
    ],
    'Spring 2027' => [
        'starts_on'=>'2027-01-25', 'ends_on'=>'2027-05-18',
        'final_exams_start'=>'2027-05-12', 'final_exams_end'=>'2027-05-18',
        'attendance_dates'=>$regularDates('2027-01-25','2027-05-09',[
            '2027-02-15','2027-03-13','2027-03-14','2027-03-15','2027-03-16',
            '2027-03-17','2027-03-18','2027-03-19','2027-03-20','2027-03-21',
        ]),
        'early_closures'=>['2027-05-04'=>'16:30:00'],
        'grading'=>[],
    ],
];
// Owner confirmed finals and the 48-hour deadline on 2026-11-19.
// Per-section exam end times and permitted grades remain pending. Example shape:
// 'grading' => [12345 => ['final_exam_ends_at'=>'2026-12-10T12:00:00-05:00',
//                       'allowed_grades'=>[...]]]
// Use verified exam times, never inferred regular class times. The window opens
// at exam end and closes exactly 48 hours later, even across the semester end.
