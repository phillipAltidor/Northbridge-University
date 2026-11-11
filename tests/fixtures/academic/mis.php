<?php
declare(strict_types=1);
require_once __DIR__.'/cis.php';
function mis_complete_fixture(array $program): array
{
    $fixture=cis_complete_fixture($program);
    $fixture['fixtureId']='synthetic-mis-complete';
    $fixture['program']='MIS';
    foreach ($fixture['attempts'] as &$attempt) {
        if (in_array($attempt['courseCode'],['BU3502','BU3511'],true)) $attempt['credits']=3;
    }
    unset($attempt);
    // 66 major + 52 four-credit electives + one two-credit elective = 120.
    $fixture['attempts'][]=cis_attempt('BU3600','B',['credits'=>2]);
    return $fixture;
}
