<?php
declare(strict_types=1);
namespace Northbridge\Academic;
require_once __DIR__.'/evaluator.php';

/** Bounded, distinct-course allocation. An exhausted search is review-required, never completion. */
final class CurriculumAllocator
{
    private int $steps=0;
    private array $records=[];
    private array $student=[];
    private array $program=[];
    public function __construct(private array $metadata,private int $searchLimit=30000) {}
    public function allocations(array $program,array $student,array $records): \Generator
    {
        $this->steps=0;$this->program=$program;$this->student=$student;$this->records=$records;
        yield from $this->sequence($program['groups'],[],[]);
    }
    private function tick(): void
    {
        if (++$this->steps>$this->searchLimit) throw new \OverflowException('Allocation search limit reached; academic review required.');
    }
    private function sequence(array $nodes,array $used,array $plans): \Generator
    {
        $this->tick();
        if (!$nodes) {yield ['plans'=>$plans,'used'=>$used];return;}
        $node=array_shift($nodes);
        foreach ($this->node($node,$used) as $plan) {
            $next=$used;
            foreach ($plan['codes'] as $code) $next[$code]=true;
            yield from $this->sequence($nodes,$next,array_merge($plans,[$plan]));
        }
    }
    private function plan(array $codes,string $status='satisfied',array $extra=[]): array
    {
        return $extra+['codes'=>array_values(array_unique($codes)),'status'=>$status,'credits'=>$this->credits($codes),'units'=>count($codes),'children'=>[]];
    }
    private function credits(array $codes): float
    {
        return array_sum(array_map(fn($c)=>(float)($this->records[$c]['credits']??0),array_unique($codes)));
    }
    private function grade(array $a,array $node): ?bool
    {
        $code=$a['courseCode'];$p=$this->program['policies'];
        $minimum=$node['minimumGrade']??$p['courseMinimumGrades'][$code]??$p['minimumGrade']??null;
        if (isset($p['biologyMinimumGrade'])) {
            $minimum=$p['biologyMinimumGrade'];
            if (!str_starts_with($code,'BS') && isset($p['nonBiologyLowGradeException'])) {
                $normal=Transcript::meets($a,$minimum);
                if ($normal===false && Transcript::meets($a,$p['nonBiologyLowGradeException']['minimumGrade'])===true) return Evidence::approved($this->student['approvals']['biology-low-grade-exception']??null,'biology-low-grade-exception')?true:null;
            }
        }
        return Transcript::meets($a,$minimum);
    }
    public static function classified(array $a,string $classification): ?bool
    {
        if (!Evidence::approved($a['attributeApproval']??null,'attributes:'.$a['courseCode']) || !isset($a['classifications'])) return null;
        return in_array($classification,$a['classifications'],true);
    }
    private function selector(array $a,array $selector): ?bool
    {
        $code=$a['courseCode'];preg_match('/^([A-Z]+)(\d+)/',$code,$m);
        $subjects=(array)($selector['subject']??[]);
        if ($subjects && !in_array($m[1],$subjects,true)) return false;
        if (in_array($code,$selector['excludeCourses']??[],true)) return false;
        if (!Evidence::approved($a['attributeApproval']??null,'attributes:'.$code)) return null;
        $level=(int)($a['level']??0);
        if (!$level) return null;
        if (isset($selector['levels']) && !in_array((int)(floor($level/1000)*1000),$selector['levels'],true)) return false;
        if (isset($selector['minimumLevel']) && $level<$selector['minimumLevel']) return false;
        if (isset($selector['classification'])) return self::classified($a,$selector['classification']);
        return true;
    }
    private function units(array $node,array $used,bool &$unknown): array
    {
        $type=$node['type'];$options=[];
        if (in_array($type,['all-courses','choose-distinct-courses','choose-credits','choose-from-union'],true)) $options=$node['courses']??$node['eligibleCourses']??[];
        if ($type==='all-course-versions') $options=array_map(static fn($c)=>['type'=>'version','courseCode'=>$c['courseCode'],'catalogVersion'=>$c['catalogVersion']],$node['courses']);
        if ($type==='choose-distinct-units') $options=$node['options'];
        $units=[];
        foreach ($options as $option) {
            $codes=is_string($option)?[$option]:($option['courses']??(isset($option['courseCode'])?[$option['courseCode']]:[]));
            if (($option['type']??null)==='approved-independent-study') {
                foreach ($this->records as $code=>$a) {
                    if (isset($used[$code]) || self::classified($a,'independent-study')!==true) continue;
                    if (Evidence::approved($a['independentStudyApproval']??null,'independent-study:'.$code)
                        && ($a['independentStudyApproval']['homeDepartment']??null)===($this->student['homeDepartment']??null)
                        && !empty($this->student['homeDepartment'])) $options[]=$code;
                    else $unknown=true;
                }
                // Scoped approved independent study remains optional; never create XX9990.
                foreach ($this->records as $code=>$a) if (!isset($used[$code]) && self::classified($a,'independent-study')===true && Evidence::approved($a['independentStudyApproval']??null,'independent-study:'.$code) && !empty($this->student['homeDepartment']) && ($a['independentStudyApproval']['homeDepartment']??null)===$this->student['homeDepartment'] && $this->grade($a,$node)===true) $units[$code]=[$code];
                continue;
            }
            $ok=(bool)$codes;
            foreach ($codes as $code) {
                if (!isset($this->metadata[$code])) {$unknown=true;$ok=false;continue;}
                if (isset($used[$code]) || !isset($this->records[$code])) {$ok=false;continue;}
                $a=$this->records[$code];$grade=$this->grade($a,$node);
                if ($grade===null) $unknown=true;
                if ($grade!==true) $ok=false;
                if (is_array($option) && isset($option['catalogVersion']) && ($a['catalogVersion']??null)!==$option['catalogVersion']) {$unknown=true;$ok=false;}
                if (($node['localCourseRequired']??false) && $a['origin']!=='local') $ok=false;
            }
            if ($ok) $units[implode('+',$codes)]=$codes;
        }
        $selectors=$type==='choose-by-selector'?[$node]:($node['selectors']??[]);
        foreach ($selectors as $selector) foreach ($this->records as $code=>$a) {
            if (isset($used[$code])) continue;
            $match=$this->selector($a,$selector);
            if ($match===null) {$unknown=true;continue;}
            if (!$match) continue;
            if (($node['localCourseRequired']??false) && $a['origin']!=='local') continue;
            $grade=$this->grade($a,$node);
            if ($grade===null) $unknown=true;
            if ($grade===true) $units[$code]=[$code];
        }
        uasort($units,function($a,$b) {
            $rank=fn($codes)=>[count(array_filter($codes,fn($c)=>$this->records[$c]['origin']==='transfer')),-$this->credits($codes),implode('+',$codes)];
            return $rank($a)<=>$rank($b);
        });
        return array_values($units);
    }
    private function combinations(array $units,int $needed,float $credits,array $selected=[],array $codes=[],int $start=0): \Generator
    {
        $this->tick();
        if (count($selected)>=$needed && $this->credits($codes)>=$credits) {yield ['codes'=>$codes,'units'=>count($selected),'unitCodes'=>$selected];return;}
        for ($i=$start;$i<count($units);$i++) {
            if (array_intersect($codes,$units[$i])) continue;
            yield from $this->combinations($units,$needed,$credits,array_merge($selected,[$units[$i]]),array_merge($codes,$units[$i]),$i+1);
        }
    }
    private function node(array $node,array $used): \Generator
    {
        $this->tick();$type=$node['type']??'';
        if ($type==='reviewed-evidence') {
            $key=$node['key'];$ok=Evidence::approved($this->student['approvals'][$key]??null,$key);
            yield $this->plan([],$ok?'satisfied':'review-required',['reason'=>'Reviewed evidence: '.$key]);return;
        }
        if ($type==='choose-one-path') {
            $any=false;
            foreach ($node['paths'] as $name=>$path) foreach ($this->node($path,$used) as $p) {
                $p['path']=$name;if ($p['status']==='satisfied') $any=true;yield $p;
            }
            if (!$any) yield $this->plan([],'unsatisfied',['reason'=>'No complete path.']);
            return;
        }
        if ($type==='selected-concentration') {
            $name=$this->student['concentration']??null;$concentration=$this->program['concentrations'][$name??'']??null;
            if (!$concentration) {yield $this->plan([],'review-required',['reason'=>'Select one offered concentration.']);return;}
            $parent=['type'=>'allocation-group','groups'=>$concentration['groups'],'minimumCredits'=>$concentration['minimumCredits']??$node['minimumCredits']];
            if (isset($concentration['minimumGrade'])) foreach ($parent['groups'] as &$g) $g['minimumGrade']=$concentration['minimumGrade'];unset($g);
            foreach ($this->node($parent,$used) as $p) {$p['concentration']=$name;yield $p;}return;
        }
        if ($type==='choose-distinct-domain-courses') {
            if (($node['coursesPerDomain']??1)!==1) {yield $this->plan([],'review-required',['reason'=>'Unsupported courses-per-domain condition.']);return;}
            $domainUnits=[];$unknown=false;
            foreach ($node['domains'] as $name=>$codes) $domainUnits[$name]=$this->units(['type'=>'choose-distinct-courses','eligibleCourses'=>$codes],$used,$unknown);
            $domainNames=array_keys($domainUnits);$found=false;
            foreach ($this->domainChoices($domainNames,$domainUnits,$node['minimumDomains']) as $p) {$found=true;yield $this->plan($p['codes'],'satisfied',['domains'=>$p['domains']]);}
            if (!$found) yield $this->plan([],$unknown?'review-required':'unsatisfied',['reason'=>'Distinct foundation domains required.']);return;
        }
        if ($type==='allocation-group') {
            foreach ($this->sequence($node['groups'],$used,[]) as $allocation) {
                $children=$allocation['plans'];$codes=array_values(array_diff(array_keys($allocation['used']),array_keys($used)));
                $states=array_map(static fn($p)=>Result::make($p['status'],''),$children);$status=Result::combine($states)['status'];
                $units=array_sum(array_map(static fn($p)=>$p['units'],$children));
                $minCredits=(float)($node['minimumCredits']??0);$minUnits=$node['minimumCount']??$node['minimumUnitCount']??0;
                $extras=[['codes'=>[],'units'=>0,'unitCodes'=>[]]];
                if ($status==='satisfied' && ($this->credits($codes)<$minCredits || $units<$minUnits) && (($node['allowAdditionalEligibleCoursesToMeetCredits']??false)||($node['allowAdditionalEligibleUnitsToMeetCredits']??false))) {
                    $pool=[];$unknown=false;
                    foreach ($node['groups'] as $child) $pool=array_merge($pool,$this->units($child,$allocation['used'],$unknown));
                    $extras=$this->combinations($pool,max(0,$minUnits-$units),max(0,$minCredits-$this->credits($codes)));
                }
                $found=false;
                foreach ($extras as $extra) {
                    $found=true;$all=array_merge($codes,$extra['codes']);$s=$status;$totalUnits=$units+$extra['units'];
                    if ($this->credits($all)<$minCredits || $totalUnits<$minUnits) $s='unsatisfied';
                    $checks=[];
                    if (isset($node['approvedCategory'])) foreach ($all as $c) {
                        $a=$this->records[$c];$one=self::classified($a,'Media Studies');$two=self::classified($a,'Media Production');
                        $checks[]=Result::make($one===true||$two===true?'satisfied':($one===null||$two===null?'review-required':'unsatisfied'),'Approved Media category.');
                    }
                    foreach ($node['constraints']??[] as $constraint) {
                        if ($constraint['type']!=='minimum-lab-bearing-electives' || ($constraint['scope']??'')!=='additional-electives') {$checks[]=Result::make('review-required','Unsupported allocation constraint.');continue;}
                        $last=$children[count($children)-1]['codes'];$scope=array_merge($last,$extra['codes']);$labs=0;$unknown=false;
                        foreach ($scope as $c) {
                            $a=$this->records[$c];
                            if (!Evidence::approved($a['attributeApproval']??null,'attributes:'.$c)||!isset($a['labBearing'])) $unknown=true;
                            elseif ($a['labBearing']) $labs++;
                        }
                        $checks[]=Result::make($labs >= $constraint['minimum']?'satisfied':($unknown?'review-required':'unsatisfied'),'Additional lab-bearing elective count.');
                    }
                    if ($checks) $s=Result::combine(array_merge([Result::make($s,'')],$checks))['status'];
                    yield $this->plan($all,$s,['children'=>$children,'units'=>$totalUnits,'extraCourses'=>$extra['codes'],'constraints'=>$checks]);
                }
                if (!$found) yield $this->plan($codes,'unsatisfied',['children'=>$children,'units'=>$units,'reason'=>'Insufficient additional distinct credits/units.']);
            }
            return;
        }
        $supported=['all-courses','all-course-versions','choose-distinct-courses','choose-by-selector','choose-from-union','choose-credits','choose-distinct-units'];
        if (!in_array($type,$supported,true)) {yield $this->plan([],'review-required',['reason'=>'Unsupported group type: '.$type]);return;}
        $unknown=false;$units=$this->units($node,$used,$unknown);
        $all=in_array($type,['all-courses','all-course-versions'],true);
        $needed=$all?count($node['courses']):($node['minimumCount']??($type==='choose-credits'?0:1));
        $found=false;
        foreach ($this->combinations($units,$needed,(float)($node['minimumCredits']??0)) as $p) {
            $checks=[];
            foreach ($node['maximumSubjectCourses']??[] as $subject=>$max) $checks[]=Result::make(count(array_filter($p['codes'],static fn($c)=>str_starts_with($c,$subject)))<=$max?'satisfied':'unsatisfied','Subject limit.');
            if (isset($node['minimumUpperDivisionUnits'])) {
                $upper=0;$missing=false;
                foreach ($p['unitCodes'] as $unit) {
                    $good=true;foreach ($unit as $c) {
                        $a=$this->records[$c];
                        if (!Evidence::approved($a['attributeApproval']??null,'attributes:'.$c)||!isset($a['upperDivision'])) {$missing=true;$good=false;}
                        elseif (!$a['upperDivision']) $good=false;
                    }
                    if ($good) $upper++;
                }
                $checks[]=Result::make($upper >= $node['minimumUpperDivisionUnits']?'satisfied':($missing?'review-required':'unsatisfied'),'Upper-division elective units.');
            }
            $state=$checks?Result::combine($checks)['status']:'satisfied';$found=true;yield $this->plan($p['codes'],$state,['units'=>$p['units'],'unitCodes'=>$p['unitCodes']]);
        }
        if (!$found) yield $this->plan(array_values(array_unique(array_merge([],...$units))),$unknown?'review-required':'unsatisfied',['reason'=>'Missing distinct courses, approved metadata, grades, units or credits.']);
    }
    private function domainChoices(array $names,array $units,int $need,array $codes=[],array $selected=[],int $start=0): \Generator
    {
        $this->tick();if (count($selected)>=$need) {yield ['codes'=>$codes,'domains'=>$selected];return;}
        for ($i=$start;$i<count($names);$i++) foreach ($units[$names[$i]] as $unit) if (!array_intersect($codes,$unit)) yield from $this->domainChoices($names,$units,$need,array_merge($codes,$unit),array_merge($selected,[$names[$i]]),$i+1);
    }
}

/** All selected programs. Incomplete evidence remains an explicit review outcome. */
final class CurriculumEvaluator
{
    private array $registry=[];
    public function __construct(private array $metadata,array $programs,private int $searchLimit=30000)
    {
        foreach ($programs as $p) $this->registry[$p['kind'].'|'.$p['program']]=$p;
    }
    public function evaluate(array $program,array $student,array $institutional): array
    {
        $key=($program['kind']??'').'|'.($program['program']??'');
        if (!isset($this->registry[$key]) || $program!=$this->registry[$key]) return Result::make('review-required','Program definition does not match the reviewed package.')+['graduationReady'=>false,'programComplete'=>false];
        $transcript=new Transcript($student['attempts']??[]);$versions=[];
        foreach ($transcript->attempts as $a) {
            $code=$a['courseCode'];$version=$a['catalogVersion']??'unspecified';$versions[$code][$version]=true;
            $m=$this->metadata[$code]??null;
            if (!$m || $m['creditMinimum']===null || $m['creditMaximum']===null || $a['credits']<$m['creditMinimum'] || $a['credits']>$m['creditMaximum']) {
                if (!Evidence::approved($a['creditApproval']??null,'credit-exception:'.$code)) return Result::make('review-required','Missing or mismatched catalog credits for '.$code)+['graduationReady'=>false,'programComplete'=>false];
            }
        }
        foreach ($versions as $code=>$v) if (count($v)>1) return Result::make('review-required','Multiple historical meanings for '.$code.' need a reviewed equivalency/repeat allocation.')+['graduationReady'=>false,'programComplete'=>false];
        $records=$transcript->latest();
        // Psychology department highest-attempt policy is separate from institutional GPA/latest credits.
        if (isset($program['policies']['departmentalGradeRule'])) foreach ($transcript->attempts as $a) {
            if ($a['status']!=='completed' || Transcript::meets($a,null)!==true) continue;
            $code=$a['courseCode'];$old=$records[$code]??null;
            if (!$old || (Transcript::POINTS[Transcript::grade($a)]??-1)>(Transcript::POINTS[Transcript::grade($old)]??-1)) $records[$code]=$a;
        }
        $best=null;$bestRank=null;$examined=0;
        try {
            $allocator=new CurriculumAllocator($this->metadata,$this->searchLimit);
            foreach ($allocator->allocations($program,$student,$records) as $allocation) {
                $examined++;$checks=[];$groups=[];$selected=[];
                foreach ($allocation['plans'] as $i=>$plan) {
                    $groups[]=$plan+['ordinal'=>$i+1];
                    foreach ($plan['codes'] as $code) if (isset($records[$code])) $selected[$code]=$records[$code];
                }
                // Degree credits remain actual earned credits. A program may apply only a capped portion.
                foreach (['maximumInternshipCreditsAppliedToMajor'=>'internship','maximumIndependentStudyCreditsAppliedToMajor'=>'independent-study'] as $field=>$category) if (isset($program['policies'][$field])) {
                    $remaining=$program['policies'][$field];
                    foreach ($selected as &$a) if (CurriculumAllocator::classified($a,$category)===true) {
                        $a['earnedCredits']=$a['credits'];$applied=min($a['credits'],max(0,$remaining));$remaining-=$applied;$a['credits']=$applied;
                    }
                    unset($a);
                }
                foreach ($groups as $i=>&$plan) {
                    $plan=$this->recredit($plan,$program['groups'][$i],$selected,$program,$student);
                    $checks['group-'.($i+1)]=Result::make($plan['status'],$plan['reason']??'Distinct-course requirement group.');
                }
                unset($plan);
                $checks+=$this->policies($program,$student,$transcript,$selected,$groups);
                $programStatus=Result::combine(array_values($checks))['status'];
                if ($program['kind']==='major') {
                    $audit=(new ComputingDegreeEvaluator($this->metadata))->finish($program,$student,$institutional,$transcript,$checks,$groups,$selected,'isolated-all-programs');
                } else {
                    $audit=Result::combine(array_values($checks))+['checks'=>$checks,'groups'=>$groups,'totals'=>['programCredits'=>array_sum(array_column($selected,'credits'))],'graduationReady'=>false,'scope'=>'isolated-minor-only'];
                }
                $audit['programComplete']=$programStatus==='satisfied';$audit['programStatus']=$programStatus;$audit['selectedCourses']=array_keys($selected);$audit['appliedProgramCredits']=array_map(static fn($a)=>$a['credits'],$selected);
                // Prefer a completion, then an unresolved but otherwise possible path, then fewer failures.
                $states=array_column($audit['checks'],'status');
                $rank=[['satisfied'=>0,'review-required'=>1,'conditional'=>2,'unsatisfied'=>3][$audit['status']],count(array_filter($states,static fn($s)=>$s==='unsatisfied')),count(array_filter($states,static fn($s)=>$s==='review-required'))];
                if ($best===null || $rank<$bestRank) {$best=$audit;$bestRank=$rank;}
                if ($audit['status']==='satisfied') break;
            }
        } catch (\OverflowException $error) {
            return Result::make('review-required',$error->getMessage())+['graduationReady'=>false,'programComplete'=>false,'bestPartialAudit'=>$best,'allocationsExamined'=>$examined];
        }
        return ($best??(Result::make('review-required','No allocation found.')+['graduationReady'=>false,'programComplete'=>false]))+['allocationsExamined'=>$examined];
    }
    private function recredit(array $plan,array $node,array $selected,array $program,array $student): array
    {
        if (($node['type']??'')==='choose-one-path' && isset($plan['path'])) $node=$node['paths'][$plan['path']];
        if (($node['type']??'')==='selected-concentration' && isset($plan['concentration'])) {
            $c=$program['concentrations'][$plan['concentration']];$node=['type'=>'allocation-group','groups'=>$c['groups'],'minimumCredits'=>$c['minimumCredits']??$node['minimumCredits']];
        }
        $plan['credits']=array_sum(array_map(static fn($code)=>(float)($selected[$code]['credits']??0),$plan['codes']));
        if ($plan['status']==='satisfied' && $plan['credits']<($node['minimumCredits']??0)) {$plan['status']='unsatisfied';$plan['reason']='Applied credit cap leaves this requirement short.';}
        if ($plan['status']==='satisfied' && array_filter($plan['codes'],static fn($c)=>($selected[$c]['credits']??0)<=0)) {$plan['status']='unsatisfied';$plan['reason']='A zero-credit allocation cannot fill a required course slot.';}
        foreach ($plan['children']??[] as $i=>$child) if (isset($node['groups'][$i])) {
            $plan['children'][$i]=$this->recredit($child,$node['groups'][$i],$selected,$program,$student);
            if ($plan['children'][$i]['status']!=='satisfied' && $plan['status']==='satisfied') $plan['status']=$plan['children'][$i]['status'];
        }
        return $plan;
    }
    private function policies(array $program,array $student,Transcript $transcript,array $selected,array $groups): array
    {
        $p=$program['policies'];$checks=[];$credits=(float)array_sum(array_column($selected,'credits'));$count=count($selected);
        $minimum=static fn($value,$needed,$label)=>Result::make($value>=$needed?'satisfied':'unsatisfied',$label.': '.$value.' / '.$needed);
        $maximum=static fn($value,$limit,$label)=>Result::make($value<=$limit?'satisfied':'unsatisfied',$label.': '.$value.' / maximum '.$limit);
        $known=['minimumGrade','courseMinimumGrades','majorCredits','minimumCredits','minimumCourseCount','majorCourseCount','minimumMajorCourseUnits','departmentMinimumCredits','coreCredits','accountingMinimumCredits','accountingBusinessMinimumCredits','transferMajorResidency','minimumCreditsOutsideMajor','maximumCoursesSharedWithMajor','maximumTransferCourses','prerequisiteSubstitutions','biologyMinimumGrade','nonBiologyLowGradeException','maximumAttemptsPerBiologyCourse','maximumCoursesAtGradeCMinus','repeatPolicy','distribution','departmentalGradeRule','fieldExperienceApprovalRequired','minimumMajorGPA','minimumMinorGPA','maximumLowGradeCourses','maximumVACourses','minimumLocalProductionCourses','maximumInternshipCreditsAppliedToMajor','maximumIndependentStudyCreditsAppliedToMajor','maximumExtraLowerDivisionCourses','lowerDivisionExemptions'];
        foreach (array_diff(array_keys($p),$known) as $unknown) $checks['unsupported-policy-'.$unknown]=Result::make('review-required','Unsupported program policy: '.$unknown);
        foreach (['majorCredits','minimumCredits','departmentMinimumCredits'] as $field) if (isset($p[$field])) $checks[$field]=$minimum($credits,$p[$field],'Selected program credits');
        foreach (['minimumCourseCount','majorCourseCount','minimumMajorCourseUnits'] as $field) if (isset($p[$field])) $checks[$field]=$minimum($count,$p[$field],'Distinct counted courses');
        if (isset($p['coreCredits'])) $checks['coreCredits']=$minimum(array_sum(array_column(array_slice($groups,0,3),'credits')),$p['coreCredits'],'Core credits');
        if (isset($p['accountingMinimumCredits'])) $checks['accountingMinimumCredits']=$minimum(array_sum(array_column(array_slice($groups,0,2),'credits')),$p['accountingMinimumCredits'],'Accounting credits');
        if (isset($p['accountingBusinessMinimumCredits'])) $checks['accountingBusinessMinimumCredits']=$minimum(array_sum(array_column(array_slice($groups,0,3),'credits')),$p['accountingBusinessMinimumCredits'],'Accounting/business credits');
        $transfers=count(array_filter($selected,static fn($a)=>$a['origin']==='transfer'));
        if (isset($p['maximumTransferCourses'])) $checks['maximumTransferCourses']=$maximum($transfers,$p['maximumTransferCourses'],'Transfer courses applied');
        if (isset($p['minimumCreditsOutsideMajor'])||isset($p['maximumCoursesSharedWithMajor'])) {
            if (!Evidence::approved($student['majorAllocationApproval']??null,'major-allocation') || !isset($student['majorCourseCodes'])) $checks['major-overlap']=Result::make('review-required','Reviewed major allocation required to check minor overlap.');
            else {
                $shared=array_intersect(array_keys($selected),$student['majorCourseCodes']);$outside=$credits-array_sum(array_map(fn($c)=>$selected[$c]['credits'],$shared));
                if (isset($p['minimumCreditsOutsideMajor'])) $checks['outside-major']=$minimum($outside,$p['minimumCreditsOutsideMajor'],'Minor credits outside major');
                if (isset($p['maximumCoursesSharedWithMajor'])) $checks['maximumCoursesSharedWithMajor']=$maximum(count($shared),$p['maximumCoursesSharedWithMajor'],'Courses shared with major');
            }
        }
        foreach ($groups as $group) if (isset($group['concentration'])) {
            $c=$program['concentrations'][$group['concentration']];$rows=array_intersect_key($selected,array_flip($group['codes']));$local=0;$tCredits=0;$tCount=0;
            foreach ($rows as $a) {if ($a['origin']==='local') $local+=$a['credits'];else {$tCredits+=$a['credits'];$tCount++;}}
            if (isset($c['minimumLocalTrackCredits'])) $checks['track-residency']=$minimum($local,$c['minimumLocalTrackCredits'],'Local track credits');
            if (isset($c['maximumTransferCourses'])) $checks['track-transfer-count']=$maximum($tCount,$c['maximumTransferCourses'],'Concentration transfer courses');
            if (isset($c['maximumTransferCredits'])) $checks['track-transfer-credits']=$maximum($tCredits,$c['maximumTransferCredits'],'Concentration transfer credits');
        }
        if (isset($p['nonBiologyLowGradeException'])) {
            $low=count(array_filter($selected,static fn($a)=>!str_starts_with($a['courseCode'],'BS') && Transcript::meets($a,'C')!==true));
            $checks['biology-low-grades']=$maximum($low,$p['nonBiologyLowGradeException']['maximumCourses'],'Non-biology low-grade exceptions');
        }
        if (isset($p['maximumAttemptsPerBiologyCourse'])) foreach ($selected as $code=>$a) if (str_starts_with($code,'BS')) {
            $attempts=count(array_filter($transcript->attempts,static fn($x)=>$x['courseCode']===$code && $x['status']==='completed'));
            $checks['biology-attempts-'.$code]=$maximum($attempts,$p['maximumAttemptsPerBiologyCourse'],'Biology attempts');
        }
        if (isset($p['maximumCoursesAtGradeCMinus'])) $checks['english-low-grades']=$maximum(count(array_filter($selected,static fn($a)=>Transcript::grade($a)==='C-')),$p['maximumCoursesAtGradeCMinus'],'C-minus courses');
        if (isset($p['repeatPolicy'])) foreach ($selected as $code=>$a) {
            $attempts=count(array_filter($transcript->attempts,static fn($x)=>$x['courseCode']===$code && $x['status']==='completed'));
            if ($attempts>2) $checks['english-repeat-'.$code]=Result::make(Evidence::approved($student['approvals']['english-repeat:'.$code]??null,'english-repeat:'.$code)?'satisfied':'review-required','Chair approval for more than two attempts.');
        }
        if (isset($p['distribution'])) $checks['english-distribution']=$this->englishDistribution($selected);
        if (isset($p['departmentalGradeRule'])) {
            $alternatives=$p['departmentalGradeRule']['alternatives']??[];
            if (($p['departmentalGradeRule']['type']??'')!=='or' || count($alternatives)!==2 || ($alternatives[0]['type']??'')!=='major-gpa' || ($alternatives[1]['type']??'')!=='minimum-grade-in-each-course') {
                $checks['psychology-grade-alternative']=Result::make('review-required','Unsupported departmental grade alternatives.');
            } else {
            $gpa=$this->gpa($selected,$student);$allC=true;
            foreach ($selected as $a) if (Transcript::meets($a,$alternatives[1]['minimumGrade'])!==true) $allC=false;
            $checks['psychology-grade-alternative']=Result::make($allC||($gpa!==null && $gpa>=$alternatives[0]['minimum'])?'satisfied':($gpa===null?'review-required':'unsatisfied'),'Psychology highest-attempt GPA or minimum grade in every counted course.');
            }
        }
        foreach (['minimumMajorGPA','minimumMinorGPA'] as $key) if (isset($p[$key])) {
            $gpa=$this->gpa($selected,$student);$checks[$key]=$gpa===null?Result::make('review-required','Departmental GPA needs reviewed graded/transfer evidence.'):$minimum($gpa,$p[$key],'Departmental GPA');
        }
        if (isset($p['maximumLowGradeCourses'])) {
            $rule=$p['maximumLowGradeCourses'];$low=count(array_filter($selected,static fn($a)=>in_array(Transcript::grade($a),$rule['grades'],true)));
            $checks['media-low-grade-count']=$maximum($low,$rule['count'],'Low-grade courses');
            if ($low) $checks['media-low-grade-approval']=Result::make(Evidence::approved($student['approvals']['media-low-grade']??null,'media-low-grade')?'satisfied':'review-required','Chair approval for a low grade.');
        }
        if (isset($p['maximumVACourses'])) $checks['media-va-limit']=$maximum(count(array_filter(array_keys($selected),static fn($c)=>str_starts_with($c,'VA'))),$p['maximumVACourses'],'VA courses applied');
        if (isset($p['minimumLocalProductionCourses'])) {
            $local=0;$unknown=false;
            foreach ($selected as $a) if ($a['origin']==='local') {
                $match=CurriculumAllocator::classified($a,'Media Production');if ($match===null) $unknown=true;elseif ($match) $local++;
            }
            $checks['local-production']=$local>=$p['minimumLocalProductionCourses']?Result::make('satisfied','Local production requirement met.'):Result::make($unknown?'review-required':'unsatisfied','Local production requirement not met.');
        }
        foreach (['maximumInternshipCreditsAppliedToMajor'=>'internship','maximumIndependentStudyCreditsAppliedToMajor'=>'independent-study'] as $field=>$classification) if (isset($p[$field])) {
            $sum=0;$unknown=false;foreach ($selected as $a) {$match=CurriculumAllocator::classified($a,$classification);if ($match===null) $unknown=true;elseif ($match) $sum+=$a['credits'];}
            $checks[$field]=$sum>$p[$field]?$maximum($sum,$p[$field],$classification.' credits'):($unknown?Result::make('review-required','Reviewed '.$classification.' classification required.'):$maximum($sum,$p[$field],$classification.' credits'));
        }
        if (isset($p['maximumExtraLowerDivisionCourses'])) {
            $lower=0;$unknown=false;foreach ($selected as $code=>$a) {
                if (in_array($code,$p['lowerDivisionExemptions'],true)) continue;
                if (!Evidence::approved($a['attributeApproval']??null,'attributes:'.$code)||!isset($a['level'])) $unknown=true;
                elseif ($a['level']<3000) $lower++;
            }
            $checks['extra-lower-division']=$lower>$p['maximumExtraLowerDivisionCourses']?$maximum($lower,$p['maximumExtraLowerDivisionCourses'],'Extra lower-level courses'):($unknown?Result::make('review-required','Reviewed levels needed for media lower-level limit.'):$maximum($lower,$p['maximumExtraLowerDivisionCourses'],'Extra lower-level courses'));
        }
        if ($p['fieldExperienceApprovalRequired']??false) $checks['education-field-approval']=Result::make(Evidence::approved($student['approvals']['education-field-experience']??null,'education-field-experience')?'satisfied':'review-required','Reviewed field placement/completion approval.');
        // Structured evidence gates retain the conditions that cannot be inferred from course grades alone.
        if ($program['program']==='Public Health, B.S.') {
            $a=$student['approvals']['applied-practice-90-hours']??[];
            $checks['practice-hours']=Result::make(!Evidence::approved($a,'applied-practice-90-hours')||!isset($a['hours'])?'review-required':($a['hours']>=90?'satisfied':'unsatisfied'),'At least 90 documented practice hours.');
        }
        if ($program['program']==='Psychology, B.S.') $checks['psychology-science-plan']=$this->psychologyScience($student,$transcript);
        if ($program['program']==='Childhood Education 1-6, B.S.') {
            $a=$student['approvals']['school-of-education-admission']??[];
            $complete=isset($a['completedCreditsAtAdmission'],$a['gpaAtAdmission'],$a['ED3900Grade'],$a['formalAdmissionReference'],$a['requiredProficienciesReviewed'],$a['introCoursesReviewed']);
            $ok=$complete && $a['completedCreditsAtAdmission']>=32 && $a['gpaAtAdmission']>=3.0 && (Transcript::POINTS[$a['ED3900Grade']]??-1)>=2.0 && $a['requiredProficienciesReviewed']===true && $a['introCoursesReviewed']===true && !empty($a['formalAdmissionReference']);
            $checks['education-admission-snapshot']=Result::make(!$complete?'review-required':($ok?'satisfied':'unsatisfied'),'Formal Education admission snapshot: credits, GPA, prerequisites and proficiency.');
        }
        return $checks;
    }
    private function gpa(array $selected,array $student): ?float
    {
        $points=0;$credits=0;
        $hasTransfer=(bool)array_filter($selected,static fn($a)=>$a['origin']==='transfer');
        $policy=$student['approvals']['departmental-transfer-gpa-policy']??null;
        if ($hasTransfer && (!Evidence::approved($policy,'departmental-transfer-gpa-policy')||!in_array($policy['method']??null,['include','exclude'],true))) return null;
        foreach ($selected as $a) {
            if ($a['origin']==='transfer' && $policy['method']==='exclude') continue;
            $grade=Transcript::grade($a);if (!isset(Transcript::POINTS[$grade??''])) return null;
            $points+=Transcript::POINTS[$grade]*$a['credits'];$credits+=$a['credits'];
        }
        return $credits?$points/$credits:null;
    }
    private function englishDistribution(array $selected): array
    {
        $before=0;$unknown=false;$covered=[];$analysis=[];
        foreach ($selected as $code=>$a) {
            $m=CurriculumAllocator::classified($a,'literature-before-1800-or-US-before-1900');if ($m===null) $unknown=true;elseif ($m) $before++;
            foreach (['British-literature','US-literature','World-literature'] as $attribute) {
                $m=CurriculumAllocator::classified($a,$attribute);if ($m===null) $unknown=true;
                elseif ($m) {if (in_array($code,['EL3500','EL3510'],true) && $attribute!=='World-literature') $analysis[$attribute]=true;else $covered[$attribute]=true;}
            }
        }
        $missing=array_diff(['British-literature','US-literature','World-literature'],array_keys($covered));
        $possible=count($missing)===0 || (count($missing)===1 && isset($analysis[array_values($missing)[0]]));
        return Result::make($before>=2 && $possible?'satisfied':($unknown?'review-required':'unsatisfied'),'Two historical courses plus British, U.S. and World coverage; analysis group cannot fill both British and U.S.');
    }
    private function psychologyScience(array $student,Transcript $transcript): array
    {
        $plan=$student['approvals']['psychology-bs-math-science-plan']??[];
        if (!Evidence::approved($plan,'psychology-bs-math-science-plan') || !isset($plan['mathCourse'],$plan['scienceSemesters']) || ($plan['advisorDeadlineReviewed']??false)!==true) return Result::make('review-required','Reviewed math/four-semester science plan and advisor deadline required.');
        $best=[];foreach ($transcript->attempts as $a) {
            if ($a['status']!=='completed'||Transcript::meets($a,null)!==true) continue;
            $code=$a['courseCode'];if (!isset($best[$code])||(Transcript::POINTS[Transcript::grade($a)]??-1)>(Transcript::POINTS[Transcript::grade($best[$code])]??-1)) $best[$code]=$a;
        }
        $math=$best[$plan['mathCourse']]??null;
        if (!$math || !str_starts_with($math['courseCode'],'MA') || CurriculumAllocator::classified($math,'precalculus-or-higher')!==true) return Result::make('review-required','Reviewed qualifying precalculus-or-higher math required.');
        $selected=[$math['courseCode']=>$math];$terms=[];
        foreach ($plan['scienceSemesters'] as $semester) {
            if (!isset($semester['termOrder'],$semester['courseCodes']) || isset($terms[$semester['termOrder']]) || !$semester['courseCodes']) return Result::make('unsatisfied','Four distinct science semesters are required.');
            $terms[$semester['termOrder']]=true;
            foreach ($semester['courseCodes'] as $code) {
                $a=$best[$code]??null;
                if (isset($selected[$code])) return Result::make('unsatisfied','Science credits cannot be reused across semesters.');
                if (!$a || $a['termOrder']!==$semester['termOrder'] || CurriculumAllocator::classified($a,'qualifying-psychology-bs-science')!==true) return Result::make('review-required','Science course/semester and non-major exclusion need reviewed evidence.');
                $selected[$code]=$a;
            }
        }
        if (count($terms)<4) return Result::make('unsatisfied','Four qualifying science semesters required.');
        $allC=true;foreach ($selected as $a) if (Transcript::meets($a,'C')!==true) $allC=false;
        $gpa=$this->gpa($selected,$student);
        return Result::make($allC||($gpa!==null&&$gpa>=2.5)?'satisfied':($gpa===null?'review-required':'unsatisfied'),'Math/science highest-grade GPA >=2.5 OR C in every plan course.');
    }
}
