<?php
declare(strict_types=1);

function academic_today(?DateTimeImmutable $now = null): DateTimeImmutable
{
    return ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('America/New_York'));
}

function academic_semester_order(string $name): int
{
    if (!preg_match('/^(Spring|Fall) ([0-9]{4})$/D', $name, $match)) {
        throw new InvalidArgumentException('Unrecognized semester name.');
    }
    return (int) $match[2] * 2 + ($match[1] === 'Fall' ? 1 : 0);
}

function academic_semester_status(string $name): string
{
    $timeline = require dirname(__DIR__) . '/config/academic-timeline.php';
    $order = academic_semester_order($name);
    $current = academic_semester_order($timeline['current_semester']);
    return $order < $current ? 'Completed' : ($order === $current ? 'In progress' : 'Upcoming');
}
