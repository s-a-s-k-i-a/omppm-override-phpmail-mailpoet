#!/usr/bin/env python3
"""Loopback-only SMTP fault sink. Never relays, stores no message body."""
import argparse,json,socketserver,threading,time,re
p=argparse.ArgumentParser();p.add_argument('--log',required=True);a=p.parse_args()
lock=threading.Lock();attempts={}
def log(event):
 with lock:
  with open(a.log,'a') as f:f.write(json.dumps(event)+'\n')
class Handler(socketserver.StreamRequestHandler):
 def handle(self):
  mode=self.server.server_address[1]; recipients=[]; data=False
  def reply(s):self.wfile.write((s+'\r\n').encode())
  if mode==25283:time.sleep(3);return
  reply('220 isolated.test ESMTP')
  while True:
   line=self.rfile.readline()
   if not line:return
   text=line.decode(errors='replace').rstrip('\r\n');upper=text.upper()
   if data:
    if text=='.':
     log({'accepted':recipients});data=False;reply('250 2.0.0 captured')
    continue
   if upper.startswith(('EHLO','HELO')):
    if mode==25282:reply('250-isolated.test\r\n250 AUTH LOGIN PLAIN')
    else:reply('250 isolated.test')
   elif upper.startswith('AUTH'):log({'auth':'535'});reply('535 5.7.8 Synthetic authentication failure')
   elif upper.startswith('MAIL FROM:'):recipients=[];reply('250 2.1.0 OK')
   elif upper.startswith('RCPT TO:'):
    address=text.split('<',1)[-1].split('>',1)[0]
    with lock:attempts[address]=attempts.get(address,0)+1;n=attempts[address]
    code='250 2.1.5 OK'
    if not address.endswith('.test'):code='550 5.7.1 Non-test address forbidden'
    elif address.startswith('reject-'):code='550 5.1.1 Synthetic unknown recipient'
    elif address.startswith('policy-') and n==1:code='550 5.7.1 Synthetic policy rejection'
    elif address.startswith('temporary-') and n==1:code='450 4.2.0 Synthetic temporary rejection'
    log({'recipient':address,'response':code,'attempt':n})
    if code.startswith('250'):recipients.append(address)
    reply(code)
   elif upper=='DATA':data=True;reply('354 End with dot')
   elif upper=='QUIT':reply('221 bye');return
   elif upper=='RSET':recipients=[];reply('250 reset')
   else:reply('250 OK')
class Server(socketserver.ThreadingTCPServer):
 allow_reuse_address=True
 daemon_threads=True
for port in [25281,25282,25283]:
 server=Server(('127.0.0.1',port),Handler)
 threading.Thread(target=server.serve_forever,daemon=True).start()
print('SMTP sinks ready on loopback',flush=True)
threading.Event().wait()
