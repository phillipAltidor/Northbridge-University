"""Requirement-driven planning. Reservations are not grades or earned credits.

This ranks academically useful choices; the independent prerequisite evaluator,
full degree evaluator and timetable checker remain separate gates.
"""
import collections,itertools,math,re


def canonical(key):return key.split('@')[0]


def version_key(value):
    if isinstance(value,str):return value
    return value['courseCode']+('@'+value['catalogVersion']if value['catalogVersion']!='2026-2027'else'')


def source_sequences(sources):
    result={}
    for row in sources:
        text='\n'.join(row['pages']);matches=list(re.finditer(r'Sample Course Sequence',text,re.I))
        if not matches:continue
        tail=text[matches[-1].start():];terms=list(re.finditer(r'\bTerm\s+(\d+)\b',tail));ranks={};repeated=[]
        for i,m in enumerate(terms):
            part=tail[m.end():terms[i+1].start()if i+1<len(terms)else len(tail)]
            for prefix,number in re.findall(r'\b([A-Z]{2,3})\s*(\d{4})\b',part):
                code=prefix+number
                if code in ranks and ranks[code]!=int(m[1]):repeated.append(code)
                ranks.setdefault(code,int(m[1]))
        result[row['program']]={'courseTerms':ranks,'sourcePDF':row['sourcePDF'],'repeatedCourseReferences':sorted(set(repeated)),'policy':'Sample order is guidance; actual prerequisites, prior preparation and offerings govern. Repeated sample references do not require duplicate credit.'}
    return result


class PlanningCatalog:
    def __init__(self,programs,courses,rules,ge_rows,sequences=None,available_courses=None):
        self.programs={p['program']:p for p in programs};self.courses=courses;self.rules=rules;self.sequences=sequences or {};self.available=available_courses;self.ge=collections.defaultdict(set);self.cache={};self.decisions={};self.unsupported=set();self.budgets={}
        for r in ge_rows:
            if not r.get('lectureLabBundleCandidate'):self.ge[r['sourceCourseCode']].add(r['domain'])
    def selector(self,n):
        found=set()
        for key,c in self.courses.items():
            if c['catalogVersion']!='2026-2027':continue
            m=re.match(r'([A-Z]+)(\d+)',c['courseCode'])
            if not m or m[1]not in ([n['subject']]if isinstance(n.get('subject'),str)else n.get('subject',[])):continue
            level=int(m[2])
            if key in n.get('excludeCourses',[])or('minimumLevel'in n and level<n['minimumLevel'])or('levels'in n and level//1000*1000 not in n['levels']):continue
            if n.get('classification')and n['classification']not in c.get('classifications',[]):continue
            found.add(key)
        return found
    def units(self,node):
        codes=set(node.get('eligibleCourses',[]))
        if node['type']=='choose-by-selector':codes|=self.selector(node)
        for selector in node.get('selectors',[]):codes|=self.selector(selector)
        units=[frozenset([k])for k in sorted(codes)if k in self.courses]
        if node['type']=='choose-distinct-units':
            for option in node['options']:
                if isinstance(option,str)and option in self.courses:units.append(frozenset([option]))
                elif isinstance(option,dict)and option['type']=='course-bundle'and set(option['courses'])<=self.courses.keys():units.append(frozenset(option['courses']))
        return units
    def all_references(self,node,program,concentration=None):
        t=node['type']
        if t=='selected-concentration':return set().union(*(self.all_references(g,program,concentration)for g in program.get('concentrations',{}).get(concentration,{}).get('groups',[])))
        if t=='all-courses':return set(node['courses'])
        if t=='all-course-versions':return {version_key(c)for c in node['courses']}
        result=set().union(*self.units(node))if self.units(node)else set()
        for g in node.get('groups',[]):result|=self.all_references(g,program,concentration)
        for g in node.get('paths',{}).values():result|=self.all_references(g,program,concentration)
        for codes in node.get('domains',{}).values():result|=set(codes)
        return result
    def goals(self,person,kind,initial):
        name=person['major']if kind=='major'else person.get('minor')
        if not name:return []
        key=(person['studentId'],kind)
        if key in self.cache:return self.cache[key]
        p=self.programs[name];goals=[];concentration=person.get('concentration')if kind=='major'else None
        def add(label,units,count=1,credits=0,fixed=False,parent=None):
            goals.append({'id':label,'units':units,'minimumCount':count,'minimumCredits':credits,'fixed':fixed,'program':name,'kind':kind,'parent':parent})
        def compile(n,label,parent=None):
            t=n['type']
            if t in ['all-courses','all-course-versions']:
                for item in n['courses']:
                    code=version_key(item);add(label+'/'+code,[frozenset([code])],fixed=True,parent=parent)
            elif t=='selected-concentration':
                selected=p.get('concentrations',{}).get(concentration)
                if not selected:self.unsupported.add((name,'Missing or unreviewed concentration'));return
                for i,g in enumerate(selected['groups']):compile(g,label+'/concentration/'+concentration+'/'+str(i),parent)
            elif t=='choose-one-path':
                # Prefer an already-established path; otherwise use the first
                # published valid option and record it as a planning choice.
                ranked=[]
                for ordinal,(path,g)in enumerate(n['paths'].items()):
                    refs=self.all_references(g,p,concentration);ranked.append((-len(refs&initial),ordinal,path,g))
                _,_,path,g=min(ranked,key=lambda r:r[:2]);requested=person.get('planningPaths',{}).get(label)
                if requested is not None:
                    if requested not in n['paths']:self.unsupported.add((name,'Unknown planning path '+requested));return
                    path=requested;g=n['paths'][path]
                self.decisions[person['studentId'],label]=path;compile(g,label+'/path/'+path,parent)
            elif t=='allocation-group':
                for i,g in enumerate(n['groups']):compile(g,label+'/'+str(i),label)
                # Parent count/credit floors apply to the union of child courses.
                units=[frozenset([k])for k in sorted(self.all_references(n,p,concentration))if k in self.courses]
                add(label,units,n.get('minimumCount',0),n.get('minimumCredits',0),parent=parent)
            elif t=='choose-distinct-domain-courses':
                # Reserve distinct courses in the required number of domains.
                ranked=sorted(enumerate(n['domains'].items()),key=lambda r:(-len(set(r[1][1])&initial),r[0]))
                for _,(domain,codes)in ranked[:n['minimumDomains']]:add(label+'/'+domain,[frozenset([k])for k in codes if k in self.courses],n.get('coursesPerDomain',1),parent=parent)
            elif t in ['choose-distinct-courses','choose-credits','choose-from-union','choose-by-selector','choose-distinct-units']:
                add(label,self.units(n),n.get('minimumCount',0),n.get('minimumCredits',0),parent=parent)
            elif t=='reviewed-evidence':
                if n['key']=='psychology-bs-math-science-plan':
                    # Existing reviewed Northbridge science-plan selection:
                    # two Biology and two Chemistry semesters, with labs.
                    for code in ['MA2090','BS2400','BS2401','BS2410','BS2411','CP2120','CP2121','CP2130','CP2131']:add(label+'/science-plan/'+code,[frozenset([code])],fixed=True,parent=parent)
                # Non-course approvals remain independent audit requirements.
            else:self.unsupported.add((name,'Unsupported planning node '+t))
        for i,g in enumerate(p['groups']):compile(g,kind+'/'+str(i))
        self.cache[key]=goals;return goals
    def reservations(self,goals,reserved):
        used=set();assigned={g['id']:set()for g in goals};open_goals=[]
        # Single required courses precede flexible choices, so a required course
        # cannot silently consume an elective slot as well.
        ordered=sorted(goals,key=lambda g:(not g['fixed'],bool(any(x['parent']==g['id']for x in goals)),len(g['units'])))
        for g in ordered:
            child=set().union(*(assigned[x['id']]for x in goals if x['parent']==g['id']))
            covered=set(child);count=len(child)
            for unit in g['units']:
                if count>=g['minimumCount']and sum(self.courses[k]['credits']for k in covered if k in self.courses)>=g['minimumCredits']:break
                if unit<=reserved and not(unit&used):covered|=unit;used|=unit;count+=1
            assigned[g['id']]=covered
            credits=sum(self.courses[k]['credits']for k in covered if k in self.courses)
            if count<g['minimumCount']or credits<g['minimumCredits']:open_goals.append(g)
        return open_goals,assigned
    def routes(self,node,evaluate,preferred):
        if evaluate(node)in ['satisfied','conditional']:return set()
        t=node['type']
        if t in ['course','approved-transfer-course']:
            key=node['courseCode']+('@'+node['catalogVersion']if node.get('catalogVersion')and node['catalogVersion']!='2026-2027'else'')
            return {key}if key in self.courses and t!='approved-transfer-course'else None
        if t in ['and','or']:
            values=[self.routes(n,evaluate,preferred)for n in node['rules']]
            if t=='and':return None if any(v is None for v in values)else set().union(*values)
            choices=[v for v in values if v is not None]
            return min(choices,key=lambda v:(len(v-preferred),len(v),tuple(sorted(v))))if choices else None
        if t in ['corequisite-condition','completed-or-concurrent-condition']:return self.routes(node['rule'],evaluate,preferred)
        if t in ['proficiency','completed-writing-proficiency','completed-or-concurrent-proficiency']:
            key=node.get('key');return {'EL1000'}if key in ['ECI','CompositionI']else {'EL2216'}if key in ['ECII','CompositionII']else {'EL1000','EL2216'}if key in ['EMS','writing',None]else None
        return None
    def choices(self,person,reserved,selected,evaluate):
        held=set(reserved)|set(selected);result={};major=self.goals(person,'major',set(reserved));minor=self.goals(person,'minor',set(reserved));open_major,allocated=self.reservations(major,held);open_minor,minor_allocated=self.reservations(minor,held)
        required={next(iter(g['units'][0]))for g in major if g['fixed']and len(g['units'])==1 and len(g['units'][0])==1};sample=self.sequences.get(person['major'],{}).get('courseTerms',{})
        mandatory_codes=set(required)
        required|=set(sample)
        def definite(n):
            if n['type']=='course':return {n['courseCode']}
            if n['type']=='and':return set().union(*(definite(x)for x in n['rules']))
            if n['type']=='or':
                parts=[definite(x)for x in n['rules']];return set.intersection(*parts)if parts else set()
            return set()
        for _ in range(8):
            required|=set().union(*(definite(self.rules.get(k,{'type':'unknown'}))for k in list(required)))
        def propose(k,priority,role,reason,goal=None,bundle=None,root=None):
            if k not in self.courses or k in held:return
            if role in ['general-education','general-elective']:
                credits_so_far=sum(self.courses[x]['credits']for x in held if x in self.courses)
                if self.courses[k].get('level',0)>=3000 and credits_so_far<32:priority+=15
                elif self.courses[k].get('level',0)>=4000 and credits_so_far<57:priority+=8
            if role=='major-preparation'and k in mandatory_codes:role='major-required';reason='Required major course; '+reason
            value={'priority':priority,'role':role,'reason':reason,'goal':goal,'bundle':sorted(bundle or {k}),'root':root}
            if k not in result or (priority,reason)<(result[k]['priority'],result[k]['reason']):result[k]=value
        def prep(root,priority,role,reason,preferred,seen=None,depth=0):
            seen=set()if seen is None else seen
            if root in seen or depth>12:return
            need=self.routes(self.rules.get(root,{'type':'review-required'}),evaluate,preferred)
            if need is None:return
            for k in sorted(need-held):
                propose(k,priority-depth,role,reason+'; prerequisite for '+root,root=root)
                prep(k,priority-1,role,reason,preferred,seen|{root},depth+1)
        for g in open_major:
            label='Required major/concentration course'if g['fixed']else'Major elective/distribution choice'
            # Core progression and its actual prerequisite route take precedence.
            for unit in g['units']:
                for k in unit-held:
                    priority=10+sample.get(canonical(k),3)if g['fixed']else 45+sample.get(canonical(k),6)
                    propose(k,priority,'major-required'if g['fixed']else'major-elective',label+' in '+person['major'],g['id'],unit)
                    if g['fixed']:prep(k,5+sample.get(canonical(k),3),'major-preparation',label,required)
        # Prepare one route through a required choice group, including programs
        # without a published sample. Do not prepare every optional alternative.
        for g in open_major:
            if g['fixed']:continue
            units=[u for u in g['units']if not u<=held]
            if units:
                def route_cost(unit):
                    routes=[self.routes(self.rules.get(k,{'type':'review-required'}),evaluate,required)for k in unit]
                    need=set().union(*(r for r in routes if r is not None))
                    unavailable=self.available is not None and not unit<=self.available
                    return (any(r is None for r in routes),unavailable,len(need-required),min(sample.get(canonical(k),99)for k in unit),len(need),tuple(sorted(unit)))
                chosen=min(units,key=route_cost)
                for k in chosen-held:prep(k,8 if any(canonical(x)in sample for x in chosen)else 42,'major-preparation','Preparation for one required choice in '+person['major'],required)
        # College writing is a separate requirement even when communication GE
        # already has a reserved course. Do not put I and II in the same term.
        if 'EL1000'not in held:propose('EL1000',12,'college-writing','Required Composition I')
        elif 'EL1000'in reserved and 'EL2216'not in held:propose('EL2216',13,'college-writing','Required Composition II after Composition I')
        for g in open_minor:
            for unit in g['units']:
                for k in unit-held:
                    propose(k,55 if g['fixed']else 65,'minor-required'if g['fixed']else'minor-elective','Declared minor requirement: '+person['minor'],g['id'],unit)
                    # Minor preparation follows major foundations and missing GE.
                    if g['fixed']:prep(k,75,'minor-preparation','Preparation for declared minor '+person['minor'],required)
        # A minor's ordinary course count can be met while its outside-major
        # credit floor is still missing. Reserve additional eligible choices
        # for that explicit rule instead of silently treating shared work as
        # outside the major. Future fixed major courses remain shared as well.
        if person.get('minor'):
            policies=self.programs[person['minor']]['policies']
            outside_minimum=policies.get('minimumCreditsOutsideMajor',0)
            major_codes=set().union(*allocated.values())|mandatory_codes
            minor_codes=set().union(*(unit for g in minor for unit in g['units']))
            outside_held=(held&minor_codes)-major_codes
            if sum(self.courses[k]['credits']for k in outside_held)<outside_minimum:
                for g in minor:
                    if g['fixed']:continue
                    for unit in g['units']:
                        if unit&major_codes:continue
                        for k in unit-held:propose(k,60,'minor-overlap','Additional minor credits outside the major; minimum '+str(outside_minimum)+' credits',g['id'],unit)
        covered=set().union(*(self.ge.get(canonical(k),set())for k in held));all_domains=set().union(*self.ge.values())
        for k,domains in self.ge.items():
            missing=domains-covered
            if missing:propose(k,35-len(missing),'general-education','Unmet general education: '+'; '.join(sorted(missing)))
        # A load shortage alone never establishes an elective requirement.
        # Use a conservative upper bound on outstanding required credits: only
        # a residual beyond that bound can justify general electives now.
        def bound(goals,assignments):
            values={}
            for g in goals:
                available=[sum(self.courses[k]['credits']for k in unit if k not in held)for unit in g['units']if not unit<=held]
                largest=max(available,default=0)
                applied=sum(self.courses[k]['credits']for k in assignments[g['id']])
                applied_count=sum(unit<=assignments[g['id']]for unit in g['units'])
                values[g['id']]=max(0,g['minimumCredits']-applied,max(0,g['minimumCount']-applied_count)*largest)
            # Allocation parents share their child courses. Reserve the larger
            # floor, rather than adding the same requirement a second time.
            def subtree(g):
                children=[x for x in goals if x['parent']==g['id']]
                return max(values[g['id']],sum(subtree(x)for x in children))
            ids=set(values)
            return sum(subtree(g)for g in goals if g['parent']not in ids)
        held_credits=sum(self.courses[k]['credits']for k in held if k in self.courses)
        writing_budget=sum(self.courses[k]['credits']for k in ['EL1000','EL2216']if k not in held and k in self.courses)
        outstanding_bound=bound(open_major,allocated)+bound(open_minor,minor_allocated)+writing_budget+4*len(all_domains-covered)
        degree=self.programs[person['major']]['degreeRequirements']
        # College-wide distributions are real degree requirements as well.
        # Upper-level choices follow the foundation years; course prerequisites
        # are still evaluated separately before any assignment is possible.
        liberal=sum(self.courses[k]['credits']for k in held if k in self.courses and self.courses[k].get('liberalArts')is True)
        upper=sum(self.courses[k]['credits']for k in held if k in self.courses and self.courses[k].get('upperDivision')is True)
        if held_credits>=57:
            for k,course in self.courses.items():
                if course['catalogVersion']!='2026-2027':continue
                needs=[]
                if liberal<degree['minimumLiberalArtsCredits']and course.get('liberalArts')is True:needs.append('liberal arts')
                if upper<degree['minimumUpperDivisionCredits']and course.get('upperDivision')is True:needs.append('upper division')
                if needs:propose(k,85-len(needs),'degree-distribution','Unmet college-wide '+ ' and '.join(needs)+' credit requirement')
        elective_gap=max(0,degree['minimumTotalCredits']-held_credits-outstanding_bound)
        self.budgets[person['studentId']]={'heldCredits':held_credits,'outstandingRequiredBound':outstanding_bound,'minimumTotalCredits':degree['minimumTotalCredits']}
        if elective_gap:
            for k,c in self.courses.items():
                # The total-credit rule is a minimum. A normal four-credit
                # elective can close a one-credit remainder; do not demand a
                # nonexistent one-credit course or treat 120 as an exact cap.
                if c['catalogVersion']!='2026-2027'or k in held or c['credits']>max(4,elective_gap)or c['credits']<=0:continue
                if c.get('level',9999)>=3000 and held_credits<57:continue
                propose(k,95,'general-elective','Residual degree-credit budget: at least '+str(elective_gap)+' elective credits after reserving up to '+str(outstanding_bound)+' required credits')
        if 'EL1000'not in reserved:result.pop('EL2216',None)
        return result
    def redundant_preparation(self,bundle,choices,selected,selected_roles):
        for key in bundle:
            role=choices[key]['role'];subject=re.match('[A-Z]+',canonical(key))[0]
            if role not in ['minor-preparation','general-education','general-elective','degree-distribution']:continue
            if subject=='MA'and any(re.match('[A-Z]+',canonical(k))[0]=='MA'and selected_roles.get(k,{}).get('role')in ['major-preparation','major-required']for k in selected):return True
        return False
