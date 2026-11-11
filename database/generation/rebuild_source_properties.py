"""Narrow source facts for prerequisite checks, not blanket degree attributes."""
import hashlib
import json
from pathlib import Path


def lab_science_evidence(metadata):
    pinned = json.loads(Path(__file__).with_name('rebuild_lab_science_evidence.json').read_text())
    reviewed = {}
    for code, evidence in pinned.items():
        actual = metadata.get(code)
        if actual is None or hashlib.sha256(json.dumps(actual, sort_keys=True).encode()).hexdigest() != evidence['sourceMetadataSHA256']:
            raise ValueError('Re-review changed laboratory science source: ' + code)
        reviewed[code] = evidence
    return reviewed
