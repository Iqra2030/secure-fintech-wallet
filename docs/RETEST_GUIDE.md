# Retest and evidence completion

After pulling in each checkout: `php artisan config:clear`, restart its server and log in again. Session encryption changes invalidate old plaintext sessions. No migration or reset of wallet_secured or wallet_vulnerable is required. All destructive setup below is ONLY for disposable wallet_testing.

## T3 transfer replay

On each edition, save Sara as Ali's beneficiary. Record both balances and the current transfer count in pgAdmin. Capture one new PKR 100 transfer. In Repeater send the exact same request/key again. Capture both response references, both wallet balances and transfer count after replay. Secured should return the original reference with no additional debit; vulnerable should create a new reference and another debit. The initial browser send already counts as an execution. Do not change the key between the original and replay.

## T4 correctly signed expiry

In the secured directory run `php artisan lab:sign-payment expired-manual-001 1 50000 --age=600`. Copy the exact body, signature and timestamp into Repeater. Capture the 401 response and unchanged wallet/payment-event count. This signs a timestamp already ten minutes old; simply changing a previously signed timestamp would confound expiry with an invalid signature. Use a new event ID for each independent run. Sign with the matching edition's secret when comparing the vulnerable callback.

## T5 show attempt count

Use a new synthetic email (for example attempt-proof-001@wallet.test), the same IP and a valid session/CSRF context. Send the wrong-password login six times within 60 seconds. Retain each request/response in numbered Repeater tabs 1–6, or record a continuous screen capture showing the sends. The secure first five responses are 422 and sixth 429; vulnerable remains 422. Capture Retry-After. A single final screenshot cannot prove the sequence. The HTTP evidence script also prints every numbered attempt but is automated evidence, not a manual Burp capture.

## T7 two vulnerable processes

Stop any process currently using wallet_testing before changing its schema. In TWO PowerShell windows, use the vulnerable checkout and these session-only environment variables:

```powershell
cd C:\Users\IQRA\wallet-vulnerable
$env:DB_DATABASE="wallet_testing"
$env:SESSION_COOKIE="wallet_concurrency_session"
php artisan config:clear
```

In the first window ONLY, confirm this is disposable wallet_testing, then run `php artisan migrate:fresh --seed` and `php -S 127.0.0.1:8087 -t public`. In the second window run `php -S 127.0.0.1:8088 -t public`. Do not run migrations in both windows.

From a third window in that checkout:

```powershell
py scripts/concurrency_check.py http://127.0.0.1:8087 --second-base http://127.0.0.1:8088 --edition vulnerable
```

Capture the entire output plus pgAdmin balances and transfer count. If both requests pass the stale funds check, two transfers can leave Ali at PKR -20,000. If scheduling serializes the requests before that check, the script reports INCONCLUSIVE. Do not call that proof of security or invent the negative balance. Repetition requires a fresh disposable seed with no in-flight requests. Do not add sleeps to production transfer code to force a result.

Repeat with the secured checkout, rebuilding wallet_testing from that edition after stopping both vulnerable processes, using `--edition secured`. Expect one accepted transfer, one 422, Ali PKR 40,000, Sara PKR 160,000 and one transfer. Keep the demonstration databases untouched.

## Inspect new failure audits

In pgAdmin select the matching database and submit a malformed amount, an insufficient-funds transfer and a secured changed-payload/same-key request. Query:

```sql
SELECT actor_id, action, outcome, reference, created_at
FROM audit_events WHERE action = 'transfer' ORDER BY id DESC LIMIT 20;
```

Expect validation_rejected, idempotency_conflict or processing_failed as appropriate. Failure rows contain no submitted keys/hashes, session values or raw payload. Completed rows commit with the transfer. The injected-failure PHPUnit test verifies that a failure row survives the secured rollback.
