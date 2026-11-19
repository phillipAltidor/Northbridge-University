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

admin=signin('Admin');faculty=signin('Faculty');student=signin('Student')
modes=['create-course','update-course','add-prerequisites','create-section','update-section','add-faculty-section','update-faculty-section','create-major','update-major','create-minor','update-minor','create-major-requirements','update-major-requirements','create-minor-requirements','update-minor-requirements','add-student-section','drop-student-section','update-student-grade','change-grade','declare-major','add-minor','assign-advisor','create-building','update-building','create-room']
for mode in modes:
 path='/admin/'+mode+'.php'
 status,html,url=request(admin,path)
 check(status==200 and 'Fatal error' not in html,mode+' GET')
 for client_,role in [(faculty,'Faculty'),(student,'Student')]:check(request(client_,path)[0]==403,role+' denied '+mode)
 check(request(client(),path)[2]=='/login.php','anonymous denied '+mode)
 check(request(admin,path,{'form_token':'wrong'})[0]==403,'CSRF denied '+mode)
 check(request(admin,path,method='DELETE')[0]==405,'method denied '+mode)
 selectors=[]
 if mode in ['update-course','add-prerequisites']:selectors=['course_ID']
 elif mode in ['update-section','add-faculty-section','update-faculty-section']:selectors=['CRN']
 elif mode in ['update-major','create-major-requirements','update-major-requirements']:selectors=['major_ID']
 elif mode in ['update-minor','create-minor-requirements','update-minor-requirements']:selectors=['minor_ID']
 elif mode in ['add-student-section','drop-student-section','update-student-grade','change-grade']:selectors=['student_ID','CRN']
 elif mode in ['declare-major','add-minor','assign-advisor']:selectors=['student_ID']
 elif mode=='update-building':selectors=['Bldg_ID']
 if selectors:
  form=Form(html);query={k:next(v for v,label in form.selects[k] if v) for k in selectors}
  status,html,url=request(admin,path+'?'+urllib.parse.urlencode(query))
  check(status==200 and 'Current information' in html and 'Fatal error' not in html,'selected record '+mode)
print(f'PASS: {checks} Admin HTTP role, route, selector, CSRF and method checks. No academic writes.')
