<?php
declare(strict_types=1);
namespace Northbridge\Academic;

/** Pure pilot service. Inputs must come from a reviewed registrar adapter, never a request body. */
final class Evidence
{
    public static function approved(mixed $value, string $scope): bool
    {
        return is_array($value) && ($value['status'] ?? null) === 'approved'
            && ($value['scope'] ?? null) === $scope
            && !empty($value['approvalId']) && !empty($value['approvedBy']) && !empty($value['approvedAt']);
    }
}
final class Result
{
    public static function make(string $status, string $reason, array $children = []): array
    { return ['status'=>$status,'reason'=>$reason,'children'=>$children]; }
    public static function combine(array $children, bool $any = false): array
    {
        if (!$children) return self::make('review-required','Empty rule expression.');
        $states=array_column($children,'status');
        $order=$any ? ['satisfied','conditional','review-required','unsatisfied'] : ['unsatisfied','review-required','conditional','satisfied'];
        foreach ($order as $state) if (in_array($state,$states,true)) return self::make($state,$any?'At least one alternative is required.':'Every condition is required.',$children);
        return self::make('review-required','Unknown outcome.',$children);
    }
}
final class Transcript
{
    public const POINTS=['A'=>4.0,'A-'=>3.7,'B+'=>3.3,'B'=>3.0,'B-'=>2.7,'C+'=>2.3,'C'=>2.0,'C-'=>1.7,'D+'=>1.3,'D'=>1.0,'D-'=>0.7,'F'=>0.0];
    public array $attempts;
    public function __construct(array $attempts)
    {
        $seen=[];
        foreach ($attempts as $a) {
            if (!isset($a['courseCode'],$a['status'],$a['termOrder'],$a['attemptOrder'],$a['credits'],$a['origin'])
                || !preg_match('/^[A-Z]{2,8}[0-9]{3,5}[A-Z]?$/',$a['courseCode'])
                || !in_array($a['status'],['completed','in-progress'],true)
                || !in_array($a['origin'],['local','transfer'],true)
                || !is_int($a['termOrder']) || !is_int($a['attemptOrder'])
                || !is_numeric($a['credits']) || $a['credits'] <= 0 || $a['credits'] > 100)
                throw new \InvalidArgumentException('Invalid registrar attempt input.');
            $identity=$a['courseCode'].':'.$a['termOrder'].':'.$a['attemptOrder'];
            if (isset($seen[$identity])) throw new \InvalidArgumentException('Ambiguous duplicate attempt ordering.');
            $seen[$identity]=true;
        }
        usort($attempts,static fn($a,$b)=>[$a['termOrder'],$a['attemptOrder']]<=>[$b['termOrder'],$b['attemptOrder']]);
        $this->attempts=$attempts;
    }
    public static function grade(array $a): ?string
    {
        $grade=$a['grade'] ?? null;
        if ($a['origin']==='transfer' && $grade==='TC') return 'C';
        return is_string($grade)?$grade:null;
    }
    public static function meets(array $a, ?string $minimum): ?bool
    {
        if ($a['origin']==='transfer' && !Evidence::approved($a['equivalenceApproval']??null,'equivalence:'.$a['courseCode'])) return null;
        $grade=self::grade($a);
        if ($minimum!==null && !isset(self::POINTS[$minimum])) return null;
        if ($grade==='CR') return $minimum===null ? true : null;
        if (in_array($grade,['F','NC','I','W','NR'],true)) return false;
        if ($grade===null || !isset(self::POINTS[$grade])) return null;
        return self::POINTS[$grade] >= self::POINTS[$minimum ?? 'D-'];
    }
    /** Latest final attempt governs pilot qualification/GPA. I/W/NR do not erase a prior final result. */
    public function latest(?int $beforeTerm = null): array
    {
        $latest=[];
        foreach ($this->attempts as $a) {
            if ($a['status']!=='completed' || ($beforeTerm!==null && $a['termOrder'] >= $beforeTerm)) continue;
            if (in_array($a['grade']??null,['I','W','NR'],true)) continue;
            $latest[$a['courseCode']]=$a;
        }
        return $latest;
    }
}
final class PrerequisiteEvaluator
{
    public function evaluateCourse(array $record,array $student,?int $targetTermOrder=null,?array $program=null): array
    {
        $student['evaluatedCourseVersion']=$record['courseVersionOrCode'];
        $rule=$record['rule'];
        // These are prerequisite alternatives for the CIS minor only, never transcript equivalencies.
        if (($program['kind']??null)==='minor' && ($program['program']??null)==='Computer Information Science, Minor') {
            $substitutions=$program['policies']['prerequisiteSubstitutions'];
            $replace=function(array $node) use (&$replace,$substitutions): array {
                if (($node['type']??null)==='course') foreach ($substitutions as $s) if ($node['courseCode']===$s['required']) {
                    $alternative=$node;$alternative['courseCode']=$s['allowed'];return ['type'=>'or','rules'=>[$node,$alternative]];
                }
                if (isset($node['rules'])) $node['rules']=array_map($replace,$node['rules']);
                if (isset($node['rule'])) $node['rule']=$replace($node['rule']);
                return $node;
            };
            $rule=$replace($rule);
        }
        return $this->evaluate($rule,$student,$targetTermOrder);
    }
    public function evaluate(array $rule, array $student, ?int $targetTermOrder = null): array
    {
        return $this->node($rule,$student,new Transcript($student['attempts']??[]),$targetTermOrder);
    }
    private function node(array $rule,array $student,Transcript $transcript,?int $target): array
    {
        $type=$rule['type']??'';
        if ($type==='corequisite-condition' || $type==='completed-or-concurrent-condition') {
            if ($target===null) return Result::make('review-required','Target term is required for concurrent-course conditions.');
            $student['allowConcurrentAcademicCondition']=true;
            return $this->node($rule['rule'],$student,$transcript,$target);
        }
        if ($type==='approval') {
            $course=$student['evaluatedCourseVersion']??null;
            if ($course===null) return Result::make('review-required','Permission needs a specific course version.');
            $scope='course-permission:'.$course;$evidence=$student['approvals'][$scope]??null;
            $ok=Evidence::approved($evidence,$scope) && ($evidence['authority']??null)===($rule['authority']??null)
                && ($target===null || (isset($evidence['termOrder']) && $evidence['termOrder'] <= $target));
            return Result::make($ok?'satisfied':'review-required','Scoped course permission from the required authority.');
        }
        if ($type==='and' || $type==='or') {
            $children=[];
            foreach ($rule['rules']??[] as $child) $children[]=$this->node($child,$student,$transcript,$target);
            return Result::combine($children,$type==='or');
        }
        if ($type==='no-course-prerequisites' && ($rule['sourceExplicitlyStatesNone']??false)) return Result::make('satisfied','Source explicitly states no prerequisites.');
        if ($type==='course' || $type==='approved-transfer-course') {
            $code=$rule['courseCode']; $minimum=$rule['minimumGrade']??$rule['minimumBaseGrade']??null;
            $concurrent=($student['allowConcurrentAcademicCondition']??false)===true;
            $latest=$transcript->latest($target===null?null:($concurrent?$target+1:$target)); $a=$latest[$code]??null;
            $state=$a ? Transcript::meets($a,$minimum) : false;
            if ($a && $type==='approved-transfer-course' && $a['origin']!=='transfer') $state=false;
            if ($state===true) return Result::make('satisfied',$code.' meets the completed-course condition.');
            // A later current enrollment may repair a previous failing/low result, but is never earned credit.
            foreach ($transcript->attempts as $pending) {
                if ($pending['courseCode']!==$code || $pending['status']!=='in-progress' || ($a && [$pending['termOrder'],$pending['attemptOrder']]<=[$a['termOrder'],$a['attemptOrder']])) continue;
                if ($type==='approved-transfer-course') continue;
                if ($pending['origin']==='transfer' && !Evidence::approved($pending['equivalenceApproval']??null,'equivalence:'.$code)) return Result::make('review-required','Pending transfer equivalency for '.$code.' requires approval.');
                if ($target===null) return Result::make('review-required','Target term is needed to evaluate '.$code.'.');
                if ($pending['termOrder']<$target || ($concurrent && $pending['termOrder']===$target)) return Result::make('conditional',$code.' is in progress; recheck grade and concurrent enrollment for the target term.');
            }
            return Result::make($state===null?'review-required':'unsatisfied',$code.($state===null?' needs approved equivalency or grade evidence.':' has not met the completed-course condition.'));
        }
        if ($type==='completed-writing-proficiency') {
            $children=[];
            foreach ($rule['components']??[] as $component) $children[]=$this->proficiency($component,$student,$rule['minimumCourseGrade']??'C',$target);
            return Result::combine($children);
        }
        if ($type==='proficiency') {
            $key=['ECI'=>'CompositionI','ECII'=>'CompositionII'][$rule['key']]??$rule['key'];
            return $this->proficiency($key,$student,'C',$target);
        }
        if ($type==='standing-in-program') {
            if (!isset($student['program'],$student['completedStandingCredits'])) return Result::make('review-required','Verified program and completed standing credits required.');
            $required=['Freshman'=>0,'Sophomore'=>32,'Junior'=>57,'Senior'=>88][$rule['standing']]??null;
            if ($required===null) return Result::make('review-required','Unknown standing.');
            return Result::make($student['program']===$rule['program'] && $student['completedStandingCredits'] >= $required?'satisfied':'unsatisfied','Standing uses completed credits; junior means junior or higher in the stated program.');
        }
        return Result::make('review-required',$rule['reason']??'This rule type requires academic review: '.$type);
    }
    private function proficiency(string $key,array $student,string $minimum,?int $target=null): array
    {
        $evidence=$student['proficiencies'][$key]??null;
        if (!Evidence::approved($evidence,'proficiency:'.$key)) return Result::make('review-required','Reviewed '.$key.' proficiency evidence is required.');
        if ($target!==null && (!isset($evidence['termOrder']) || !is_int($evidence['termOrder']) || $evidence['termOrder'] >= $target)) return Result::make('review-required','Prior-term proficiency evidence must be verified.');
        if (($evidence['basis']??null)==='placement' || ($evidence['basis']??null)==='approved-equivalent') return Result::make('satisfied',$key.' satisfied by scoped approved evidence.');
        if (($evidence['basis']??null)==='course' && ($evidence['completed']??false)===true && isset(Transcript::POINTS[$evidence['grade']??''])) {
            return Result::make(Transcript::POINTS[$evidence['grade']]>=Transcript::POINTS[$minimum]?'satisfied':'unsatisfied',$key.' requires a completed grade of '.$minimum.' or better.');
        }
        return Result::make('review-required','Incomplete proficiency evidence for '.$key.'.');
    }
}

/** Shared rules for the two reviewed computing majors; other programs remain gated. */
final class ComputingDegreeEvaluator
{
    public function __construct(private array $courseMetadata) {}
    public function evaluate(array $program,array $student,array $institutional): array
    {
        $names=['Computer and Information Science, B.S.'=>'CIS','Management Information Systems, B.S.'=>'MIS'];
        $label=$names[$program['program']??'']??null;
        if ($label===null || ($program['kind']??'')!=='major') return Result::make('review-required','This service evaluates only the reviewed computing majors.')+['graduationReady'=>false];
        $scope='isolated-'.strtolower($label).'-pilot';
        $transcript=new Transcript($student['attempts']??[]); $latest=$transcript->latest();
        foreach ($transcript->attempts as $a) {
            $metadata=$this->courseMetadata[$a['courseCode']]??null;
            if (!$metadata || $metadata['creditMinimum']===null || $metadata['creditMaximum']===null
                || $a['credits'] < $metadata['creditMinimum'] || $a['credits'] > $metadata['creditMaximum']) {
                if (!Evidence::approved($a['creditApproval']??null,'credit-exception:'.$a['courseCode'])) return Result::make('review-required','Missing or mismatched catalog credits for '.$a['courseCode'].'.')+['graduationReady'=>false,'scope'=>$scope];
            }
        }
        $checks=[];$groups=[];$used=[];$major=[];
        foreach ($program['groups'] as $i=>$group) {
            if (!in_array($group['type'],['all-courses','choose-distinct-courses'],true)) return Result::make('review-required','Unsupported computing-major group.');
            $codes=$group['courses']??$group['eligibleCourses'];$eligible=[];$pending=[];$review=false;
            foreach ($codes as $code) {
                if (isset($used[$code])) continue;
                $a=$latest[$code]??null;
                $meets=$a?Transcript::meets($a,$program['policies']['minimumGrade']):false;
                if ($meets===true) $eligible[$code]=$a;
                elseif ($meets===null) $review=true;
                foreach ($transcript->attempts as $p) if ($p['courseCode']===$code && $p['status']==='in-progress') $pending[$code]=true;
            }
            // Prefer local courses for required institutional residency; all credits remain distinct.
            uasort($eligible,static fn($a,$b)=>[($a['origin']==='local'?0:1),-$a['credits'],$a['courseCode']]<=>[($b['origin']==='local'?0:1),-$b['credits'],$b['courseCode']]);
            $need=$group['type']==='all-courses'?count($codes):$group['minimumCount'];
            $selected=[];$credits=0.0;
            foreach ($eligible as $code=>$a) {
                if (count($selected)>=$need && $credits >= $group['minimumCredits']) break;
                $selected[]=$code;$credits+=(float)$a['credits'];$used[$code]=true;$major[$code]=$a;
            }
            $complete=count($selected)>=$need && $credits >= $group['minimumCredits'];
            $state=$complete?'satisfied':($review?'review-required':'unsatisfied');
            $groups[]=['ordinal'=>$i+1,'status'=>$state,'selectedCourses'=>$selected,'credits'=>$credits,'requiredCredits'=>$group['minimumCredits'],'remainingCourseCount'=>max(0,$need-count($selected)),'inProgressCourses'=>array_keys($pending)];
            $checks['group-'.($i+1)]=Result::make($state,$label.' requirement group '.($i+1).': completed C-or-better courses only.');
        }
        return $this->finish($program,$student,$institutional,$transcript,$checks,$groups,$major,$scope);
    }
    public function finish(array $program,array $student,array $institutional,Transcript $transcript,array $checks,array $groups,array $major,string $scope): array
    {
        $latest=$transcript->latest();
        $earned=0.0;$local=0.0;$transfer=0.0;$upper=0.0;$liberal=0.0;$points=0.0;$gpaCredits=0.0;$attributeReview=false;$creditReview=false;
        $localTranscript=new Transcript(array_values(array_filter($transcript->attempts,static fn($a)=>$a['origin']==='local')));
        foreach ($localTranscript->latest() as $a) {
            $grade=Transcript::grade($a);
            if (isset(Transcript::POINTS[$grade??''])) {$points+=Transcript::POINTS[$grade]*$a['credits'];$gpaCredits+=$a['credits'];}
        }
        $earnedCodes=[];
        foreach ($latest as $code=>$a) {
            $meets=Transcript::meets($a,null);
            if ($meets===null) {$creditReview=true;continue;}
            if (!$meets) {
                // A prior passed attempt followed by a failure has an unresolved earned-credit treatment.
                foreach ($transcript->attempts as $old) if ($old['courseCode']===$code && $old['status']==='completed' && Transcript::meets($old,null)===true) $creditReview=true;
                continue;
            }
            $earned+=$a['credits'];$earnedCodes[$code]=$a;
            if ($a['origin']==='local') $local+=$a['credits']; else $transfer+=$a['credits'];
            if (!Evidence::approved($a['attributeApproval']??null,'attributes:'.$code) || !isset($a['upperDivision'],$a['liberalArts'])) {$attributeReview=true;continue;}
            if ($a['upperDivision']===true) $upper+=$a['credits'];
            if ($a['liberalArts']===true) $liberal+=$a['credits'];
        }
        $degree=$program['degreeRequirements']+['maximumTransferCredits'=>$institutional['maximumTransferCredits']];
        $minimum=static fn(float $value,float $needed,string $label,bool $unknown=false):array=>Result::make($value >= $needed?'satisfied':($unknown?'review-required':'unsatisfied'),$label.': '.$value.' / '.$needed.'.');
        $checks['total-credits']=$minimum($earned,$degree['minimumTotalCredits'],'Earned credits',$creditReview);
        $checks['institutional-residency']=$minimum($local,$degree['minimumInstitutionalResidencyCredits'],'Local credits',$creditReview);
        $checks['upper-division']=$minimum($upper,$degree['minimumUpperDivisionCredits'],'Upper-division credits',$attributeReview);
        $checks['liberal-arts']=$minimum($liberal,$degree['minimumLiberalArtsCredits'],'Liberal-arts credits',$attributeReview);
        $checks['transfer-limit']=Result::make($transfer > $degree['maximumTransferCredits']?'unsatisfied':($creditReview?'review-required':'satisfied'),'Approved transfer credits: '.$transfer.' / maximum '.$degree['maximumTransferCredits'].'.');
        $gpa=$gpaCredits>0?$points/$gpaCredits:null;
        $checks['institutional-gpa']=$gpa===null?Result::make('review-required','No graded local GPA evidence.'):$minimum($gpa,$degree['minimumInstitutionalGPA'],'Latest-attempt institutional GPA');
        $majorLocalUpper=0.0;$majorLocalUpperCount=0;$majorUpper=0.0;$majorAttributeReview=false;
        foreach ($major as $code=>$a) {
            if (!Evidence::approved($a['attributeApproval']??null,'attributes:'.$code) || !isset($a['upperDivision'])) {$majorAttributeReview=true;continue;}
            if ($a['upperDivision']===true) {
                $majorUpper+=$a['credits'];
                if ($a['origin']==='local') {$majorLocalUpper+=$a['credits'];$majorLocalUpperCount++;}
            }
        }
        $checks['upper-major']=$minimum($majorUpper,$institutional['minimumUpperDivisionMajorCredits'],'Upper-division major credits',$majorAttributeReview);
        if (!isset($student['isTransferStudent']) || !is_bool($student['isTransferStudent'])) $checks['transfer-major-residency']=Result::make('review-required','Transfer admission status required.');
        elseif ($student['isTransferStudent'] && isset($program['policies']['transferMajorResidency'])) {
            $r=$program['policies']['transferMajorResidency'];
            $checks['transfer-major-residency']=Result::combine([$minimum($majorLocalUpper,$r['credits'],'Local upper major credits',$majorAttributeReview),$minimum($majorLocalUpperCount,$r['minimumCourses'],'Local upper major course count',$majorAttributeReview)]);
        }
        $prerequisites=new PrerequisiteEvaluator();
        $checks['writing']=$prerequisites->evaluate(['type'=>'completed-writing-proficiency','components'=>['CompositionI','CompositionII'],'minimumCourseGrade'=>'C'],$student);
        $math=[];
        foreach ($institutional['proficiencies']['math']['courses'] as $code) if (isset($latest[$code])) $math[] = Transcript::meets($latest[$code],'C');
        $checks['math-proficiency']=Result::make(in_array(true,$math,true)||Evidence::approved($student['mathProficiencyApproval']??null,'proficiency:math')?'satisfied':(in_array(null,$math,true)?'review-required':'unsatisfied'),'Math proficiency requires a qualifying C-or-better course or approved evidence.');
        $checks['general-education']=$this->generalEducation($earnedCodes,$student,$institutional);
        $overall=Result::combine(array_values($checks));
        // A degree audit never treats in-progress work as completion.
        return $overall+['checks'=>$checks,'groups'=>$groups,'totals'=>['earnedCredits'=>$earned,'localCredits'=>$local,'transferCredits'=>$transfer,'upperDivisionCredits'=>$upper,'liberalArtsCredits'=>$liberal,'institutionalGPA'=>$gpa], 'graduationReady'=>$overall['status']==='satisfied','scope'=>$scope];
    }
    private function generalEducation(array $earned,array $student,array $institutional): array
    {
        $waiver=$student['geWaiver']??null;
        if (Evidence::approved($waiver,'general-education-waiver') && in_array($waiver['awardType']??null,['AA','AS'],true)
            && in_array($waiver['institutionSystem']??null,['SUNY','CUNY'],true) && !empty($waiver['awardEvidenceReference'])) return Result::make('satisfied','Approved award-based GE waiver; local proficiency checks remain separate.');
        $domains=['Diversity: Equity, Inclusion, and Social Justice','Mathematics','Communication Written and Oral','The Arts','US History and Civic Engagement','World History and Global Awareness','World Languages','Natural Sciences','Humanities','Social Sciences'];
        $covered=[];$distinct=[];
        foreach ($student['geApprovals']??[] as $approval) {
            if (!Evidence::approved($approval,'ge:'.($approval['courseCode']??'')) || empty($approval['cohortAndEffectiveDateReviewed']) || empty($approval['sourceReference'])) continue;
            $code=$approval['courseCode'];
            if (!isset($earned[$code])) continue;
            foreach ($approval['domains']??[] as $domain) if (in_array($domain,$domains,true)) {$covered[$domain]=true;$distinct[$code]=$earned[$code]['credits'];}
        }
        $missing=array_values(array_diff($domains,array_keys($covered)));$credits=array_sum($distinct);
        return Result::make(!$missing && $credits >= $institutional['generalEducation']['minimumDistinctCredits']?'satisfied':'review-required',
            'Reviewed GE evidence: '.$credits.' distinct credits; uncovered domains: '.implode(', ',$missing).'.');
    }
}

/** Program-specific entry points prevent using a different program's rules by accident. */
final class CisDegreeEvaluator
{
    public function __construct(private array $courseMetadata) {}
    public function evaluate(array $program,array $student,array $institutional): array
    {
        if (($program['program']??'')!=='Computer and Information Science, B.S.') return Result::make('review-required','This pilot evaluates only the CIS B.S.')+['graduationReady'=>false];
        return (new ComputingDegreeEvaluator($this->courseMetadata))->evaluate($program,$student,$institutional);
    }
}
final class MisDegreeEvaluator
{
    public function __construct(private array $courseMetadata) {}
    public function evaluate(array $program,array $student,array $institutional): array
    {
        if (($program['program']??'')!=='Management Information Systems, B.S.') return Result::make('review-required','This pilot evaluates only the MIS B.S.')+['graduationReady'=>false];
        return (new ComputingDegreeEvaluator($this->courseMetadata))->evaluate($program,$student,$institutional);
    }
}
