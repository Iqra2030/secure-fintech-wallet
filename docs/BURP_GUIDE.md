# Burp Suite Community before/after demonstration

Use only these local lab instances and synthetic data. Start with matching seeded databases. Secure is port 8085; vulnerable is 8086. Use Burp's built-in browser to avoid proxy setup. Leave Intercept off while navigating; use Proxy HTTP history → Send to Repeater. Add `Accept: application/json` to mutation requests for clear status codes. Preserve each instance's own session cookie and CSRF token. Never transfer cookies across editions.

Before each independent scenario reset the appropriate disposable database or record exact starting balances. Evidence must include request, response and state—not just status.

## 1. Wallet ownership / IDOR
Log in as Ali. Click “View my wallet JSON”. Send GET `/wallets/1` to Repeater. Change only the path to `/wallets/2`.
- Vulnerable: 200 and Sara's wallet/history.
- Secured: 403. Ali can still read `/wallets/1`.

## 2. Negative transfer
From Ali's dashboard submit a valid small transfer to Sara while intercepting it, or capture one then reset balances. Send POST `/transfers` to Repeater. Preserve `_token`, `receiver_id=2` and the idempotency key. Change `amount` to `-100.00` (bypassing HTML min=0.01).
- Vulnerable: sender gains PKR 100, recipient loses PKR 100.
- Secured: 422, neither balance changes, no transfer/ledger records.
Query both accounts through their own sessions or pgAdmin.

## 3. Duplicate transfer
Capture a fresh valid PKR 2,000 request and send it twice **with exactly the same idempotency_key**.
- Vulnerable: PKR 4,000 moved; two transfer records.
- Secured: PKR 2,000 moved; same transfer reference returned; one record.
Then change amount to 3,000 while retaining that key: secured returns 409. A new key denotes a new intended transfer; it is not a replay of the same operation.

## 4. Forged payment and signed replay
Create this request in Repeater (JSON amount is paisa):

```http
POST /webhooks/payment HTTP/1.1
Host: 127.0.0.1:8086
Content-Type: application/json
Accept: application/json

{"event_id":"forged-001","user_id":1,"amount_minor":50000}
```

Burp updates Content-Length. Vulnerable credits PKR 500. Secured, using port 8085, returns 401. A missing secured WEBHOOK_SECRET causes 503: fix configuration before claiming a successful signature test.

Generate a legitimate simulator request in the secured project:

```powershell
php artisan lab:sign-payment genuine-001 1 50000
```

Copy the exact body, timestamp and signature into Repeater within five minutes. Valid request credits once. Repeat: `already_processed`, no second credit. Alter the amount without signing again: 401. For freshness testing, wait beyond the five-minute acceptance window and replay: 401.

The signer is an operator CLI tool, not a publicly exposed signing endpoint. Do not include the actual shared secret in evidence.

## 5. Login rate limit
Log out. Capture one incorrect login request for `ali@wallet.test` with password `wrong`. Send to Repeater and send six times within 60 seconds with the same email, IP and CSRF/session context.
- Vulnerable: each request reaches password checking (422).
- Secured: first five are 422; sixth is 429 with Retry-After.
This demonstrates throttling, not password compromise. Use Repeater; no paid scanner required.

## 6. Forced partial failure — transaction atomicity
`php vendor/bin/phpunit --filter partial_failure` injects a test-only exception immediately after debit. There is no failure-trigger HTTP parameter.
- Vulnerable test records the inconsistent sender balance.
- Secured test checks restored sender and recipient balances and zero transfer rows. This focused test does not directly assert ledger row counts.

## 7. Concurrent spending — ordered row locks and balance recheck

For concurrency follow README's multi-worker instructions and run `scripts/concurrency_check.py` on a fresh secured seed. Two PKR 60,000 requests from a PKR 100,000 wallet should result in one success, one insufficient-funds rejection, sender PKR 40,000 and recipient PKR 160,000. Run on a concurrent server before claiming race-condition evidence.

## Additional checks
- Remove `_token` from a browser-session transfer: 419 in both versions. CSRF is retained, not a selected deliberate weakness.
- Log out then repeat an authenticated read: rejected.
- Sign in as Sara: her legitimate wallet read still succeeds.
- Check audit_events for actor/action/outcome/reference. Never log passwords or signing secrets.

## Database evidence
```sql
SELECT user_id, balance_minor FROM wallets ORDER BY user_id;
SELECT id, sender_id, receiver_id, amount_minor, idempotency_key FROM transfers ORDER BY created_at;
SELECT event_id, user_id, amount_minor FROM payment_events ORDER BY id;
SELECT user_id, SUM(amount_minor) AS ledger_balance FROM ledger_entries GROUP BY user_id ORDER BY user_id;
SELECT actor_id, action, outcome, reference, created_at FROM audit_events ORDER BY id DESC LIMIT 20;
```
Ledger balances should equal wallet balances after successful secured workflows. The test-only vulnerable failure intentionally breaks that invariant.

## Beneficiary prerequisite
Before transfer tests, log in as Ali in each edition and manually add `sara@wallet.test` through Add / manage beneficiaries. Otherwise the missing-beneficiary validation stops the request before the intended comparison.
