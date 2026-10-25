<?php

require_once __DIR__ . '/../includes/academic-timeline.php';

// The date is live; exact semester/registration boundaries await the manual.
return [
    'as_of' => academic_today()->format('Y-m-d'),
    'current_semester' => 'Fall 2026',
    'next_semester' => 'Spring 2027',
];
