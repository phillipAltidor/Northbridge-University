"""Pinned sufficient routes recovered from the supplied complete course entries."""
import hashlib,json

PINNED={
 'BS2400':'c60c778a17d5f90ae58d859fbe6b90a53a1fc8d844dbbe4be9bf471876187339',
 'BS2401':'eb3849f5b5dfe5267775c7530f9ed73828714bd0026f9e0d7192c8dfe53f3932',
 'BS2410':'197e3efa9a0882d228321d7630373fad6dcd8393e1a09b4701df28ed55c04eb9',
 'BS2411':'ddc64a16d2c79f03a45492a1c6c303863ae4058e03bd72fc44c3ad1fddd3d830',
 'BU4180':'313b35643f72c9c18cec6a5aedeea1f5a91e2dd275fadc86970accd3ed76b3db',
 'BU5180':'b803b9ebb93c0b7a0cddd173cf95b03c6716d0f859e9a97dd8718fcb81a22f8a'}

def foundation_rules(original):
 for code,sha in PINNED.items():
  if hashlib.sha256(json.dumps(original[code],sort_keys=True).encode()).hexdigest()!=sha:raise ValueError('Re-review changed foundation prerequisite: '+code)
 def course(code,grade=None):return {'type':'course','courseCode':code,'minimumGrade':grade}
 def both(*rules):return {'type':'and','rules':list(rules)}
 def either(*rules):return {'type':'or','rules':list(rules)}
 def coreq(code):return {'type':'corequisite-condition','rule':course(code)}
 math=either(course('MA1020'),{'type':'review-required','reason':'The source math-proficiency alternative requires separate evidence review; MA1020 is an explicit sufficient course route.'})
 values={
  'BS2400':both({'type':'proficiency','key':'ECI'},coreq('BS2401')),
  'BS2401':both({'type':'proficiency','key':'ECI'},coreq('BS2400')),
  'BS2410':both(course('BS2400','C'),course('BS2401','C'),math,coreq('BS2411')),
  'BS2411':both(course('BS2400','C'),course('BS2401','C'),math,coreq('BS2410')),
  'BU4180':either(course('BU3250'),{'type':'review-required','reason':'The MA3250 cross-list reference is not defined in the supplied course catalog. Do not invent a course or equivalency.'}),
  'BU5180':both(course('MA2000'),either(course('BU3010'),course('BU3071')),course('BU3250'),course('CS2521'))}
 return {k:{'rule':v,'sourceRuleSHA256':PINNED[k],'status':'reviewed-sufficient-source-route','source':'Supplied 2026-2027 complete catalog course entry','doesNotWaiveAmbiguousAlternative':True}for k,v in values.items()}
