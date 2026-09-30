"""Two real HTTP processes; use ONLY a fresh disposable seeded database.

No artificial server delay is inserted. A vulnerable race may be inconclusive.
"""
import argparse
import concurrent.futures
import http.cookiejar
import json
import re
import threading
import urllib.error
import urllib.parse
import urllib.request
import uuid

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('base', nargs='?', default='http://127.0.0.1:8087')
parser.add_argument('--second-base', help='Second process sharing the same disposable DB')
parser.add_argument('--edition', choices=['secured', 'vulnerable'], default='secured')
args = parser.parse_args()
bases = [args.base.rstrip('/'), (args.second_base or args.base).rstrip('/')]
for base in bases:
    if urllib.parse.urlsplit(base).hostname not in ('localhost', '127.0.0.1', '::1'):
        raise SystemExit('Local lab only')


def csrf(page):
    match = re.search(r'name="_token"[^>]*value="([^"]+)"', page)
    if not match:
        raise RuntimeError('CSRF token missing; inspect server/login response')
    return match.group(1)


def session(base):
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    with opener.open(base + '/login', timeout=20) as response:
        token = csrf(response.read().decode())
    data = urllib.parse.urlencode({'_token': token, 'email': 'ali@wallet.test',
                                  'password': 'WalletLab!2026'}).encode()
    with opener.open(base + '/login', data, timeout=20) as response:
        token = csrf(response.read().decode())
    return opener, token


def state(opener, base):
    with opener.open(base + '/wallets/1', timeout=20) as response:
        return json.load(response)


sessions = [session(base) for base in bases]
for base, (opener, token) in zip(bases, sessions):
    before = state(opener, base)
    print(json.dumps({'server': base, 'before': before}), flush=True)
    if int(before['wallet']['balance_minor']) != 10000000 or before['transactions']:
        raise SystemExit('STOP: fresh disposable seed required; no transfer sent')
opener, token = sessions[0]
data = urllib.parse.urlencode({'_token': token, 'beneficiary_user_id': 2}).encode()
with opener.open(bases[0] + '/beneficiaries', data, timeout=20) as response:
    response.read()
barrier = threading.Barrier(2)
run_id = uuid.uuid4().hex


def send(index):
    opener, token = sessions[index]
    data = urllib.parse.urlencode({'_token': token, 'receiver_id': 2, 'amount': '60000',
                                  'idempotency_key': run_id + '-' + str(index)}).encode()
    request = urllib.request.Request(bases[index] + '/transfers', data=data,
                                     headers={'Accept': 'application/json'})
    barrier.wait(timeout=10)
    try:
        with opener.open(request, timeout=30) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()


with concurrent.futures.ThreadPoolExecutor(2) as pool:
    results = list(pool.map(send, range(2)))
for base, (status, body) in zip(bases, results):
    print(json.dumps({'server': base, 'http': status, 'response': body}), flush=True)
after = state(sessions[0][0], bases[0])
print(json.dumps({'after': after}), flush=True)
statuses = sorted(status for status, _ in results)
balance = int(after['wallet']['balance_minor'])
count = len(after['transactions'])
if args.edition == 'secured':
    if statuses != [200, 422] or balance != 4000000 or count != 1:
        raise SystemExit('FAIL: secured spending invariant')
    print('PASS: one accepted, one rejected, one transfer, Ali PKR 40,000.')
elif statuses == [200, 200] and balance == -2000000 and count == 2:
    print('VULNERABILITY OBSERVED: two transfers, Ali PKR -20,000.')
elif statuses == [200, 422] and balance == 4000000 and count == 1:
    print('INCONCLUSIVE: requests did not exercise the vulnerable overlap; this is not a security pass.')
else:
    raise SystemExit('UNEXPECTED RESULT: inspect responses and database before repeating')
