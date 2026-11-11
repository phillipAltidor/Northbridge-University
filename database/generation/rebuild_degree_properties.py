"""Evidence-backed properties for the fictional rebuild, separate from guesses.

The section registry supplies LA observations; program lists and individually
reviewed descriptions supply positive category uses. Unknown historical LA
properties stay unknown. This does not approve student placements or fieldwork.
"""
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DIRECTORY = ROOT / 'database/staging/academic-alignment'


def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest()


def reviewed_properties(courses):
    source_path = DIRECTORY / 'official-course-attributes.json'
    source = json.loads(source_path.read_text())
    programs = json.loads((DIRECTORY / 'rules/curricula.json').read_text())['programs']
    metadata = {c['courseCode']: c for c in json.loads((DIRECTORY / 'rules/course-metadata.json').read_text())['courses']}
    labs = json.loads(Path(__file__).with_name('rebuild_lab_science_evidence.json').read_text())
    media = next(p for p in programs if p['program'] == 'Media & Communications, B.A.')
    ph = next(p for p in programs if p['program'] == 'Public Health, B.S.')
    def listed(node):
        result = set(node.get('eligibleCourses', []))
        for child in node.get('groups', []):
            result.update(listed(child))
        return result
    # Public-health elective eligibility is a source list, not every PH course.
    ph_electives = listed(ph['groups'][2])
    literature = {
        'US-literature': {'EL3500', 'EL3510', 'EL3600'},
        'British-literature': {'EL3500', 'EL3510', 'EL3800'},
        'World-literature': {'EL3560', 'EL4312', 'EL4405', 'EL4600'},
        'literature-before-1800-or-US-before-1900': {'EL3600', 'EL3800', 'EL3560', 'EL4312'},
    }
    science = {'BS2400', 'BS2401', 'BS2410', 'BS2411', 'CP2120', 'CP2121', 'CP2130', 'CP2131'}
    rows = {}
    for key, course in courses.items():
        code = course['courseCode']
        observation = source['courseProperties'].get(key)
        if not observation:
            continue
        if observation['status'] != 'consistent-section-attribute-observations':
            raise ValueError('Inconsistent course attributes: ' + key)
        records = [r for r in source['records'] if r['courseKey'] == key]
        if not records:
            raise ValueError('Missing attribute observations: ' + key)
        level = int(re.search(r'\d+', code)[0])
        classes = [category for category, codes in literature.items() if code in codes]
        for index, category in [(1, 'Media Studies'), (3, 'Media Production')]:
            if code in media['groups'][index]['eligibleCourses']:
                classes.append(category)
        if code in {'MC4832', 'MC4835'}:
            classes.append('internship')
        if code in {'VA3340', 'VA3360'}:
            classes.append('art-history')
        if code in ph_electives:
            classes.append('approved-public-health-elective')
        if code in {'MA2090', 'MA2310', 'MA2320'}:
            classes.append('precalculus-or-higher')
        if code in science:
            classes.append('qualifying-psychology-bs-science')
        if 'independent study' in course['title'].lower():
            classes.append('independent-study')
        lab = code in labs or code in {'BS3450', 'BS3520', 'BS3530'}
        rows[key] = {
            'courseCode': code, 'catalogVersion': course['catalogVersion'],
            'liberalArts': observation['liberalArtsAttributeListed'],
            'upperDivision': 3000 <= level < 6000, 'level': level,
            'classifications': sorted(set(classes)), 'labBearing': lab,
            'sourceMetadataSHA256': digest(metadata[code]),
            'sectionAttributeEvidenceSHA256': digest(records),
            'categoryBasis': 'Reviewed explicit program lists and course descriptions; positive approved uses only.',
            'upperDivisionBasis': 'College-wide source defines 3000, 4000 and 5000 levels as upper division.',
            'liberalArtsBasis': 'Consistent public section LA attribute; absence contributes zero LA credits.',
        }
    return {'status': 'reviewed-source-properties-not-student-approvals',
            'sourceAttributeFileSHA256': hashlib.sha256(source_path.read_bytes()).hexdigest(),
            'courseProperties': rows, 'workingDatabaseChanged': False}
