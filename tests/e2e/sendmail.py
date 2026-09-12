#!/usr/bin/env python3
"""Restricted PHP-mail fallback for the recursion smoke; loopback only."""
import email,email.utils,smtplib,sys
message=sys.stdin.buffer.read()
parsed=email.message_from_bytes(message)
recipients=[address for _,address in email.utils.getaddresses(parsed.get_all('To',[])+parsed.get_all('Cc',[])+parsed.get_all('Bcc',[]))]
if not recipients or any(not address.endswith('.test') for address in recipients):
 raise SystemExit('Non-synthetic envelope refused')
with smtplib.SMTP('127.0.0.1',25281,timeout=2) as smtp:
 smtp.sendmail('sender@example.test',recipients,message)
