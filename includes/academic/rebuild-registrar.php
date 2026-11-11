<?php
declare(strict_types=1);
namespace Northbridge\Academic;
require_once __DIR__.'/curriculum-evaluator.php';
require_once __DIR__.'/rebuild-prerequisites.php';

/** Server-side adapter. No student/evidence fields are accepted from HTTP input. */
final class RebuildRegistrar
{
    private array $courses=[];
    private array $programs=[];
    private array $metadata=[];
    private array $institutional=[];
    private array $ge=[];
    public function __construct(private \PDO $pdo)
    {
        foreach ($pdo->query('SELECT * FROM NB_AcademicCourse') as $row) {
            $definition=json_decode($row['definition_JSON'],true,512,JSON_THROW_ON_ERROR);
            $this->courses[$row['course_ID']]=['definition'=>$definition,'versionKey'=>$row['version_Key'],
                'rule'=>json_decode($row['rule_JSON'],true,512,JSON_THROW_ON_ERROR),
                'properties'=>$row['properties_JSON']===null?null:json_decode($row['properties_JSON'],true,512,JSON_THROW_ON_ERROR)];
        }
        foreach ($pdo->query('SELECT definition_JSON FROM NB_AcademicProgram') as $row) {$program=json_decode($row['definition_JSON'],true,512,JSON_THROW_ON_ERROR);$this->programs[$program['program']]=$program;}
        $directory=dirname(__DIR__,2).'/database/staging/academic-alignment/rules/';
        $read=static fn($name)=>json_decode(file_get_contents($directory.$name),true,512,JSON_THROW_ON_ERROR);
        // MySQL's binary JSON reorders object keys. Restore the reviewed path
        // order after semantic equality checking so equally ranked partial
        // allocations do not depend on database object serialization order.
        foreach ($read('curricula.json')['programs'] as $program) {
            $name=$program['program'];
            if (!isset($this->programs[$name]) || $this->programs[$name]!=$program) throw new \RuntimeException('Database curriculum differs from the reviewed catalog.');
            $this->programs[$name]=$program;
        }
        foreach ($read('course-metadata.json')['courses'] as $row) $this->metadata[$row['courseCode']]=$row;
        $this->institutional=$read('institutional-rules.json');
        foreach ($read('general-education-mappings.json')['rows'] as $row) if (!$row['lectureLabBundleCandidate']) $this->ge[$row['sourceCourseCode']][]=$row;
    }
    private function evidence(string $scope,int $sid,array $extra=[]): array
    {
        return $extra+['status'=>'approved','scope'=>$scope,'studentId'=>$sid,'approvalId'=>'NB-derived-'.hash('sha256',$sid.':'.$scope),'approvedBy'=>'Northbridge source-backed registrar adapter','approvedAt'=>'2026-11-07'];
    }
    public function student(int $sid,bool $includeForecast=false): ?array
    {
        $q=$this->pdo->prepare('SELECT profile_JSON FROM NB_AcademicStudent WHERE student_ID=?');$q->execute([$sid]);$value=$q->fetchColumn();
        if ($value===false) return null;
        $person=json_decode($value,true,512,JSON_THROW_ON_ERROR);
        $student=['studentId'=>$sid,'program'=>$person['major'],'minor'=>$person['minor'],'concentration'=>$person['concentration'],
            'isTransferStudent'=>false,'registrarEvidence'=>$person['registrarEvidence']??[],
            'approvals'=>$person['degreeApprovals']??[],'attempts'=>[],'proficiencies'=>[],'geApprovals'=>[]];
        $sql="SELECT h.course_ID,h.semester_ID,h.Grade,s.semester_Name FROM Student_History h JOIN Semester s ON s.semester_ID=h.semester_ID WHERE h.student_ID=?
              UNION ALL SELECT cs.course_ID,e.semester_ID,e.Grade,s.semester_Name FROM Enrollment e JOIN Course_Section cs ON cs.CRN=e.CRN JOIN Semester s ON s.semester_ID=e.semester_ID WHERE e.student_ID=? AND NOT EXISTS(SELECT 1 FROM Student_History h WHERE h.student_ID=e.student_ID AND h.CRN=e.CRN) ORDER BY semester_Name,course_ID";
        $q=$this->pdo->prepare($sql);$q->execute([$sid,$sid]);
        foreach ($q as $i=>$row) {
            if (!$includeForecast && $row['semester_ID']===2) continue;
            $entry=$this->courses[$row['course_ID']]??null;
            if (!$entry) throw new \RuntimeException('Unversioned course in rebuilt student record.');
            $c=$entry['definition'];$code=$c['courseCode'];[$season,$year]=explode(' ',$row['semester_Name']);$term=(int)($year.($season==='Fall'?'09':'02'));
            $attempt=['courseCode'=>$code,'catalogVersion'=>$c['catalogVersion'],'status'=>$row['Grade']===null?'in-progress':'completed',
                'grade'=>$row['Grade'],'credits'=>$c['credits'],'origin'=>'local','termOrder'=>$term,'attemptOrder'=>$i+1];
            $facts=$entry['properties'];
            if ($facts!==null) {
                if ($facts['courseCode']!==$code || $facts['catalogVersion']!==$c['catalogVersion']) throw new \RuntimeException('Course/version attribute mismatch.');
                $attempt+=array_intersect_key($facts,array_flip(['upperDivision','level','liberalArts','classifications','labBearing']));
                $attempt['attributeApproval']=$this->evidence('attributes:'.$code,$sid,['sourceReference'=>$facts['sectionAttributeEvidenceSHA256']]);
            }
            $student['attempts'][]=$attempt;
        }
        usort($student['attempts'],static fn($a,$b)=>[$a['termOrder'],$a['courseCode'],$a['catalogVersion']]<=>[$b['termOrder'],$b['courseCode'],$b['catalogVersion']]);
        foreach ($student['attempts'] as $index=>&$attempt) $attempt['attemptOrder']=$index+1;
        unset($attempt);
        $completed=(new Transcript($student['attempts']))->latest();$student['completedStandingCredits']=0;
        foreach ($completed as $code=>$a) if (Transcript::meets($a,null)===true) {
            $student['completedStandingCredits']+=$a['credits'];
            foreach (['CompositionI'=>'EL1000','CompositionII'=>'EL2216'] as $key=>$required) if ($code===$required) $student['proficiencies'][$key]=$this->evidence('proficiency:'.$key,$sid,['basis'=>'course','courseCode'=>$code,'completed'=>true,'grade'=>$a['grade'],'termOrder'=>$a['termOrder']]);
            if (isset($this->ge[$code])) $student['geApprovals'][]=$this->evidence('ge:'.$code,$sid,['courseCode'=>$code,'domains'=>array_values(array_unique(array_column($this->ge[$code],'domain'))),'cohortAndEffectiveDateReviewed'=>true,'sourceReference'=>array_column($this->ge[$code],'sourceURL'),'applicationPolicy'=>'Owner-authorized new GE requirements across fictional rebuilt history.']);
        }
        $admission=$student['registrarEvidence']['school-of-education-admission']??null;
        if ($admission && ($admission['status']??null)==='approved' && ($admission['studentId']??null)===$sid) $student['approvals']['school-of-education-admission']=$admission;
        return $student;
    }
    public function evaluateStudent(int $sid): ?array
    {
        $student=$this->student($sid);if ($student===null) return null;
        $engine=new CurriculumEvaluator($this->metadata,array_values($this->programs));
        $major=$engine->evaluate($this->programs[$student['program']],$student,$this->institutional);
        if ($major['selectedCourses']??[]) {
            $student['majorCourseCodes']=$major['selectedCourses'];
            $student['majorAllocationApproval']=$this->evidence('major-allocation',$sid,['basis'=>'actual completed-course evaluator allocation']);
        }
        $minor=$student['minor']?$engine->evaluate($this->programs[$student['minor']],$student,$this->institutional):null;
        return ['majorEvaluation'=>$major,'minorEvaluation'=>$minor,'completedCredits'=>$student['completedStandingCredits']];
    }
    public function prerequisite(int $sid,string $localCourse,int $targetTerm): array
    {
        $student=$this->student($sid,true);$course=$this->courses[$localCourse]??null;
        if (!$student || !$course) return Result::make('review-required','Versioned student and course records required.');
        $properties=[];foreach ($this->courses as $entry) if ($entry['properties']!==null) $properties[$entry['definition']['courseCode']]=$entry['properties'];
        return (new RebuildPrerequisiteEvaluator($student,$properties,$targetTerm,$course['versionKey']))->evaluate($course['rule']);
    }
}
