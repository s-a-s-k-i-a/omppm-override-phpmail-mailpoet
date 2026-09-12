#!/usr/bin/env python3
import collections,json,sys
log,state,scenario=sys.argv[1:]
events=[json.loads(line) for line in open(log)]
emails=json.load(open(state))['emails']
accepted=collections.Counter(address for e in events for address in e.get('accepted',[]))
for index,address in enumerate(emails):
 expected=0 if scenario in ('permanent','full') and index==1 else 1
 assert accepted[address]==expected,(scenario,address,accepted[address],expected)
print('PASS capture:',scenario,'expected acceptances, no duplicates or lost healthy recipients')
