#!/usr/bin/env python3
"""Public Banner search only. Persist academic allowlist, never faculty/contact/room fields."""
import urllib.request,urllib.parse,http.cookiejar,json,hashlib,re,sys
from pathlib import Path
root=Path(__file__).resolve().parents[1]
base='https://owsis.oldwestbury.edu';prefix=base+'/StudentRegistrationSsb/ssb/'
def request(path,data=None):
 req=urllib.request.Request(prefix+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,headers={'User-Agent':'Mozilla/5.0','Accept':'application/json,text/html'})
 response=opener.open(req,timeout=25);return response.read()
fields=['courseReferenceNumber','subject','courseNumber','courseDisplay','subjectCourse','courseTitle','sequenceNumber','term','termDesc','scheduleTypeDescription','instructionalMethod','instructionalMethodDescription','partOfTerm','creditHours','creditHourLow','creditHourHigh','creditHourIndicator','openSection','maximumEnrollment']
meeting_fields=['beginTime','endTime','startDate','endDate','monday','tuesday','wednesday','thursday','friday','saturday','sunday','meetingScheduleType','meetingType','meetingTypeDescription']
def collect(term,description):
 global opener
 opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 html=request('term/termSelection?mode=search').decode()
 if '/StudentRegistrationSsb/ssb/term/search?mode=search' not in html:raise RuntimeError('Official term search endpoint changed')
 available=json.loads(request('classSearch/getTerms?'+urllib.parse.urlencode({'searchTerm':term[:4],'offset':1,'max':100})))
 if term not in [t['code'] for t in available]:return {'term':term,'description':description,'status':'not-published-by-official-source'}
 ack=json.loads(request('term/search?mode=search',{'term':term,'studyPath':'','startDatepicker':'','endDatepicker':''}))
 if not ack.get('fwdURL'):raise RuntimeError('Public semester selection failed')
 request('classSearch/classSearch?mode=search')
 sections=[];pages=[];offset=0;expected=None
 while expected is None or offset<expected:
  path='searchResults/searchResults?'+urllib.parse.urlencode({'txt_term':term,'pageOffset':offset,'pageMaxSize':500,'sortColumn':'subjectDescription','sortDirection':'asc'})
  raw=request(path);data=json.loads(raw)
  rows=data.get('data',[]);expected=int(data.get('totalCount',-1))
  if expected<0 or not rows:raise RuntimeError('Missing or incomplete official search results at offset '+str(offset))
  for row in rows:
   section={k:row.get(k) for k in fields};section['meetings']=[{k:m.get('meetingTime',{}).get(k) for k in meeting_fields} for m in row.get('meetingsFaculty',[])]
   if section['term']!=term or not re.fullmatch(r'\d+',str(section['courseReferenceNumber'])):raise RuntimeError('Invalid official section identity')
   sections.append(section)
  pages.append({'url':prefix+path,'offset':offset,'records':len(rows),'rawResponseSHA256':hashlib.sha256(raw).hexdigest()})
  offset+=len(rows);print(term,'official sections:',offset,'of',expected,flush=True)
  if offset>expected:raise RuntimeError('Paging total mismatch')
 keys=[(s['term'],s['courseReferenceNumber']) for s in sections]
 if len(keys)!=len(set(keys)):raise RuntimeError('Duplicate official term/CRN identities')
 result={'status':'complete-public-term-academic-projection-not-active-registration','reviewAsOf':'2026-11-07','source':prefix+'term/termSelection?mode=search','term':term,'description':description,'totalSections':expected,'totalMeetings':sum(len(s['meetings']) for s in sections),'pages':pages,'discardedFields':['faculty','faculty IDs','names','emails','source rooms','source buildings','personal/contact data'],'sections':sections}
 out=root/('database/staging/academic-alignment/official-sections-'+('fall-2026' if term=='202609' else term)+'.json')
 if out.exists():
  previous=out.read_bytes();digest=hashlib.sha256(previous).hexdigest();archive=out.parent/'official-source-archives';archive.mkdir(exist_ok=True)
  immutable=archive/(digest+'.json')
  if immutable.exists()and immutable.read_bytes()!=previous:raise RuntimeError('Immutable source archive mismatch')
  if not immutable.exists():immutable.write_bytes(previous)
 out.write_text(json.dumps(result,indent=2)+'\n')
 return {'term':term,'description':description,'path':str(out.relative_to(root)),'sections':len(sections),'meetings':result['totalMeetings'],'sourceSHA256':hashlib.sha256(out.read_bytes()).hexdigest()}

if __name__=='__main__':
 if sys.argv[1:]==['--all-history']:
  terms=json.loads((root/'database/staging/academic-alignment/legacy-academic-review-inputs.json').read_text())['terms']
  results=[]
  for row in terms:
   season,year=row['semester_Name'].split();term=year+('09' if season=='Fall' else '02')
   results.append(collect(term,row['semester_Name']))
  output={'status':'official-term-collection-with-explicit-unpublished-terms','asOf':'2026-11-07','terms':results,'personalFieldsPersisted':False}
  (root/'database/staging/academic-alignment/official-history-source-register.json').write_text(json.dumps(output,indent=2)+'\n')
  print(json.dumps({'termsCollected':sum('path'in r for r in results),'unpublished':[r['description'] for r in results if 'path'not in r],'sections':sum(r.get('sections',0)for r in results)}))
 elif not sys.argv[1:]:print(json.dumps(collect('202609','Fall 2026')))
 else:raise SystemExit('Use no arguments or --all-history')
