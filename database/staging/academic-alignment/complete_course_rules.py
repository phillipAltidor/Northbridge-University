#!/usr/bin/env python3
"""Normalize course descriptions and conservatively transcribe prerequisites.
Unsupported prose becomes a review gate. Never silently discard a condition.
"""
import html,json,re
from pathlib import Path
R=Path(__file__).resolve().parent
O=R/'rules'
def read(p):return json.loads((R/p).read_text())
def write(p,x):(O/p).write_text(json.dumps(x,indent=2)+'\n')
metadata=read('rules/course-metadata.json')['courses'];by={c['courseCode']:c for c in metadata}
raw={}
for source in read('reviewed-course-extraction.json'):
 for c in source['records']:raw.setdefault(c['code'],[]).append(dict(c,sourceSHA256=source['sourceSHA256']))
for version in read('rules/approved-historical-course-versions.json'):
 raw[version['versionID']]=[{'code':version['courseCode'],'title':version['title'],'sourceText':version['sourceText'],'sourceSHA256':version['sourceSHA256']}]
raw['ED4050']=raw['ED4050@2020-2022']
# Display choice uses the same-code entry explicitly marked as an updated title.
by['SY2222']['sourceTitle']='Care & Care Work'
by['SY2222']['titleAliases']=['Sociology of Caring','The Work of Caring']
by['SY2222']['metadataStatus']='project-display-title-selected-source-variants-retained'
by['SY2222']['issues']=[]

class ParseError(Exception):pass

def parse_condition(source):
 s=html.unescape(source).replace('\u00a0',' ')
 s=re.sub(r'[“”\"]','',s)
 s=re.sub(r'\bEC[ \t-]*(?:II|ll|11|2)\b','ECII',s,flags=re.I)
 s=re.sub(r'\bEC[ \t-]*(?:I|l|1)\b','ECI',s,flags=re.I)
 s=re.sub(r'\bEC\s*I\s*I\b','ECII',s,flags=re.I)
 s=re.sub(r'\bEC\s*I\b','ECI',s,flags=re.I)
 s=re.sub(r'\b([A-Z]{2})\s+(\d{4}[A-Z]?)\b',r'\1\2',s)
 if re.fullmatch(r'\s*(?:none|no prerequisites?)\s*[.]?\s*',s,re.I):return {'type':'no-course-prerequisites','sourceExplicitlyStatesNone':True}
 if re.fullmatch(r'\s*(?:permission|approval) (?:of|from) (?:the )?instructor\s*[.]?\s*',s,re.I):return {'type':'approval','authority':'course-instructor'}
 # Exact one-of / English / permission pattern has explicit grouping in prose.
 one=re.fullmatch(r'\s*One of the following:\s*(.*?)\s+AND\s+EMS\s+Or permission of instructor\s*[.]?\s*',s,re.S|re.I)
 if one:
  option=parse_condition(one[1])
  return {'type':'or','rules':[{'type':'and','rules':[option,{'type':'proficiency','key':'EMS'}]},{'type':'approval','authority':'course-instructor'}]}
 s=re.sub(r'\bEnglish Proficiency\b','EMS',s,flags=re.I)
 s=re.sub(r'\bMath Proficiency\b','MATH_PROFICIENCY',s,flags=re.I)
 s=re.sub(r'\b(?:permission of (?:the )?instructor|instructor approval)\b','INSTRUCTOR_APPROVAL',s,flags=re.I)
 # Grade suffix/prefix transforms are deliberately narrow.
 s=re.sub(r'([A-Z]{2}\d{4}[A-Z]?)\s*\((?:with )?(?:a )?minimum grade (?:of )?([ABCDF][+-]?)\)',r'\1@\2',s,flags=re.I)
 s=re.sub(r'(?:a )?(?:grade of )?([ABCDF][+-]?) or (?:better|higher) in\s+([A-Z]{2}\d{4}[A-Z]?)',r'\2@\1',s,flags=re.I)
 global_grade=None
 m=re.match(r'^\s*(?:a )?(?:minimum )?grade (?:of )?([ABCDF][+-]?) (?:or (?:higher|better) )?in\s*',s,re.I)
 if m:global_grade=m[1].upper();s=s[m.end():]
 s=s.strip().rstrip('.')
 s=re.sub(r'\s*[,;]\s*',' AND ',s)
 s=re.sub(r'\s*&\s*',' AND ',s)
 s=re.sub(r'\band\b','AND',s,flags=re.I);s=re.sub(r'\bor\b','OR',s,flags=re.I)
 if 'AND' in s and 'OR' in s and '(' not in s:raise ParseError('mixed AND/OR without explicit grouping')
 token_re=re.compile(r'[A-Z]{2}\d{4}[A-Z]?(?:@[ABCDF][+-]?)?|MATH_PROFICIENCY|INSTRUCTOR_APPROVAL|EMS|ECII|ECI|AND|OR|[()]')
 tokens=[];end=0
 for m in token_re.finditer(s):
  if s[end:m.start()].strip():raise ParseError('unsupported prerequisite prose')
  tokens.append(m[0]);end=m.end()
 if s[end:].strip() or not tokens:raise ParseError('unsupported or empty prerequisite text')
 index=0
 def atom():
  nonlocal index
  if index>=len(tokens):raise ParseError('missing operand')
  token=tokens[index];index+=1
  if token=='(':
   node=expr('OR')
   if index>=len(tokens) or tokens[index]!=')':raise ParseError('unbalanced grouping')
   index+=1;return node
  if token=='INSTRUCTOR_APPROVAL':return {'type':'approval','authority':'course-instructor'}
  if token in ('EMS','ECI','ECII','MATH_PROFICIENCY'):return {'type':'proficiency','key':token}
  if re.fullmatch(r'[A-Z]{2}\d{4}[A-Z]?(?:@[ABCDF][+-]?)?',token):
   parts=token.split('@');return {'type':'course','courseCode':parts[0],'minimumGrade':parts[1] if len(parts)>1 else global_grade,'minimumGradeStatus':'explicit' if len(parts)>1 or global_grade else 'not-specified-in-source'}
  raise ParseError('unexpected operand')
 def expr(level):
  nonlocal index
  next_parser=(lambda:expr('AND')) if level=='OR' else atom
  nodes=[next_parser()]
  while index<len(tokens) and tokens[index]==level:index+=1;nodes.append(next_parser())
  return nodes[0] if len(nodes)==1 else {'type':level.lower(),'rules':nodes}
 node=expr('OR')
 if index!=len(tokens):raise ParseError('unconsumed prerequisite condition')
 return node

labels=re.compile(r'(?im)^(Prerequisite/Corequisite|Prerequisites?|Corequisites?|When Offered|Note)[ \t]*:?[ \t]*(?:\n|(?<=:))')
def sections(text):
 text=html.unescape(text)
 text=re.sub(r'(?<=[^\n])(?=(?:Corequisite|Prerequisite/Corequisite|When Offered:|Note:))','\n',text)
 text='\n'.join(line for line in text.splitlines() if line.strip() not in ['Course Descriptions - SUNY Old Westbury','SUNY Old Westbury','Mathematics'])
 matches=list(labels.finditer(text));fields={}
 for i,m in enumerate(matches):fields.setdefault(m[1].lower().rstrip(':'),[]).append(text[m.end():matches[i+1].start() if i+1<len(matches) else len(text)].strip())
 desc=text[:matches[0].start()] if matches else text
 desc=re.sub(r'^\s*(?:[A-Z]{2}\d{4}[^\n]*\n)?\s*Credits:\s*\d+(?:\s*[-‑–]\s*\d+)?\s*','',desc)
 return ' '.join(desc.split()),fields
manual=[];records=[]
overrides={x['courseCode']:x for x in read('rules/pilot-prerequisites.json')['rules']}
overrides.update({x['courseCode']:x for x in read('rules/psychology-prerequisites.json')['rules']})
for key,entries in sorted(raw.items()):
 if key=='ED4050':continue # canonical lookup points to its single historical version
 row=entries[0];code=row['code'];desc,fields=sections(row['sourceText'])
 if key=='SY2222':row=next(c for c in entries if c['title']=='Care & Care Work');desc,fields=sections(row['sourceText'])
 components=[];issues=[]
 for label,values in fields.items():
  if label not in ['prerequisite','prerequisites','corequisite','corequisites','prerequisite/corequisite']:continue
  for text in values:
   try:node=parse_condition(text)
   except ParseError as error:
    node={'type':'review-required','reason':str(error),'sourceText':text,'approvalAuthority':'authorized-academic-reviewer'};issues.append(str(error))
   if label.startswith('corequisite'):node={'type':'corequisite-condition','rule':node}
   elif label=='prerequisite/corequisite':node={'type':'completed-or-concurrent-condition','rule':node}
   components.append(node)
 if key in overrides:
  item=overrides[key];components=[item['rule']];issues=[]
 if key=='MA2701':
  expected='Grade of C or higher in MA 2000 - Applied Statistics and a grade of C or higher in MA 2300 - Calculus for Business and Economics'
  assert ' '.join(' '.join(fields.get('prerequisites',[])).split())==expected, 'MA2701 source changed; review its explicit transcription'
  components=[{'type':'and','rules':[{'type':'course','courseCode':c,'minimumGrade':'C','minimumGradeStatus':'explicit'} for c in ['MA2000','MA2300']]}];issues=[]
 if not components:components=[{'type':'review-required','reason':'prerequisite statement not supplied','approvalAuthority':'authorized-academic-reviewer'}];issues=['prerequisite statement not supplied']
 if key=='ED5925@2020-2022':
  components=[{'type':'review-required','reason':'Malformed historical ED400 reference and ambiguous separators; do not guess or add a nonexistent course.','sourceText':fields.get('prerequisites',[]),'approvalAuthority':'School-of-Education-academic-reviewer'}];issues=['historical prerequisite requires review']
 # Preserve unresolved course identities at the condition node, including OR branches.
 def resolve(node):
  if isinstance(node,list):return [resolve(n) for n in node]
  if not isinstance(node,dict):return node
  if node.get('type')=='course' and node['courseCode'] not in by:
   issues.append('unresolved course reference '+node['courseCode'])
   return {'type':'unresolved-course-reference','sourceCourseCode':node['courseCode'],'originalRule':node,'approvalAuthority':'authorized-academic-reviewer'}
  return {k:resolve(v) if isinstance(v,(list,dict)) else v for k,v in node.items()}
 components=[resolve(n) for n in components]
 if issues:manual.append({'courseVersionOrCode':key,'issues':list(dict.fromkeys(issues))})
 if key in by or key=='ED4050@2020-2022':
  target=code if key=='ED4050@2020-2022' else key
  if key=='ED4050@2020-2022':desc=' '.join(row['sourceText'].splitlines()[1:]).split('Prerequisites')[0].strip()
  by[target]['displayDescription']=desc;by[target]['sourceEditorialNotes']=fields.get('note',[])
 records.append({'courseVersionOrCode':key,'courseCode':code,'status':'review-gated' if issues else 'transcribed-for-test','rule':{'type':'and','rules':components} if len(components)>1 else components[0],'sourceFields':fields,'sourceSHA256':row['sourceSHA256']})
write('course-metadata.json',{'status':'normalized-with-explicit-review-gates','courses':list(sorted(by.values(),key=lambda x:x['courseCode']))})
write('all-course-prerequisites.json',{'status':'transcribed-with-review-gates-not-live-registration-code','courses':records,'canonicalLookupAliases':{'ED4050':'ED4050@2020-2022'}})
write('manual-review-register.json',{'status':'required-evidence-gates','prerequisites':manual,'missingCreditValues':[c['courseCode'] for c in by.values() if c['creditMinimum'] is None],
 'policy':'These gates are real requirements for affected evaluations. They must never be interpreted as no prerequisites or zero credits. Unselected optional courses do not block unrelated valid paths.'})
print('Transcribed',len(records),'course/version prerequisite records;',len(manual),'retain explicit academic-review gates.')
