<?php
declare(strict_types=1);
// Synthetic reviewed registrar evidence only. No copied people, accounts or historical rows.
function cis_approval(string $scope,array $extra=[]): array
{
    return $extra+['status'=>'approved','scope'=>$scope,'approvalId'=>'synthetic-'.$scope,'approvedBy'=>'synthetic-reviewer','approvedAt'=>'2026-11-07'];
}
function cis_attempt(string $code,string $grade='B',array $extra=[]): array
{
    return $extra+['courseCode'=>$code,'status'=>'completed','grade'=>$grade,'credits'=>4,'origin'=>'local','termOrder'=>1,'attemptOrder'=>1,'upperDivision'=>true,'liberalArts'=>true,'attributeApproval'=>cis_approval('attributes:'.$code)];
}
function cis_writing(): array
{
    return ['CompositionI'=>cis_approval('proficiency:CompositionI',['basis'=>'course','grade'=>'B','completed'=>true,'termOrder'=>1]),'CompositionII'=>cis_approval('proficiency:CompositionII',['basis'=>'course','grade'=>'C','completed'=>true,'termOrder'=>1])];
}
function cis_complete_fixture(array $program): array
{
    $codes=[];
    foreach ($program['groups'] as $group) {
        $options=$group['courses']??array_slice($group['eligibleCourses'],0,$group['minimumCount']);
        $codes=array_merge($codes,$options);
    }
    // 17 major courses (68) + 13 distinct general electives (52) = 120.
    $extra=['AS1152','AS1282','AS1512','AS2020','AS2112','AS2122','AS2202','AS2252','AS2262','AS2263','AS2300','AS2640','AS2700'];
    $domains=['Diversity: Equity, Inclusion, and Social Justice','Mathematics','Communication Written and Oral','The Arts','US History and Civic Engagement','World History and Global Awareness','World Languages','Natural Sciences','Humanities','Social Sciences'];
    $ge=[];
    // These synthetic approvals exercise evaluation, not real GE mappings or actual advisement.
    foreach ($domains as $i=>$domain) $ge[]=cis_approval('ge:'.$extra[$i],['courseCode'=>$extra[$i],'domains'=>[$domain],'cohortAndEffectiveDateReviewed'=>true,'sourceReference'=>'synthetic-fixture-only']);
    return ['fixtureId'=>'synthetic-cis-complete','attempts'=>array_map('cis_attempt',array_merge($codes,$extra)), 'proficiencies'=>cis_writing(),'program'=>'CIS','completedStandingCredits'=>120,'isTransferStudent'=>false,'geApprovals'=>$ge];
}
