#!/usr/bin/env python3
"""Collect public academic section attributes only; never faculty/person fields."""
import argparse
import collections
import hashlib
import http.cookiejar
from html.parser import HTMLParser
import json
from pathlib import Path
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = 'https://owsis.oldwestbury.edu/StudentRegistrationSsb/ssb/'


class Attributes(HTMLParser):
    def __init__(self):
        super().__init__()
        self.section = False
        self.collecting = False
        self.buffer = []
        self.labels = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'section' and attrs.get('aria-labelledby') == 'attributes':
            self.section = True
        if tag == 'span' and 'attribute-text' in attrs.get('class', '').split():
            self.collecting = True
            self.buffer = []

    def handle_data(self, data):
        if self.collecting:
            self.buffer.append(data)

    def handle_endtag(self, tag):
        if tag == 'span' and self.collecting:
            label = ' '.join(' '.join(self.buffer).split())
            if label:
                self.labels.append(label)
            self.collecting = False


def collect(candidate):
    directory = ROOT / 'database/staging/academic-alignment'
    register = json.loads((directory / 'official-history-source-register.json').read_text())['terms']
    by_term = {r['term']: json.loads((ROOT / r['path']).read_text())['sections'] for r in register}
    required = sorted({a['courseKey'] for a in candidate['attempts']})
    jobs = collections.defaultdict(list)
    unresolved = []
    for key in required:
        course = candidate['courses'][key]
        options = []
        for term in sorted(by_term, reverse=True):
            if '@' in key and not ('202009' <= term <= '202202'):
                continue
            matches = [s for s in by_term[term] if s['subject'] + s['courseNumber'] == course['courseCode']]
            # A reused historical number with a different title is not evidence
            # for the selected older course meaning.
            if '@' in key:
                normalized = lambda text: ''.join(c.lower() for c in text.replace('&', 'and') if c.isalnum())
                matches = [s for s in matches if normalized(s['courseTitle']) == normalized(course['title'])]
            if matches:
                options = [(term, s) for s in matches]
                break
        if not options:
            unresolved.append({'courseKey': key, 'reason': 'No exact collected source course/version; no attributes borrowed from a meeting template.'})
            continue
        # All available sections in the selected term, rather than silently
        # assuming one section's attributes apply to the entire course.
        for term, section in options:
            jobs[term].append((key, section))
    cache_path = directory / 'official-course-attributes.json'
    cached = {}
    if cache_path.exists():
        previous = json.loads(cache_path.read_text())
        if previous.get('facultyOrPersonalFieldsPersisted') is not False:
            raise ValueError('Unexpected attribute cache provenance')
        cached = {(r['courseKey'], r['sourceTerm'], r['sourceCRN']): r for r in previous['records']}
    collected = []
    for term, rows in sorted(jobs.items()):
        remaining = []
        for key, section in rows:
            hit = cached.get((key, term, section['courseReferenceNumber']))
            if hit and hit['sourceCourseCode'] == section['subject'] + section['courseNumber'] and hit['sourceCourseTitle'] == section['courseTitle']:
                collected.append(hit)
            else:
                remaining.append((key, section))
        rows = remaining
        if not rows:
            continue
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        def request(path, data=None):
            body = urllib.parse.urlencode(data).encode() if data is not None else None
            response = opener.open(urllib.request.Request(BASE + path, data=body, headers={'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json,text/html'}), timeout=25)
            return response.read()
        request('term/termSelection?mode=search')
        ack = json.loads(request('term/search?mode=search', {'term': term, 'studyPath': '', 'startDatepicker': '', 'endDatepicker': ''}))
        if not ack.get('fwdURL'):
            raise RuntimeError('Public academic term selection failed')
        request('classSearch/classSearch?mode=search')
        for index, (key, section) in enumerate(rows, 1):
            query = urllib.parse.urlencode({'term': term, 'courseReferenceNumber': section['courseReferenceNumber']})
            url = BASE + 'searchResults/getSectionAttributes?' + query
            raw = request('searchResults/getSectionAttributes?' + query)
            parser = Attributes()
            parser.feed(raw.decode())
            if not parser.section:
                raise RuntimeError('Unexpected academic attribute response; no other page data persisted')
            collected.append({'courseKey': key, 'sourceCourseCode': section['subject'] + section['courseNumber'],
                              'sourceCourseTitle': section['courseTitle'], 'sourceTerm': term,
                              'sourceCRN': section['courseReferenceNumber'], 'attributes': parser.labels,
                              'liberalArtsAttributeListed': 'Liberal Arts LA' in parser.labels,
                              'sourceURL': url, 'rawResponseSHA256': hashlib.sha256(raw).hexdigest()})
            if index % 20 == 0 or index == len(rows):
                print(term, index, 'of', len(rows), 'academic attribute records', flush=True)
    groups = collections.defaultdict(list)
    for row in collected:
        groups[row['courseKey']].append(row)
    properties = {}
    for key, rows in sorted(groups.items()):
        listed = {r['liberalArtsAttributeListed'] for r in rows}
        properties[key] = {'status': 'consistent-section-attribute-observations' if len(listed) == 1 else 'section-attributes-conflict-review-required',
                           'liberalArtsAttributeListed': next(iter(listed)) if len(listed) == 1 else None,
                           'sectionsChecked': len(rows), 'sourceTerm': rows[0]['sourceTerm']}
    return {'status': 'public-academic-attributes-for-review-not-active-course-approvals', 'reviewAsOf': '2026-11-07',
            'candidateFileSHA256': None, 'courseProperties': properties, 'unresolvedCourseVersions': unresolved,
            'records': collected, 'facultyOrPersonalFieldsPersisted': False, 'workingDatabaseChanged': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--candidate', required=True)
    args = parser.parse_args()
    path = Path(args.candidate).resolve()
    if not str(path).startswith('/private/tmp/'):
        raise SystemExit('Private candidate path required')
    report = collect(json.loads(path.read_text()))
    report['candidateFileSHA256'] = hashlib.sha256(path.read_bytes()).hexdigest()
    output = ROOT / 'database/staging/academic-alignment/official-course-attributes.json'
    output.write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps({'courses': len(report['courseProperties']), 'sourceSections': len(report['records']),
                      'unresolvedCourseVersions': report['unresolvedCourseVersions'], 'personalFieldsPersisted': False}))
