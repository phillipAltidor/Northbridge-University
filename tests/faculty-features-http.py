"""Local-demo-only HTTP checks. Credentials stay in ignored .local files."""
import json, urllib.request, urllib.parse, urllib.error, http.cookiejar
from pathlib import Path
from html.parser import HTMLParser

ROOT=Path(__file__).resolve().parents[1]
BASE='http://127.0.0.1:8000'
settings=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line)
assert settings.get('DB_NAME')=='northbridge_ruba_demo_20261119' and settings.get('DB_PORT')=='3307', 'Not the isolated demo'
accounts=json.loads((ROOT/'.local/demo-accounts.json').read_text())
checks=0
class Form(HTMLParser):
 def __init__(self,text):
  super().__init__();self.inputs={};self.selects={};self.current=None;self.option=None;self.links=[];self.disabled_fieldsets=0;self.feed(text)
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='input' and 'name' in a:self.inputs[a['name']]=a.get('value','')
  if tag=='select':self.current=a.get('name');self.selects[self.current]=[]
  if tag=='option' and self.current is not None:self.option=[a.get('value',''),''];self.selects[self.current].append(self.option)
  if tag=='a' and 'href' in a:self.links.append(a['href'])
  if tag=='fieldset' and 'disabled' in a:self.disabled_fieldsets+=1
 def handle_endtag(self,tag):
  if tag=='option':self.option=None
  if tag=='select':self.current=None
 def handle_data(self,text):
  if self.option is not None:self.option[1]+=text

def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,path,data=None,method=None):
 req=urllib.request.Request(BASE+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,method=method)
 try:
  with c.open(req,timeout=45) as r:return r.status,r.read().decode(),urllib.parse.urlparse(r.url).path
 except urllib.error.HTTPError as e:return e.code,e.read().decode(),urllib.parse.urlparse(e.url).path

def check(ok,label):
 global checks
 if not ok:raise AssertionError(label)
 checks+=1

def signin(role):
 c=client();status,html,url=request(c,'/login.php',{'role':role,'email':accounts[role]['email'],'password':accounts[role]['password']})
 check(status==200 and url=='/home.php' and f'{role} Portal' in html,role+' login/home')
 return c

faculty=signin('Faculty');admin=signin('Admin');student=signin('Student')
modes=['password','address','master-schedule','attendance','grades','advisees','students','transcript','departments','courses','majors','minors','prerequisites','major-requirements','major-courses']
for mode in modes:
 path='/faculty/'+mode+'.php'
 status,html,url=request(faculty,path)
 check(status==200 and 'temporarily unavailable' not in html and 'Fatal error' not in html,'Faculty GET '+mode)
 for c,label in [(admin,'admin'),(student,'student')]:check(request(c,path)[0]==403,label+' denied '+mode)
 check(request(client(),path)[2]=='/login.php','anonymous redirected '+mode)
 if mode not in ['password','address','attendance','grades']:check(request(faculty,path,{'unexpected':'write'})[0]==405,'read-only POST '+mode)

for mode,selector in [('courses','dept_ID'),('majors','dept_ID'),('minors','dept_ID'),('prerequisites','course_id'),('major-requirements','major_id'),('major-courses','major_id')]:
 page=request(faculty,'/faculty/'+mode+'.php')[1]; options=Form(page).selects[selector]
 value=next(v for v,label in options if v)
 status,html,url=request(faculty,'/faculty/'+mode+'.php?'+urllib.parse.urlencode({selector:value}))
 check(status==200 and 'temporarily unavailable' not in html,'selected catalog '+mode)

for key in ['q','professor','building','room']:
 status,html,_=request(faculty,'/faculty/master-schedule.php?'+urllib.parse.urlencode({key:'ZZZ_NO_MATCH_928374'}))
 check(status==200 and '0 matching sections' in html,'schedule filter '+key)
for query in ['day=Invalid','start=25:00','start=15:00&end=09:00','semester_id[]=1']:
 check(request(faculty,'/faculty/master-schedule.php?'+query)[0]==400,'invalid schedule selector')
for role,c,paths in [('Admin',admin,['/admin/master-schedule.php','/admin/create-department.php','/admin/update-department.php','/admin/assign-faculty-department.php']),('Student',student,['/student/information.php','/student/schedule.php','/student/degree-audit.php'])]:
 for path in paths:
  status,html,_=request(c,path)
  check(status==200 and 'Fatal error' not in html and 'temporarily unavailable' not in html,role+' existing page '+path)

sid=accounts['Student']['id']
status,html,_=request(faculty,f'/faculty/transcript.php?student_id={sid}')
check(status==200 and 'Course attempts' in html,'selected transcript')
for mode in ['attendance','grades']:
 path='/faculty/'+mode+'.php'
 options=Form(request(faculty,path)[1]).selects['crn']
 crn=next(v for v,label in options if v and ('Fall 2026' in label if mode=='grades' else 'Fall 2026' not in label))
 status,html,_=request(faculty,path+'?crn='+crn);form=Form(html)
 check(status==200 and form.disabled_fieldsets==1,'closed marks '+mode)
 status,html,_=request(faculty,path+'?crn='+crn,{'token':form.inputs['token'],'version':form.inputs['version'],f'marks[{sid}]':'Present' if mode=='attendance' else 'A'})
 check('role="alert"' in html and ('not been configured' in html or 'cannot be recorded' in html or 'approved class date' in html),'calendar denies POST '+mode)
 check(request(faculty,path+'?crn=2147483647')[0]==400,'unowned section '+mode)

# Exercise a real address save, stale rejection and restore on the local demo only.
path='/faculty/address.php';form=Form(request(faculty,path)[1]);original={k:form.inputs[k] for k in ['street','city','state','zip_Code']}
try:
 bad={**original,'token':'wrong','version':form.inputs['version']}
 check(request(faculty,path,bad)[0]==403,'CSRF rejected')
 changed={**original,'street':'Local HTTP Test Street','token':form.inputs['token'],'version':form.inputs['version'],'user_ID':accounts['Student']['id']}
 status,html,_=request(faculty,path,changed)
 check(status==200 and Form(html).inputs['street']=='Local HTTP Test Street','own address saved')
 current=Form(html)
 status,html,_=request(faculty,path,{**original,'token':current.inputs['token'],'version':form.inputs['version']})
 check('changed since this form opened' in html,'stale address denied')
finally:
 fresh=Form(request(faculty,path)[1]);status,html,_=request(faculty,path,{**original,'token':fresh.inputs['token'],'version':fresh.inputs['version']})
 check(status==200 and all(Form(html).inputs[k]==v for k,v in original.items()),'address restored')

# Real password change invalidates the old second session; restore through the UI.
old_session=signin('Faculty');temporary='Local-HTTP-Temporary-Password42!';password_changed=False
try:
 form=Form(request(faculty,'/faculty/password.php')[1])
 status,html,_=request(faculty,'/faculty/password.php',{'token':form.inputs['token'],'current_password':accounts['Faculty']['password'],'new_password':temporary,'confirmation':temporary})
 check(status==200 and 'Your password has been changed' in html,'password saved');password_changed=True
 check(request(old_session,'/faculty/address.php')[2]=='/login.php','old session invalidated')
finally:
 if password_changed:
  form=Form(request(faculty,'/faculty/password.php')[1])
  status,html,_=request(faculty,'/faculty/password.php',{'token':form.inputs['token'],'current_password':temporary,'new_password':accounts['Faculty']['password'],'confirmation':accounts['Faculty']['password']})
  check(status==200 and 'Your password has been changed' in html,'password restored')

for private in ['/.env','/.local/demo-logins.txt','/.git/config','/database/data/login.sql','/tools/setup-local-demo.php']:
 check(request(client(),private)[0]==404,'private path blocked')
receipt={'checks':checks,'database':settings['DB_NAME'],'result':'pass','mutations':'temporary own address/password restored; no academic marks saved'}
(ROOT/'.local/faculty-http-receipt.json').write_text(json.dumps(receipt,indent=2))
print(f'PASS: {checks} local HTTP role, route, catalog, CSRF, ownership, calendar and account checks. Test address/password restored.')
