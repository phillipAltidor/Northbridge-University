<?php
declare(strict_types=1);
namespace Northbridge\Academic;
require_once __DIR__.'/evaluator.php';

/** Registration checks from server-owned, versioned rebuild records only. */
final class RebuildPrerequisiteEvaluator
{
    private Transcript $transcript;
    public function __construct(private array $student,private array $properties,private int $target,private string $courseVersion)
    {
        $this->transcript=new Transcript($student['attempts']??[]);
    }
    private function evidence(string $scope,?string $authority=null): array
    {
        $e=$this->student['registrarEvidence'][$scope]??[];
        $binding=($e['scope']??null)===$scope && isset($e['studentId'],$e['termOrder']) && is_int($e['studentId'])
            && $e['studentId']===($this->student['studentId']??null) && is_int($e['termOrder']) && $e['termOrder']<=$this->target
            && ($authority===null || ($e['authority']??null)===$authority);
        $approved=$binding && Evidence::approved($e,$scope);
        $conditional=$binding && ($e['status']??null)==='conditional' && ($e['fictionalSimulation']??false)===true
            && !empty($e['approvalId']) && !empty($e['approvedBy']) && !empty($e['approvedAt']);
        return Result::make($approved?'satisfied':($conditional?'conditional':'review-required'),'Scoped academic evidence: '.$scope);
    }
    private function course(string $code,?string $minimum,bool $concurrent=false,?string $version=null,bool $transferOnly=false): array
    {
        $latest=$this->transcript->latest($concurrent?$this->target+1:$this->target);
        $a=$latest[$code]??null;$state=false;
        if ($a) {
            if ($version!==null && ($a['catalogVersion']??null)!==$version) $state=null;
            elseif ($transferOnly && $a['origin']!=='transfer') $state=false;
            elseif ($a['origin']==='transfer' && ($a['grade']??null)==='TC' && $minimum!==null) {
                $proof=$a['equivalenceApproval']??[];$base=$proof['minimumBaseGrade']??null;
                $state=Evidence::approved($proof,'equivalence:'.$code) && isset(Transcript::POINTS[$base??''],Transcript::POINTS[$minimum])
                    ? Transcript::POINTS[$base]>=Transcript::POINTS[$minimum] : null;
            } else $state=Transcript::meets($a,$minimum);
            if ($state===true) return Result::make('satisfied',$code.' completed at the required grade.');
        }
        if (!$transferOnly) foreach ($this->transcript->attempts as $pending) {
            if ($pending['courseCode']!==$code || $pending['status']!=='in-progress' || $pending['origin']!=='local') continue;
            if ($version!==null && ($pending['catalogVersion']??null)!==$version) continue;
            if ($a && [$pending['termOrder'],$pending['attemptOrder']]<=[$a['termOrder'],$a['attemptOrder']]) continue;
            if ($pending['termOrder']<$this->target || ($concurrent && $pending['termOrder']===$this->target)) return Result::make('conditional',$code.' needs its final grade/concurrent enrollment rechecked.');
        }
        return Result::make($state===null?'review-required':'unsatisfied',$code.' completed-course requirement.');
    }
    private function credits(?string $subject=null): array
    {
        $completed=0.0;$pending=0.0;$latest=$this->transcript->latest($this->target);$counted=[];
        foreach ($latest as $code=>$a) if (($subject===null || preg_replace('/\d.*$/','',$code)===$subject) && Transcript::meets($a,null)===true) {$completed+=$a['credits'];$counted[$code]=true;}
        foreach ($this->transcript->attempts as $a) if ($a['status']==='in-progress' && $a['termOrder']<$this->target && !isset($counted[$a['courseCode']]) && ($subject===null || preg_replace('/\d.*$/','',$a['courseCode'])===$subject)) {$pending+=$a['credits'];$counted[$a['courseCode']]=true;}
        return [$completed,$pending];
    }
    private function minimumCredits(float $minimum,?string $subject=null): array
    {
        [$earned,$pending]=$this->credits($subject);
        return Result::make($earned>=$minimum?'satisfied':($earned+$pending>=$minimum?'conditional':'unsatisfied'),'Prior earned credits: '.$earned.' / '.$minimum.'.');
    }
    private function proficiency(string $key,bool $concurrent=false,string $minimum='C'): array
    {
        // Sufficient completed-course routes explicitly listed as Math
        // Proficiency in rules/general-education-mappings.json. This does not
        // grant placement, course credit, or equivalencies for other math codes.
        if ($key==='MATH_PROFICIENCY') return Result::combine(array_map(fn($c)=>$this->course($c,$minimum,$concurrent),
            ['MA1020','MA2000','MA2050','MA2080','MA2090','MA2300','MA2310','MA2320','MA3030']),true);
        $codes=['ECI'=>['EL1000'],'CompositionI'=>['EL1000'],'ECII'=>['EL2216'],'CompositionII'=>['EL2216'],'EMS'=>['EL1000','EL2216'],'writing'=>['EL1000','EL2216']][$key]??null;
        if ($codes===null) return Result::make('review-required','Unknown proficiency token: '.$key);
        return Result::combine(array_map(fn($c)=>$this->course($c,$minimum,$concurrent),$codes));
    }
    public function evaluate(array $rule,bool $concurrent=false): array
    {
        $t=$rule['type']??'';
        if ($t==='and'||$t==='or') return Result::combine(array_map(fn($n)=>$this->evaluate($n,$concurrent),$rule['rules']??[]),$t==='or');
        if (in_array($t,['corequisite-condition','completed-or-concurrent-condition'],true)) return $this->evaluate($rule['rule'],true);
        if (in_array($t,['course','approved-transfer-course'],true)) return $this->course($rule['courseCode'],$rule['minimumBaseGrade']??$rule['minimumGrade']??null,$concurrent,$rule['catalogVersion']??null,$t==='approved-transfer-course');
        if ($t==='no-course-prerequisites') return Result::make(($rule['sourceExplicitlyStatesNone']??false)||($rule['basis']??'')==='No prerequisite or corequisite is listed in the supplied complete catalog entry.'?'satisfied':'review-required','Reviewed complete course entry.');
        if (in_array($t,['proficiency','completed-or-concurrent-proficiency'],true)) return $this->proficiency($rule['key'],$concurrent||$t==='completed-or-concurrent-proficiency');
        if ($t==='completed-writing-proficiency') return Result::combine(array_map(fn($k)=>$this->proficiency($k,$concurrent,$rule['minimumCourseGrade']??'C'),$rule['components']??['CompositionI','CompositionII']));
        if ($t==='scoped-evidence') return $this->evidence($rule['scope'],$rule['authority']??null);
        if ($t==='approval') return $this->evidence('course-permission:'.$this->courseVersion,$rule['authority']??null);
        if ($t==='minimum-earned-credits'||$t==='earned-subject-credits') return $this->minimumCredits((float)$rule['minimum'],$rule['subject']??null);
        if ($t==='standing-in-program') {
            $tag=['Computer and Information Science, B.S.'=>'CIS','Management Information Systems, B.S.'=>'MIS'][$this->student['program']??'']??($this->student['program']??'');
            $required=['Freshman'=>0,'Sophomore'=>32,'Junior'=>57,'Senior'=>88][$rule['standing']]??null;
            if ($tag!==$rule['program']) return Result::make('unsatisfied','Required declared program.');
            return $required===null?Result::make('review-required','Unknown standing threshold.'):$this->minimumCredits($required);
        }
        if ($t==='minimum-institutional-gpa') {
            $points=0.0;$credits=0.0;
            foreach ($this->transcript->latest($this->target) as $a) if ($a['origin']==='local' && isset(Transcript::POINTS[$a['grade']??''])) {$credits+=$a['credits'];$points+=Transcript::POINTS[$a['grade']]*$a['credits'];}
            return Result::make(!$credits?'review-required':($points/$credits>=$rule['minimum']?'satisfied':'unsatisfied'),'Prior institutional GPA threshold.');
        }
        if ($t==='subject-course-count') {
            $codes=[];foreach ($this->transcript->attempts as $a) {preg_match('/^([A-Z]+)(\d+)/',$a['courseCode'],$m);if ($m[1]===$rule['subject'] && (int)$m[2]>=($rule['minimumLevel']??0) && (int)$m[2]<=($rule['maximumLevel']??99999)) $codes[$a['courseCode']]=true;}
            $good=0;$pending=0;foreach ($codes as $code=>$_) {$state=$this->course($code,$rule['minimumGrade']??null,$concurrent)['status'];$good+=$state==='satisfied'?1:0;$pending+=$state==='conditional'?1:0;}
            return Result::make($good>=$rule['minimumCourses']?'satisfied':($good+$pending>=$rule['minimumCourses']?'conditional':'unsatisfied'),'Distinct qualifying subject courses.');
        }
        if ($t==='program-field') {
            foreach ($rule['eligibleFields'] as $field) foreach ([$this->student['program']??'',$this->student['minor']??''] as $name) if (str_contains($name,$field)) return Result::make('satisfied','Eligible declared field.');
            return Result::make('unsatisfied','Eligible declared field required.');
        }
        if ($t==='completed-laboratory-science') {
            $checks=[];foreach ($this->transcript->attempts as $a) if ($this->properties[$a['courseCode']]['labBearing']??false) $checks[$a['courseCode']]=$this->course($a['courseCode'],null,$concurrent);
            if ($checks) return Result::combine(array_values($checks),true);
            return Result::make('review-required','Explicit laboratory science evidence required.');
        }
        return Result::make('review-required',$rule['reason']??'Unresolved academic source condition.');
    }
}
