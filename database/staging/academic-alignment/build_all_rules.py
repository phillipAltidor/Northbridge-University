#!/usr/bin/env python3
"""Rebuild the staged rule package in dependency order; no database/network access."""
import subprocess
import sys
from pathlib import Path
root=Path(__file__).resolve().parent
for script in ['normalize_rules.py','build_curricula.py','complete_course_rules.py','finalize_rules.py','build_evaluator_capabilities.py','validate_rules.py']:
 subprocess.run([sys.executable,str(root/script)],check=True)
