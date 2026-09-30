"""Fresh disposable lab only: numbered login attempts, transfer replay, signed expiry.
Run from the matching project directory; PHP CLI signs with that project's secret.
This produces real HTTP evidence, not screenshots of a Windows Burp session.
"""
import argparse
import hashlib
import hmac
import http.cookiejar
import json
import re
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

p = argparse.ArgumentParser(description=__doc__)
p.add_argument('base')
p.add_argument('--edition', choices=['secured', 'vulnerable'], required=True)
a = p.parse_args()
base = a.base.rstrip('/')
if urllib.parse.urlsplit(base).hostname not in ('localhost', '127.0.0.1', '::1'):
    raise SystemExit('Local lab only')
opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def call(path, data=None, headers=None):
    req = urllib.request.Request(base + path, data=data, headers=headers or {})
    try:
        with opener.open(req, timeout=30) as r:
            return r.status, r.read().decode(), r.headers
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode(), e.headers

def token(page):
    return re.search(r'name="_token"[^>]*value="([^"]+)"', page).group(1)

def post(path, fields):
    return call(path, urllib.parse.urlencode(fields).encode(), {'Accept': 'application/json'})

# Use an unused synthetic email so the real Ali login is not locked by T5.
csrf = token(call('/login')[1])
for number in range(1, 7):
    status, body, headers = post('/login', {'_token': csrf, 'email': 'attempts-' + a.edition + '@wallet.test', 'password': 'incorrect'})
    print(json.dumps({'test': 'T5', 'attempt': number, 'http': status, 'retry_after': headers.get('Retry-After'), 'body': body}), flush=True)
    expected = 429 if number == 6 and a.edition == 'secured' else 422
    if status != expected:
        raise SystemExit('FAIL: login attempt sequence')
status, page, _ = post('/login', {'_token': csrf, 'email': 'ali@wallet.test', 'password': 'WalletLab!2026'})
csrf = token(page)
before = json.loads(call('/wallets/1')[1])
if int(before['wallet']['balance_minor']) != 10000000 or before['transactions']:
    raise SystemExit('STOP: fresh disposable seed required')
post('/beneficiaries', {'_token': csrf, 'beneficiary_user_id': 2})
fields = {'_token': csrf, 'receiver_id': 2, 'amount': '100', 'idempotency_key': uuid.uuid4().hex}
print(json.dumps({'test': 'T3', 'before': before}), flush=True)
ids = []
for number in (1, 2):
    status, body, _ = post('/transfers', fields)
    print(json.dumps({'test': 'T3', 'send': number, 'http': status, 'body': body}), flush=True)
    if status != 200:
        raise SystemExit('FAIL: replay request')
    ids.append(json.loads(body)['id'])
after = json.loads(call('/wallets/1')[1])
print(json.dumps({'test': 'T3', 'after': after}), flush=True)
expected = 9990000 if a.edition == 'secured' else 9980000
if int(after['wallet']['balance_minor']) != expected or (ids[0] == ids[1]) != (a.edition == 'secured'):
    raise SystemExit('FAIL: transfer replay invariant')

# Sign an already-expired timestamp with the real CLI signer; do not corrupt HMAC.
event = 'expired-' + uuid.uuid4().hex
raw = subprocess.check_output(['php', 'artisan', 'lab:sign-payment', event, '1', '50000', '--age=600'], text=True)
lines = raw.splitlines()
headers = {'Content-Type': 'application/json', 'Accept': 'application/json'}
for line in lines:
    if line.startswith('X-Payment-'):
        key, value = line.split(': ', 1)
        headers[key] = value
body = next(line for line in lines if line.startswith('{'))
status, response, _ = call('/webhooks/payment', body.encode(), headers)
expiry_after = json.loads(call('/wallets/1')[1])
print(json.dumps({'test': 'T4 expiry', 'signed_age_seconds': 600, 'http': status, 'body': response,
                  'balance_before': after['wallet']['balance_minor'], 'balance_after': expiry_after['wallet']['balance_minor']}), flush=True)
if status != (401 if a.edition == 'secured' else 200):
    raise SystemExit('FAIL: expiry response')
if int(expiry_after['wallet']['balance_minor']) != expected + (0 if a.edition == 'secured' else 50000):
    raise SystemExit('FAIL: expiry balance')
print('PASS: HTTP replay, numbered login attempts and correctly signed expiry comparison.')
