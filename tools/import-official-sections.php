<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/academic/test-database.php';
require dirname(__DIR__).'/includes/academic/official-sections.php';
$pdo=academic_test_database();$before=academic_legacy_fingerprints($pdo);$root=dirname(__DIR__).'/database/staging/academic-alignment/';
$raw=file_get_contents($root.'official-sections-fall-2026.json');$source=json_decode($raw,true,512,JSON_THROW_ON_ERROR);\Northbridge\Academic\validate_official_sections($source);
$catalog=7;$q=$pdo->prepare('SELECT package_sha256 FROM NB_CatalogVersion WHERE id=?');$q->execute([$catalog]);if($q->fetchColumn()!=='af788468dafce856c4ef61e4d428a42730e488ec3e9e96f969b0861f0ec2b032')throw new RuntimeException('Expected reviewed catalog missing.');
foreach(explode(';',file_get_contents(dirname(__DIR__).'/database/migrations/2026-11-07-official-sections.sql'))as$sql)if(trim($sql))$pdo->exec($sql);
$encode=static fn($x)=>json_encode($x,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$hash=hash('sha256',$raw);$pdo->beginTransaction();
try{
 $manifest=$source;unset($manifest['sections']);
 $pdo->prepare('INSERT IGNORE INTO NB_SectionImport (catalog_id,source_sha256,source_url,term_code,source_manifest) VALUES (?,?,?,?,?)')->execute([$catalog,$hash,$source['source'],$source['term'],$encode($manifest)]);
 $q=$pdo->prepare('SELECT id FROM NB_SectionImport WHERE catalog_id=? AND source_sha256=?');$q->execute([$catalog,$hash]);$batch=(int)$q->fetchColumn();
 $q=$pdo->prepare('SELECT id,version_key FROM NB_CourseVersion WHERE catalog_id=?');$q->execute([$catalog]);$versions=[];foreach($q as$v)$versions[$v['version_key']]=$v['id'];
 $insert=$pdo->prepare('INSERT IGNORE INTO NB_OfficialSection (import_id,course_version_id,term_code,official_crn,section_label,official_course_code,course_title,part_of_term,review_status,source_json) VALUES (?,?,?,?,?,?,?,?,?,?)');
 $meeting=$pdo->prepare('INSERT IGNORE INTO NB_OfficialMeeting (section_id,ordinal,begin_time,end_time,start_date,end_date,days_json,source_json) VALUES (?,?,?,?,?,?,?,?)');
 $linked=0;$outside=0;$noTime=0;
 foreach($source['sections']as$s){
  $code=$s['subject'].$s['courseNumber'];$version=$versions[$code]??null;if($version)$linked++;else$outside++;
  $status=$version?'linked-code-needs-level-and-fictional-assignment-review':'outside-collected-catalog-needs-review';
  $insert->execute([$batch,$version,$s['term'],$s['courseReferenceNumber'],$s['sequenceNumber'],$code,$s['courseTitle'],$s['partOfTerm'],$status,$encode($s)]);
  $q=$pdo->prepare('SELECT id FROM NB_OfficialSection WHERE import_id=? AND term_code=? AND official_crn=?');$q->execute([$batch,$s['term'],$s['courseReferenceNumber']]);$id=(int)$q->fetchColumn();$scheduled=false;
  foreach($s['meetings']as$i=>$m){$days=[];foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday']as$d)$days[$d]=$m[$d];$meeting->execute([$id,$i+1,$m['beginTime'],$m['endTime'],\Northbridge\Academic\official_iso_date($m['startDate']),\Northbridge\Academic\official_iso_date($m['endDate']),$encode($days),$encode($m)]);if($m['beginTime']&&$m['endTime']&&in_array(true,$days,true))$scheduled=true;}
  if(!$scheduled)$noTime++;
 }
 if(academic_legacy_fingerprints($pdo)!==$before)throw new RuntimeException('Legacy data changed during import.');
 $pdo->commit();
 $receipt=['status'=>'complete-official-fall-2026-import-inactive-test-only','asOf'=>'2026-11-07','database'=>'northbridge_ow_2026_test','importId'=>$batch,'catalogId'=>$catalog,'sourceSHA256'=>$hash,'officialSections'=>$source['totalSections'],'exactMeetingBlocks'=>$source['totalMeetings'],'linkedByCatalogCode'=>$linked,'outsideCollectedCatalog'=>$outside,'sectionsWithoutScheduledTime'=>$noTime,'registrationActive'=>false,'projectCapacity'=>10,'fictionalAssignmentsMade'=>0,'fictionalFacultyRoomAssignmentStatus'=>'Pending: teaching eligibility, workload limits, lab/room suitability and conflict review must be established before assignment.','realFacultyContactRoomFieldsImported'=>false,'legacyTablesUnchanged'=>count($before),'workingDatabaseChanged'=>false,'sourceURL'=>$source['source']];
 file_put_contents($root.'official-section-import-receipt.json',$encode($receipt)."\n");echo json_encode($receipt,JSON_PRETTY_PRINT),"\n";
}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
