#!/usr/bin/env python3
"""Bind enabled test evaluators to exact runtime code and staged program definitions."""
import hashlib,json
from pathlib import Path
r=Path(__file__).resolve().parent
root=r.parents[2]
files=['includes/academic/evaluator.php','includes/academic/curriculum-evaluator.php']
programs=json.loads((r/'rules/curricula.json').read_text())['programs']
output={'status':'isolated-test-only-not-portal-activation','evaluatorVersion':'curriculum-v1','runtimeSHA256':{p:hashlib.sha256((root/p).read_bytes()).hexdigest() for p in files},'programs':[{'program':p['program'],'kind':p['kind'],'evaluator':'curriculum-v1'} for p in programs],'scope':'Individual major/minor evaluation; minor overlap requires a reviewed major allocation. Missing source/evidence conditions remain review-required.','registrationIntegrated':False}
output['canonicalLookupAliases']=json.loads((r/'rules/all-course-prerequisites.json').read_text())['canonicalLookupAliases']
(r/'rules/evaluator-capabilities.json').write_text(json.dumps(output,indent=2)+'\n')
print('Bound test evaluator capabilities to',len(programs),'programs and exact runtime code hashes.')
