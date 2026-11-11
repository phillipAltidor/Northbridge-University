<?php
declare(strict_types=1);
namespace Northbridge\Academic;
require_once __DIR__.'/rebuild-registrar.php';

/** Live undergraduate audits. Evaluation and presentation never write records. */
final class DegreeAuditService
{
    private RebuildRegistrar $registrar;
    private array $programs=[];
    private array $metadata=[];
    private array $courses=[];
    private array $ge=[];
    private array $attributes=[];
    private array $institutional;
    private int $currentTerm;
    private int $nextTerm;
    public function __construct(private \PDO $pdo)
    {
        $this->registrar=new RebuildRegistrar($pdo);
        $directory=dirname(__DIR__,2).'/database/staging/academic-alignment/rules/';
        $read=static fn($name)=>json_decode(file_get_contents($directory.$name),true,512,JSON_THROW_ON_ERROR);
        foreach ($read('curricula.json')['programs'] as $p) $this->programs[$p['program']]=$p;
        foreach ($read('course-metadata.json')['courses'] as $c) $this->metadata[$c['courseCode']]=$c;
        foreach ($pdo->query('SELECT course_ID,version_Key,definition_JSON,properties_JSON,rule_JSON FROM NB_AcademicCourse') as $c) $this->courses[$c['version_Key']]=json_decode($c['definition_JSON'],true,512,JSON_THROW_ON_ERROR)+['localCourseId'=>$c['course_ID'],'prerequisiteRule'=>json_decode($c['rule_JSON'],true,512,JSON_THROW_ON_ERROR),'reviewedProperties'=>$c['properties_JSON']===null?null:json_decode($c['properties_JSON'],true,512,JSON_THROW_ON_ERROR)];
        foreach ($read('general-education-mappings.json')['rows'] as $r) if (!$r['lectureLabBundleCandidate']) $this->ge[$r['sourceCourseCode']][]=$r['domain'];
        $attributeFile=dirname(__DIR__,2).'/database/staging/academic-alignment/official-course-attributes.json';
        if (is_file($attributeFile)) foreach (json_decode(file_get_contents($attributeFile),true,512,JSON_THROW_ON_ERROR)['records'] as $row) foreach ($row['attributes'] as $attribute) $this->attributes[$row['courseKey']][$attribute]=true;
        $this->institutional=$read('institutional-rules.json');
        $timeline=require dirname(__DIR__,2).'/config/academic-timeline.php';
        $this->currentTerm=$this->termCode($timeline['current_semester']);$this->nextTerm=$this->termCode($timeline['next_semester']);
    }
    private function termCode(string $name): int
    {
        [$season,$year]=explode(' ',$name);return (int)($year.($season==='Fall'?'09':'02'));
    }
    public static function termName(int $code): string
    {
        return ($code%100===9?'Fall':'Spring').' '.intdiv($code,100);
    }
    public function program(string $name): ?array {return $this->programs[$name]??null;}
    public function course(string $code,?string $version=null): array
    {
        $key=$code.($version && $version!=='2026-2027'?'@'.$version:'');
        return $this->courses[$key]??['courseCode'=>$code,'title'=>$this->metadata[$code]['sourceTitle']??'Course information needs review','credits'=>$this->metadata[$code]['creditMinimum']??null,'catalogVersion'=>$version??'2026-2027'];
    }
    public function eligibleOptions(array $selector): array
    {
        $options=[];
        foreach ($this->courses as $course) {
            if ($course['catalogVersion']!=='2026-2027') continue;
            preg_match('/^([A-Z]+)(\d+)/',$course['courseCode'],$m);$level=(int)$m[2];
            if (!in_array($m[1],(array)($selector['subject']??[]),true) || in_array($course['courseCode'],$selector['excludeCourses']??[],true)) continue;
            if (isset($selector['levels']) && !in_array((int)(floor($level/1000)*1000),$selector['levels'],true)) continue;
            if (isset($selector['minimumLevel']) && $level<$selector['minimumLevel']) continue;
            if (isset($selector['classification']) && !in_array($selector['classification'],$course['reviewedProperties']['classifications']??[],true)) continue;
            $options[$course['courseCode']]=['code'=>$course['courseCode'],'version'=>null];
        }
        ksort($options);return array_values($options);
    }
    public function courseDetails(string $code,?string $version=null): array
    {
        $course=$this->course($code,$version);$key=$code.($version && $version!=='2026-2027'?'@'.$version:'');
        $course['description']=($version && $version!=='2026-2027') ? 'A verified description for this historical course version is not yet available.' : ($course['description']??$this->metadata[$code]['displayDescription']??'A verified description is not yet available.');
        $course['prerequisiteRule']=$course['prerequisiteRule']??['type'=>'review-required'];
        $course['attributes']=array_keys($this->attributes[$key]??[]);
        $course['generalEducationDomains']=array_values(array_unique($this->ge[$code]??[]));
        return $course;
    }
    public function domainCourses(string $domain): array
    {
        $codes=[];
        foreach ($this->ge as $code=>$domains) if (in_array($domain,$domains,true) && isset($this->courses[$code])) $codes[]=$code;
        sort($codes);return $codes;
    }
    public function search(string $query): array
    {
        if ($query==='') return [];
        $query=str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$query);
        $q=$this->pdo->prepare("SELECT s.student_ID,u.first_Name,u.middle_Name,u.last_Name,m.major_Name FROM Student s JOIN User u ON u.user_ID=s.student_ID JOIN Major m ON m.major_ID=s.major_ID WHERE s.student_Type='Undergraduate' AND (CAST(s.student_ID AS CHAR)=? OR CONCAT_WS(' ',u.first_Name,u.middle_Name,u.last_Name) LIKE ?) ORDER BY u.last_Name,u.first_Name,s.student_ID LIMIT 25");
        $q->execute([$query,'%'.$query.'%']);return $q->fetchAll();
    }
    public function audit(int $sid): ?array
    {
        $q=$this->pdo->prepare('SELECT s.student_ID,s.student_Type,s.student_Year,u.first_Name,u.middle_Name,u.last_Name,m.major_Name FROM Student s JOIN User u ON u.user_ID=s.student_ID JOIN Major m ON m.major_ID=s.major_ID WHERE s.student_ID=?');$q->execute([$sid]);$person=$q->fetch();
        if (!$person) return null;
        if ($person['student_Type']!=='Undergraduate') return ['person'=>$person,'availability'=>'graduate-not-configured'];
        $raw=$this->registrar->student($sid,true);
        if ($raw===null) return ['person'=>$person,'availability'=>'undergraduate-record-needs-review'];
        $student=$this->registrar->student($sid);$student['program']=$person['major_Name'];
        $q=$this->pdo->prepare('SELECT m.major_Name FROM Student_Major sm JOIN Major m ON m.major_ID=sm.major_ID WHERE sm.student_ID=? ORDER BY sm.date_Of_Choice,m.major_ID');$q->execute([$sid]);
        $majors=array_values(array_unique(array_merge([$person['major_Name']],$q->fetchAll(\PDO::FETCH_COLUMN))));
        $q=$this->pdo->prepare('SELECT m.minor_Name FROM Student_Minor sm JOIN Minor m ON m.minor_ID=sm.minor_ID WHERE sm.student_ID=? ORDER BY sm.date_Of_Choice,m.minor_ID');$q->execute([$sid]);$minors=$q->fetchAll(\PDO::FETCH_COLUMN);
        $engine=new CurriculumEvaluator($this->metadata,array_values($this->programs));$majorAudits=[];$minorAudits=[];$allocation=[];
        foreach ($majors as $name) {
            $program=$this->programs[$name]??null;$context=$student;$context['program']=$name;
            if ($name!==$raw['program']) $context['concentration']=null;
            $evaluation=$program?$engine->evaluate($program,$context,$this->institutional):Result::make('review-required','The declared major does not have an approved catalog definition.')+['graduationReady'=>false];
            $majorAudits[]=['name'=>$name,'program'=>$program,'evaluation'=>$evaluation,'concentration'=>$context['concentration']];
            foreach ($evaluation['selectedCourses']??[] as $code) $allocation[$code]=true;
        }
        if ($allocation) {
            $student['majorCourseCodes']=array_keys($allocation);
            $student['majorAllocationApproval']=['status'=>'approved','scope'=>'major-allocation','approvalId'=>'live-audit-'.$sid,'approvedBy'=>'Northbridge evaluated completed-course allocation','approvedAt'=>(new \DateTimeImmutable('now',new \DateTimeZone('America/New_York')))->format('Y-m-d'),'studentId'=>$sid];
        }
        foreach ($minors as $name) {
            $program=$this->programs[$name]??null;
            $minorAudits[]=['name'=>$name,'program'=>$program,'evaluation'=>$program?$engine->evaluate($program,$student,$this->institutional):Result::make('review-required','The declared minor does not have an approved catalog definition.')+['graduationReady'=>false],'concentration'=>null];
        }
        $attempts=[];$inProgress=0;$planned=0;
        foreach ($raw['attempts'] as $a) {
            $a['phase']=$a['status']==='completed'?'completed':($a['termOrder']>$this->currentTerm?'planned':'in-progress');
            $a['title']=$this->course($a['courseCode'],$a['catalogVersion'])['title'];
            if ($a['phase']==='in-progress') $inProgress+=$a['credits'];if ($a['phase']==='planned') $planned+=$a['credits'];$attempts[]=$a;
        }
        $latest=(new Transcript($student['attempts']))->latest();$ge=[];
        foreach (['Diversity: Equity, Inclusion, and Social Justice','Mathematics','Communication Written and Oral','The Arts','US History and Civic Engagement','World History and Global Awareness','World Languages','Natural Sciences','Humanities','Social Sciences'] as $domain) {
            $complete=[];$pending=[];$future=[];
            foreach ($student['geApprovals'] as $approval) if (in_array($domain,$approval['domains'],true) && isset($latest[$approval['courseCode']]) && Transcript::meets($latest[$approval['courseCode']],null)===true) $complete[]=$approval['courseCode'];
            foreach ($attempts as $a) if (in_array($domain,$this->ge[$a['courseCode']]??[],true)) {if ($a['phase']==='in-progress') $pending[]=$a['courseCode'];if ($a['phase']==='planned') $future[]=$a['courseCode'];}
            $attributeCodes=[
                'Diversity: Equity, Inclusion, and Social Justice'=>'DVRS',
                'Mathematics'=>'MATH',
                'Communication Written and Oral'=>'COMW / COMO',
                'The Arts'=>'ARTS',
                'US History and Civic Engagement'=>'USCV',
                'World History and Global Awareness'=>'GLBL',
                'World Languages'=>'WLNG',
                'Natural Sciences'=>'NSCI',
                'Humanities'=>'HUMN',
                'Social Sciences'=>'SOCS',
            ];
            $ge[]=['name'=>$domain,'attributeCode'=>$attributeCodes[$domain]??null,'completed'=>array_unique($complete),'in-progress'=>array_unique($pending),'planned'=>array_unique($future)];
        }
        $progression=[];
        foreach ($majorAudits as $major) {
            if (!$inProgress && !$planned) break;
            $required=[];foreach ($major['program']['groups']??[] as $group) if ($group['type']==='all-courses') foreach ($group['courses'] as $code) $required[$code]=true;
            if (!$required) continue;
            $current=array_filter($attempts,fn($a)=>$a['phase']==='in-progress' && isset($required[$a['courseCode']]));
            if ($current) continue;
            $policies=$major['program']['policies']??[];
            $candidates=array_values(array_filter(array_keys($required),fn($c)=>!isset($latest[$c]) || Transcript::meets($latest[$c],$policies['courseMinimumGrades'][$c]??$policies['minimumGrade']??$policies['biologyMinimumGrade']??null)!==true));
            $next=[];foreach (array_slice($candidates,0,3) as $code) {
                $course=$this->course($code);if (!isset($course['localCourseId'])) continue;
                $verdict=$this->registrar->prerequisite($sid,$course['localCourseId'],$this->nextTerm);
                $next[]=['courseCode'=>$code,'title'=>$course['title'],'prerequisite'=>$verdict,'rule'=>$course['prerequisiteRule']];
            }
            if ($next) $progression[]=['major'=>$major['name'],'courses'=>$next,'targetTerm'=>self::termName($this->nextTerm)];
        }
        return ['entryProficiency'=>require dirname(__DIR__,2).'/config/entry-proficiency.php','availability'=>'available','person'=>$person,'student'=>$student,'majors'=>$majorAudits,'minors'=>$minorAudits,'attempts'=>$attempts,
            'earnedCredits'=>$student['completedStandingCredits'],'inProgressCredits'=>$inProgress,'plannedCredits'=>$planned,'generalEducation'=>$ge,'progression'=>$progression,
            'currentTerm'=>$this->currentTerm,'nextTerm'=>$this->nextTerm,'asOf'=>(new \DateTimeImmutable('now',new \DateTimeZone('America/New_York')))->format('M j, Y')];
    }
}
