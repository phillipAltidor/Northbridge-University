<?php
declare(strict_types=1);
namespace Northbridge\Academic;
/** Read-only repository requiring an explicit immutable catalog ID. No latest-version guessing. */
final class Repository
{
    public function __construct(private \PDO $pdo,private int $catalogId) {}
    public function program(string $name,string $kind='major'): array
    {
        $q=$this->pdo->prepare('SELECT definition FROM NB_ProgramVersion WHERE catalog_id=? AND name=? AND kind=?');
        $q->execute([$this->catalogId,$name,$kind]);return $this->decode($q->fetchColumn());
    }
    public function prerequisite(string $versionKey): array
    {
        $q=$this->pdo->prepare('SELECT p.definition FROM NB_Prerequisite p JOIN NB_CourseVersion c ON c.id=p.course_version_id WHERE c.catalog_id=? AND c.version_key=?');
        $q->execute([$this->catalogId,$versionKey]);return $this->decode($q->fetchColumn());
    }
    public function policy(string $key): array
    {
        $q=$this->pdo->prepare('SELECT definition FROM NB_AcademicPolicy WHERE catalog_id=? AND policy_key=?');
        $q->execute([$this->catalogId,$key]);return $this->decode($q->fetchColumn());
    }
    public function courseMetadata(): array
    {
        $policy=$this->pdo->prepare("SELECT definition FROM NB_AcademicPolicy WHERE catalog_id=? AND policy_key='evaluator-capabilities'");
        $policy->execute([$this->catalogId]);$raw=$policy->fetchColumn();$aliases=is_string($raw)?(json_decode($raw,true)['canonicalLookupAliases']??[]):[];
        $q=$this->pdo->prepare('SELECT version_key,course_code,credit_min,credit_max FROM NB_CourseVersion WHERE catalog_id=?');
        $q->execute([$this->catalogId]);$result=[];
        foreach ($q->fetchAll() as $row) {
            if ($row['version_key']!==($aliases[$row['course_code']]??$row['course_code'])) continue;
            $result[$row['course_code']]=['creditMinimum'=>$row['credit_min']===null?null:(float)$row['credit_min'],'creditMaximum'=>$row['credit_max']===null?null:(float)$row['credit_max']];
        }
        return $result;
    }
    public function programs(): array
    {
        $q=$this->pdo->prepare('SELECT definition FROM NB_ProgramVersion WHERE catalog_id=? ORDER BY id');
        $q->execute([$this->catalogId]);return array_map(fn($raw)=>$this->decode($raw),$q->fetchAll(\PDO::FETCH_COLUMN));
    }
    private function decode(mixed $raw): array
    {
        if (!is_string($raw)) throw new \RuntimeException('Requested academic version is not loaded.');
        return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    }
}
