"""Source-linked interpretations for the replacement-data rehearsal only.

These resolve explicit course lists the earlier parser could not handle. They
do not turn absent prerequisite text, permission, placement, or ambiguous prose
into an unconditional pass. The immutable reviewed catalog remains unchanged.
"""
import hashlib
import json
from pathlib import Path


def course(code, grade='C'):
    return {'type': 'course', 'courseCode': code, 'minimumGrade': grade}


def both(*rules):
    return {'type': 'and', 'rules': list(rules)}


def either(*codes):
    return {'type': 'or', 'rules': [course(c) for c in codes]}


def writing(key='ECII'):
    return {'type': 'proficiency', 'key': key}


def interpretations(original):
    expected = json.loads(Path(__file__).with_name('rebuild_rule_source_hashes.json').read_text())
    replacements = {}
    biology = ['BS3450', 'BS3520', 'BS3530', 'BS4400', 'BS4440', 'BS4470']
    for code in biology + ['BS3400']:
        nodes = [course(c) for c in ['BS2400', 'BS2401', 'BS2410', 'BS2411']]
        if code == 'BS3400':
            nodes += [course('CP2120'), course('CP2121')]
        replacements[code] = both(*nodes, writing())
    for code, required in {
        'BU3071': ['MA2000'], 'BU3250': ['MA2080', 'MA2000'],
        'CP2120': ['MA1020'], 'CP2130': ['CP2120'], 'CP2131': ['CP2121'],
        'CP3300': ['CP2130'], 'CP3302': ['CP2130', 'CP2131'], 'CP4490': ['CP3310'],
        'MA4030': ['MA3030'], 'MA5320': ['MA2320', 'MA3520'],
    }.items():
        replacements[code] = both(*(course(c) for c in required))
    replacements.update({
        'BU4160': both(course('MA2080'), course('MA2000'), either('BU3010', 'BU3071'), course('BU3250')),
        'BU5115': both(either('BU3010', 'BU3071'), course('MA2000')),
        'ED3820': both(course('EL1000', 'B'), course('EL2216', 'B')),
        'EL3920': writing(),
        'EL4800': both(writing('EMS'), course('EL3500', None), course('EL3510', None)),
        'EL5010': both(writing('EMS'), course('EL5000', None)),
        'HI3006': course('EL1000'),
        'MA5120': both(course('MA3160'), course('MA3520'), writing()),
        'PX2000': course('MA2090'),
        'PX2100': either('PX2000', 'PX2200'),
        'PX2300': either('PX2000', 'PX2200'),
        'PX2200': both(course('MA2090'), {'type': 'corequisite-condition', 'rule': course('MA2310', None)}),
        'PX3000': both(either('PX2200', 'PX2000'), course('MA2310', None)),
        'PX3200': both(either('PX2200', 'PX2000'), course('MA2310', None)),
        'PX3600': both(course('PX3000'), course('MA2320', None)),
        'PX3700': both(either('PX2300', 'PX2100'), course('MA3330', None)),
        'PX3800': both(course('PX3000'), course('MA2320', None)),
        'PX3900': both(either('PX2301', 'PX2101'), course('MA2320', None)),
        'PX4100': both(either('PX2300', 'PX2100'), course('CP2130'), course('MA2320')),
        'PX4200': both(course('PX3000'), course('MA2320', None)),
        'PY4010': both(course('PY2010'), course('PY3010'), writing('EMS'),
                       either('PY3410', 'PY3420', 'PY3610', 'PY3620', 'PY3215', 'PY3230', 'PY3240',
                              'PY3310', 'PY3311', 'PY3510', 'PY3570', 'PY2530', 'PY2720', 'PY3710')),
    })
    result = {}
    for code, rule in replacements.items():
        source = original.get(code)
        if source is None or source.get('type') != 'review-required' or not source.get('sourceText'):
            raise ValueError('Expected explicit source prerequisite text for ' + code)
        digest = hashlib.sha256(json.dumps(source, sort_keys=True).encode()).hexdigest()
        if digest != expected.get(code):
            raise ValueError('Source prerequisite changed; re-review interpretation for ' + code)
        result[code] = {'rule': rule, 'originalRuleSHA256': digest,
                        'sourceText': source['sourceText'], 'status': 'explicit-source-interpretation-for-rebuild-rehearsal',
                        'alternativesAndPlacement': 'Uses the listed completed-course route; no placement or equivalence is fabricated.'}
    return result


def recover_reviewed_entries(original):
    """Recover conditions verified in full entries, including unlabeled lab corequisites."""
    directory = Path(__file__).resolve().parents[1] / 'staging/academic-alignment'
    documents = json.loads((directory / 'reviewed-course-extraction.json').read_text())
    records = {r['code']: r for d in documents for r in d['records']}
    expected = json.loads(Path(__file__).with_name('rebuild_recovered_source_hashes.json').read_text())
    no_listed_prerequisites = ['AS1152', 'AS1512', 'AS2020', 'AS2802', 'AS2901', 'AS3010', 'AS3247',
        'CP2025', 'CP2070', 'HI2511', 'HI3001', 'HI3002', 'HI3600', 'MC2020', 'MC2802', 'MC2901',
        'MD1000', 'MD1110', 'MD2350', 'MD3010', 'PH2000', 'SY1500']
    rules = {c: {'type': 'no-course-prerequisites', 'basis': 'No prerequisite or corequisite is listed in the supplied complete catalog entry.'}
             for c in no_listed_prerequisites}
    for lab, lecture in {'CP2121': 'CP2120', 'PX2001': 'PX2000', 'PX2101': 'PX2100', 'PX2201': 'PX2200', 'PX2301': 'PX2300'}.items():
        rules[lab] = {'type': 'completed-or-concurrent-condition', 'rule': course(lecture, None)}
    rules.update({
        'CS2000': {'type': 'no-course-prerequisites', 'sourceExplicitlyStatesNone': True},
        'BU4820': course('BU3800', None),
        'CP2800': writing('MATH_PROFICIENCY'),
        'BU5190': both(course('BU3800', None), course('BU4110', None), course('BU4762', None), {'type': 'minimum-earned-credits', 'minimum': 88}),
        'EL5000': both(writing('EMS'), {'type': 'minimum-earned-credits', 'minimum': 88}),
        'PH5900': both(writing('EMS'), course('PH2000', None), course('PH3600', None),
                       {'type': 'completed-or-concurrent-condition', 'rule': course('PH4800', None)}),
        'BU4851': both(course('BU3800', None), {'type': 'program-field',
                          'eligibleFields': ['Accounting', 'Business Administration', 'Digital Design Marketing', 'Finance', 'Marketing', 'Women’s and Gender Studies']}),
    })
    result = {}
    for code, rule in rules.items():
        evidence = expected[code]
        entry = records[code]['sourceText']
        digest = hashlib.sha256(json.dumps(original[code], sort_keys=True).encode()).hexdigest()
        if hashlib.sha256(entry.encode()).hexdigest() != evidence['sourceEntrySHA256'] or digest != evidence['originalRuleSHA256']:
            raise ValueError('Re-review changed source entry for ' + code)
        result[code] = {'rule': rule, **evidence, 'status': 'complete-source-entry-reviewed-for-rebuild-rehearsal',
                        'sourceText': entry}
    return result
