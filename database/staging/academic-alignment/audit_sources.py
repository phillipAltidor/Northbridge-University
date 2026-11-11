#!/usr/bin/env python3
"""Audit retained academic sources. No database/network access or source mutations.

Run from any directory: python3 database/staging/academic-alignment/audit_sources.py
Writes source-audit.json using existing aggregate database baseline, not live data.
Counts/dependency scans are review aids, never executable curriculum approval.
"""
import collections
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parent

def read(name):
    return json.loads((ROOT / name).read_text())

def references(text):
    return {a.replace(' ', '') + b for a, b in re.findall(
        r'\b([A-Z](?:\s?[A-Z]){1,2})\s*(\d{4}[A-Z]?)\b', text)}

sources = read('reviewed-course-extraction.json')
records = [dict(r, sourcePDF=d['sourcePDF']) for d in sources for r in d['records']]
by_code = collections.defaultdict(list)
for record in records:
    by_code[record['code']].append(record)
known = set(by_code)
missing_credits, variable, no_prereq, long_descriptions, dependencies = [], [], [], [], []
for row in records:
    text = row['sourceText']
    credit = re.search(r'Credits:\s*(\d+(?:\.\d+)?)(?:\s*[-‑–]\s*(\d+(?:\.\d+)?))?', text)
    if not credit:
        missing_credits.append(row['code'])
    elif credit[2]:
        variable.append({'code': row['code'], 'min': credit[1], 'max': credit[2]})
    description = re.split(r'\n(?:Prerequisites?|Corequisites?|When Offered:|Note:)', text)[0]
    if len(description) > 500:
        long_descriptions.append(row['code'])
    prerequisite = re.search(r'Prerequisites?\s*\n(.*?)(?=\n(?:When Offered:|Note:|Corequisite|Prerequisite/Corequisite)|\Z)', text, re.S)
    if not prerequisite:
        no_prereq.append(row['code'])
    else:
        absent = references(prerequisite[1]) - known
        if absent:
            dependencies.append({'course': row['code'], 'missingReferences': sorted(absent), 'sourceText': prerequisite[1]})
programs = read('program-review-inputs.json')
assert len(programs) == 24 and len({p['program'] for p in programs}) == 24
coverage = []
for program in programs:
    coverage.append({'program': program['program'], 'sourcePDF': program['sourcePDF'],
                     'missingLiteralReferences': sorted(references('\n'.join(program['pages'])) - known)})
ge_missing = {}
for domain in read('general-education-source-tables.json'):
    codes = set()
    for row in domain.get('tableRows', []):
        codes.update(references(row[0]))
    ge_missing[domain['domain']] = sorted(codes - known)
previous = read('source-audit.json')
result = {
    'status': 'planning-audit-not-migration', 'reviewDate': '2026-11-07',
    'programSources': {'majorPDFs': 12, 'minorPDFs': 12},
    'courseCounts': {'sourceEntries': len(records), 'distinctCodes': len(known),
                     'prefixes': len({re.match('[A-Z]+', c)[0] for c in known}),
                     'duplicateCodes': {c: len(v) for c, v in by_code.items() if len(v) > 1},
                     'earlierExtractionCandidateCount': 899,
                     'note': previous['courseCounts']['note']},
    'missingLabeledCredits': sorted(set(missing_credits)), 'variableCreditCourses': variable,
    'entriesWithoutPrerequisiteLabel': sorted(set(no_prereq)),
    'descriptionsExceedingCurrent500CharacterField': len(set(long_descriptions)),
    'unresolvedPrerequisiteReferences': dependencies, 'programReferenceCoverage': coverage,
    'GEApprovalReferencesNotInCollectedDescriptions': ge_missing,
    'sourceIntegrityWarnings': [d['provenanceWarning'] for d in sources if d.get('provenanceWarning')],
    'databaseBaseline': previous['databaseBaseline'],
    'limitations': ['Literal references include ranges, aliases and placeholders; review before adding courses.',
                   'Prerequisite scan is not a verified AND/OR tree or cycle analysis.',
                   'No-prerequisite-label entries are not approved as having no prerequisites.',
                   'Description length is a source-text heuristic, not a display-text migration result.']}
assert all(r['code'] != 'XX9990' for r in records)
assert 'ED5940B' in known
assert len(by_code['BS2400']) == 1 and len(by_code['BU4762']) == 1
(ROOT / 'source-audit.json').write_text(json.dumps(result, indent=2) + '\n')
print(f"Audited {len(programs)} programs, {len(records)} course entries / {len(known)} distinct codes. No database access.")
