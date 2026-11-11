"""Explicit Northbridge choices under the owner's fictional rebuild authority.

These are project rules, never represented as corrected SUNY catalog wording.
Every readable academic condition remains enforced by the source proof rules.
"""
import copy
import hashlib
import json


def project_rules(source_proofs, original):
    rules = {}
    ed4220 = copy.deepcopy(source_proofs['ED4220']['rule'])
    ed4220['rules'][-1] = {'type': 'corequisite-condition', 'rule': {'type': 'course', 'courseCode': 'ED4200'}}
    rules['ED4220'] = {
        'rule': ed4220,
        'projectDecision': 'NB-REBUILD-ED4220-001',
        'reason': 'Northbridge requires ED4200 before or with ED4220 in place of the unresolved CE4200 reference. This is a project exception, not a verified source equivalency.',
    }
    ed5925 = copy.deepcopy(source_proofs['ED5925@2020-2022']['rule'])
    ed5925['rules'][-1] = {'type': 'scoped-evidence', 'scope': 'project-review:ED5925:ED400'}
    rules['ED5925@2020-2022'] = {
        'rule': ed5925,
        'projectDecision': 'NB-REBUILD-ED5925-001',
        'reason': 'Retain all readable historical prerequisites and require a recorded Northbridge academic review of the undefined ED400 reference. No ED400 course or transcript equivalence is invented.',
    }
    rules['PH3800'] = {
        'rule': {'type': 'completed-writing-proficiency', 'components': ['CompositionI', 'CompositionII'], 'minimumCourseGrade': 'C'},
        'projectDecision': 'NB-REBUILD-PH3800-001',
        'reason': 'Northbridge uses completed Composition I and II at C or better for the incomplete Writing O.W. condition, consistent with the previously approved CS5910 writing decision. No placement score is invented.',
    }
    for key, entry in rules.items():
        entry.update({'sourceRuleSHA256': hashlib.sha256(json.dumps(original[key], sort_keys=True).encode()).hexdigest(),
                      'authority': 'Owner-authorized fictional academic rebuild; recommended project choices delegated to the implementation.',
                      'isOfficialOldWestburyCorrection': False})
    return rules
