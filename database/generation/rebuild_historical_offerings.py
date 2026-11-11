"""Source-explicit former names for fictional rebuild meeting templates only.

Never establish transfer equivalence or silently use a lecture CRN for a lab.
The old official code/CRN remains the source identity of the offering.
"""
import hashlib
import re


def credit_bounds(section):
    fixed = section.get('creditHours')
    if fixed is not None:
        return float(fixed), float(fixed)
    lo, hi = section.get('creditHourLow'), section.get('creditHourHigh')
    return (float(lo), float(hi if hi is not None else lo)) if lo is not None else (None, None)


def former_name_templates(metadata, source_rules, offers):
    accepted, rejected = {}, []
    for record in source_rules:
        code = record['courseCode']
        if not code.startswith('PX') or code not in metadata:
            continue
        for note in record.get('sourceFields', {}).get('note', []):
            match = re.search(r'Previously\s+named\s+[^A-Z]*(CP)\s*(\d{4})\b', note)
            if not match:
                continue
            former = match[1] + match[2]
            current = metadata[code]
            lo, hi = current['creditMinimum'], current['creditMaximum']
            old = [s for (term, key), rows in offers.items() if key == former for s in rows]
            compatible = [s for s in old if credit_bounds(s) == (lo, hi)]
            if lo is None or hi is None or not compatible:
                rejected.append({'courseCode': code, 'formerCodeInNote': former,
                                 'reason': 'No collected former-code offering has matching course credits; retain review gate.'})
                continue
            accepted[code] = {'formerCode': former, 'sourceNote': note,
                              'sourcePDFSHA256': record['sourceSHA256'],
                              'sourceNoteSHA256': hashlib.sha256(note.encode()).hexdigest(),
                              'creditMinimum': lo, 'creditMaximum': hi,
                              'purpose': 'source-explicit-former-name-meeting-template-for-fictional-rebuild',
                              'officialSourceCodeMustBeRetained': True,
                              'verifiedTranscriptEquivalence': False}
    return accepted, rejected
