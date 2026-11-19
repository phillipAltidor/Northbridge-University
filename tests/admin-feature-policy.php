<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/includes/admin-features.php';
$count=0;
function policy_ok(bool $yes):void {global $count;if (!$yes) throw new RuntimeException('Policy assertion failed');$count++;}
foreach (['','0','6,','6,wrong','6, 0'] as $bad) {$_ENV['ADMIN_FEATURE_WRITER_IDS']=$bad;policy_ok(!af_can_write(6));}
$_ENV['ADMIN_FEATURE_WRITER_IDS']='6, 9';policy_ok(af_can_write(6)&&af_can_write(9)&&!af_can_write(7));
foreach (['Fall 2026'=>['2026-08-24T00:00:00-04:00','2026-12-16T23:59:59-05:00'],'Spring 2027'=>['2027-01-25T00:00:00-05:00','2027-05-18T23:59:59-04:00']] as $term=>$times) {
 foreach($times as $stamp) {af_window($term,new DateTimeImmutable($stamp));policy_ok(true);}
 foreach([(new DateTimeImmutable($times[0]))->modify('-1 second'),(new DateTimeImmutable($times[1]))->modify('+1 second')] as $outside) {try {af_window($term,$outside);throw new RuntimeException('Outside window accepted');}catch(DomainException $e){policy_ok(true);}}
}
foreach (['0','-1','01','2147483648',[],null,'1 OR 1=1'] as $bad) {try {af_id(['id'=>$bad],'id');throw new RuntimeException('Bad ID accepted');}catch(DomainException $e){policy_ok(true);}}
foreach (['X','',[]] as $bad) {try {af_grade(['Grade'=>$bad]);throw new RuntimeException('Bad grade accepted');}catch(DomainException $e){policy_ok(true);}}
try {af_check_version(['key'=>'new'],['version'=>af_version(['key'=>'old'])]);throw new RuntimeException('Stale accepted');}catch(DomainException $e){policy_ok(true);}
echo "PASS: $count Admin policy, permission, input and stale-version checks.\n";
