#!/usr/bin/env python3
"""Source observations must replace guesses without inventing student approvals."""
import copy
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'database/generation'))
from rebuild_degree_properties import reviewed_properties
from rebuild_oldwestbury import Builder


class SourcePropertiesTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.metadata = {c['courseCode']: c for c in json.loads((ROOT / 'database/staging/academic-alignment/rules/course-metadata.json').read_text())['courses']}

    def properties(self, *codes):
        courses = {code: {'courseCode': code, 'catalogVersion': '2026-2027', 'title': self.metadata[code]['sourceTitle']} for code in codes}
        before = copy.deepcopy(courses)
        output = reviewed_properties(courses)
        self.assertEqual(courses, before)
        return output['courseProperties']

    def test_business_liberal_arts_comes_from_observation_not_subject_prefix(self):
        p = self.properties('BU3010', 'BU4035', 'BU5115')
        self.assertTrue(all(row['liberalArts'] for row in p.values()))

    def test_source_absence_does_not_keep_guessed_liberal_arts_credit(self):
        p = self.properties('AS2901', 'MC2901', 'PH3800', 'PX3200')
        self.assertFalse(any(row['liberalArts'] for row in p.values()))

    def test_public_health_core_is_not_automatically_an_elective(self):
        p = self.properties('PH2000', 'PH3400')
        self.assertNotIn('approved-public-health-elective', p['PH2000']['classifications'])
        self.assertIn('approved-public-health-elective', p['PH3400']['classifications'])

    def test_source_only_supplies_properties_not_placement_or_student_approval(self):
        p = self.properties('EL1000', 'BS2100')
        self.assertTrue(p['BS2100']['labBearing'])
        self.assertNotIn('qualifying-psychology-bs-science', p['BS2100']['classifications'])
        self.assertFalse(any('approval' in key.lower() for row in p.values() for key in row))

    def test_distinct_science_semesters_in_psychology_plan(self):
        b = Builder.__new__(Builder)
        b.courses = {code: {'credits': 3, 'level': 2400} for code in ['BS2400', 'BS2401', 'CP2120', 'CP2121']}
        b.rules = {'BS2400': {'type': 'no-course-prerequisites'}}
        b.source_laboratories = {}; b.proof_planning_enabled = True
        plan = {'major': 'Psychology, B.S.', 'minor': None, 'courses': list(b.courses)}
        self.assertFalse(b.ready('BS2400', {}, {'CP2120', 'CP2121'}, plan))
        self.assertTrue(b.ready('BS2400', {'CP2120': 1, 'CP2121': 1}, {'BS2401'}, plan))


if __name__ == '__main__':
    unittest.main()
