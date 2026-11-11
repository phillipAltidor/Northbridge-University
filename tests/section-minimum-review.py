#!/usr/bin/env python3
"""Check roster preservation and conflict safety in staged minimum-enrollment proposals."""
import copy
import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('minimum_review', Path(__file__).resolve().parents[1] / 'tools/review-section-minimums.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def section(course, students, begin, end, group=1):
    return {'term': '202702', 'courseKey': course, 'deliveryGroup': group,
            'studentIds': list(students), 'capacity': 10,
            'meetings': [{'beginTime': begin, 'endTime': end,
                          'startDate': '01/19/2027', 'endDate': '05/07/2027',
                          'monday': True, 'wednesday': True}]}


class ReviewTests(unittest.TestCase):
    def test_rebalances_only_same_course_and_preserves_source(self):
        source = {'sections': [section('A', range(1, 11), '0800', '0940'),
                               section('A', [11], '1000', '1140', 2)]}
        before = copy.deepcopy(source)
        summary, proposed = module.review(source, '202702')
        self.assertEqual(summary['sectionsUnderMinimumAfterProposal'], 0)
        self.assertEqual(summary['proposedRosterMoves'], 4)
        self.assertEqual(source, before)
        self.assertEqual(proposed['status'], 'minimum-enrollment-proposal-awaiting-full-validation')

    def test_does_not_move_students_into_a_conflicting_course(self):
        source = {'sections': [section('A', range(1, 11), '0800', '0940'),
                               section('A', [11], '1000', '1140', 2),
                               section('B', range(1, 11), '1000', '1140')]}
        summary, _ = module.review(source, '202702')
        self.assertEqual(summary['sectionsUnderMinimumAfterProposal'], 1)
        self.assertEqual(summary['proposedRosterMoves'], 0)
        self.assertEqual(summary['studentConflicts'], 0)

    def test_does_not_invent_students_or_cancel_low_demand_courses(self):
        source = {'sections': [section('A', [1, 2, 3], '0800', '0940'),
                               section('B', range(4, 9), '1000', '1140'),
                               section('B', [9], '1200', '1340', 2)]}
        summary, proposed = module.review(source, '202702')
        self.assertEqual(summary['categories'], {'course-demand-below-five': 1, 'consolidation-review': 1})
        self.assertEqual(summary['sectionsCanceled'], 0)
        self.assertEqual(summary['proposedRosterMoves'], 0)
        self.assertEqual(proposed['sections'], source['sections'])


if __name__ == '__main__':
    unittest.main()
