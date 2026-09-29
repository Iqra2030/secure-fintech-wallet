"""Run ONLY on a fresh disposable seed with a multi-worker local server."""
import concurrent.futures, http.cookiejar, json, re, sys, threading, urllib.request, urllib.parse, urllib.error
base=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8000'
assert urllib.parse.urlsplit(base).hostname in ('127.0.0.1','localhost','::1'), 'Local lab only'
def session():
 opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 page=opener.open(base+'/login').read().decode()
 token=re.search(r'name="_token"[^>]*value="([^"]+)"',page).group(1)
 data=urllib.parse.urlencode({'_token':token,'email':'ali@wallet.test','password':'WalletLab!2026'}).encode()
 page=opener.open(base+'/login',data).read().decode()
 token=re.search(r'name="_token"[^>]*value="([^"]+)"',page).group(1)
 return opener,token
sessions=[session(),session()]
opener,token=sessions[0]
opener.open(base+'/beneficiaries',urllib.parse.urlencode({'_token':token,'beneficiary_user_id':2}).encode()).read()
initial=json.load(sessions[0][0].open(base+'/wallets/1'))['wallet']['balance_minor']
assert int(initial)==10000000, 'Reset synthetic database before this test'
barrier=threading.Barrier(2)
def send(i):
 opener,token=sessions[i]
 request=urllib.request.Request(base+'/transfers',data=urllib.parse.urlencode({'_token':token,'receiver_id':2,'amount':'60000','idempotency_key':'race-'+str(i)}).encode(),headers={'Accept':'application/json'})
 barrier.wait()
 try:
  with opener.open(request) as response:return response.status
 except urllib.error.HTTPError as error:return error.code
with concurrent.futures.ThreadPoolExecutor(2) as pool: statuses=list(pool.map(send,range(2)))
final=json.load(sessions[0][0].open(base+'/wallets/1'))['wallet']['balance_minor']
print({'statuses':statuses,'sender_balance_minor':final})
assert sorted(statuses)==[200,422] and int(final)==4000000, 'Concurrency invariant failed'
print('PASS: one transfer accepted; sender has PKR 40,000. Check recipient/ledger using pgAdmin.')
