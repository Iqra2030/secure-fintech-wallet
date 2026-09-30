# Final test results and control demonstration

Assessment date: 29–30 September 2026. Project: Mini Secure Fintech Wallet. Tester: Iqra Ilyas. These results document the user-run Windows lab and supplied screenshots. No new manual execution is claimed by this documentation update.

## Environment and scope

Laravel 12 and PostgreSQL; PHP 8.4.25 in the recorded PHPUnit output; PHPUnit 11.5.56; Burp Suite Community and pgAdmin. Secure HTTP: 127.0.0.1:8085, database wallet_secured. Vulnerable HTTP: 127.0.0.1:8086, database wallet_vulnerable. Test 6 used disposable wallet_testing; Test 7 used two secured PHP processes on 8087/8088 sharing wallet_testing. Each edition uses a separate session cookie. All accounts and money are synthetic.

Source baseline: secured/main `f41bc21293352566fe4c9a97110065ab5c5ff453`; vulnerable-demo `70db46f129267b2ceaf291991c1b0eac91aeeb20`. Documentation-only submission commits preserve that application behavior. Screenshots do not independently attest the Windows checkout commit; these are the repository baselines associated with the tested release.

## Results at a glance

| Test | Security control name | Vulnerable observation | Secured observation | Assessment |
|---|---|---|---|---|
| T1: foreign wallet read | Object-level authorization / ownership Gate | Foreign wallet returned HTTP 200 | Foreign wallet HTTP 403; own wallet accessible | Passed exercised ownership check |
| T2: negative transfer | Server-side positive amount validation | HTTP 200, negative amount accepted; balances reversed | HTTP 422 validation rejection | Passed exercised validation check |
| T3: repeat transfer | Transfer idempotency | Repeated requests created distinct transfers | Existing transfer returned; user confirmed no further debit | Passed exercised replay check |
| T4: forged/tampered callback | HMAC-SHA256 verification and event deduplication | Unsigned callback credited | Unsigned/tampered 401; valid signed credited; replay already_processed | Passed exercised signature and replay checks |
| T5: repeated failed login | Login rate limiting | HTTP 422 invalid credentials | HTTP 429, Retry-After: 60 | Passed exercised throttling check |
| T6: failure after debit | Database transaction atomicity / rollback | Expected partial debit retained in test | Expected rollback restored balances | Both edition-specific assertions passed |
| T7: simultaneous spending | Ordered row locks and balance revalidation | No comparable vulnerable race result recorded | One 200, one 422, Ali PKR 40,000, one transfer | Passed bounded two-request scenario |

## T1 — Object-level authorization

While authenticated as Ali, capture GET /wallets/1 and change the owner identifier to /wallets/2. The vulnerable edition returned Sara's data with HTTP 200. The secured edition returned HTTP 403 while permitting Ali's own wallet read. The ownership Gate in AppServiceProvider and WalletController checks the authenticated subject against the requested wallet owner. Authentication alone cannot prevent an authenticated customer from changing an identifier.

The result supports confidentiality for the exercised wallet endpoint. It does not establish PostgreSQL row-level security: RLS is not implemented. Beneficiary ownership uses separately scoped application queries.

## T2 — Server-side monetary validation

Capture a transfer to saved beneficiary Sara and replace amount with -100, using a fresh operation key for this scenario. The vulnerable response recorded amount_minor=-10000 and a completed transfer. Ali's balance changed from PKR 99,900 to 100,000 and Sara's from 100,100 to 100,000. This was a new negative transfer that reversed the balance direction, not transaction rollback.

The secured response was HTTP 422 with amount-format and positive-value validation errors. Server-side validation matters because Burp bypasses the browser's minimum-value field. Secured database CHECK constraints provide another layer, but this HTTP rejection does not directly demonstrate those constraints. A separate direct SQL constraint test is not claimed.

## T3 — Transfer idempotency

Replay exactly the same successful transfer request with the same idempotency key. Vulnerable evidence showed distinct transfer references and repeated PKR 100 movements: the recorded balance pair moved from Ali 99,900 / Sara 100,100 to Ali 99,700 / Sara 100,300 after additional sends.

The secured replay returned the existing transfer reference 58505fbd-3bad-4d26-83e8-0ab9c7503fb8. The user confirmed that sending twice did not debit the balance again. The secured baseline screenshot showed Ali 48,800 / Sara 101,200; a separate final balance screenshot was not retained in the available evidence. The implementation binds a key to the sender and payload hash and checks it under the sender lock. A new key represents a new operation. The 409 changed-payload behavior is implemented but is not presented as an independently captured manual result here.

## T4 — Webhook authenticity, integrity and replay protection

Send an unsigned POST /webhooks/payment with event_id=forged-t4-001, user_id=1 and amount_minor=50000. The vulnerable callback returned credited. The secured callback returned HTTP 401. Generate a legitimate local callback using `php artisan lab:sign-payment genuine-t4-001 1 50000`, then copy the exact raw body, timestamp and signature into Repeater.

The valid secured callback returned credited. Changing amount_minor to 90000 while retaining the valid timestamp/signature returned 401. Restoring the original body and replaying returned already_processed. The tampered and replay responses were inside the same five-minute window, so the recorded tamper failure supports body-integrity verification rather than an expired timestamp explanation.

WebhookController checks an HMAC-SHA256 over timestamp + '.' + raw body with a constant-time comparison. A transaction, event identifier and payload hash prevent a valid event from crediting twice. The signer is a local operator tool simulating a trusted payment provider. Early attempts using the wrong running edition or a timestamp pasted into the signature field were setup errors, excluded from the successful security comparison. Separate manual evidence of timestamp expiry is pending; freshness enforcement exists in source.

## T5 — Login rate limiting

Repeat an incorrect login within one minute using the same account, IP and session/CSRF context. The secured screenshot shows HTTP 429 and Retry-After: 60; vulnerable shows HTTP 422 with invalid credentials. The user performed the repeated-send sequence; the retained final response screenshots do not independently count every attempt.

AuthController limits the account/IP key to five attempts and the IP key to thirty in the configured minute window. This demonstrates throttling of the exercised sequence, not MFA, password compromise or distributed production abuse resistance.

## T6 — Atomic rollback after an injected failure

Run `php vendor/bin/phpunit --filter test_partial_failure_rolls_back_only_in_secured_version --testdox` in each checkout. The test injects an exception after debit through a test-only callback; there is no public HTTP failure switch. Both screenshots show OK: 1 test, 4 assertions.

The secure test expects Ali's original 10,000,000 minor units, Sara's original 10,000,000 and zero transfer rows. The vulnerable test expects Ali at 9,800,000, Sara unchanged and zero transfer rows: a debit survived even though completion failed. Both green results are correct because the assertions intentionally differ by edition. This focused test does not directly count ledger rows. It rebuilds disposable wallet_testing and must not target the demonstration databases.

## T7 — Concurrent overspending attempt

Two independent logged-in sessions sent distinct PKR 60,000 transfers from Ali's freshly seeded PKR 100,000 wallet. Two PHP processes on ports 8087 and 8088 shared wallet_testing so a single-process HTTP server would not be the sole serialization mechanism. A Python thread barrier synchronized the sends; each request used a different idempotency key, exercising competing spending rather than replay deduplication.

The output shows port 8087 returning HTTP 422 (Insufficient funds), port 8088 returning HTTP 200, and Ali ending at PKR 40,000. pgAdmin shows one completed transfer for 6,000,000 minor units, reference 113c7ea1-0a52-4f8d-8e24-a3f4b30c47a6. The recipient's expected final balance is PKR 160,000; a dedicated final recipient-balance screenshot is not included in the retained evidence.

TransferService acquires ordered wallet locks inside a transaction and checks funds after locking. This observation is consistent with the intended protection. It is one two-request run; no lock-wait trace or exhaustive stress testing was captured, and no vulnerable race outcome was demonstrated. The repository's existing concurrency_check.py also supports a concurrent multi-worker server; the exact Windows two-process session was run interactively.

## Overall assessment and remaining scope

The secured edition resisted the specific malicious requests and preserved the tested transfer invariants. These results are suitable for a bounded classroom demonstration, not a production security certification. Application authentication, CSRF, beneficiary ownership, constraints and auditing are implemented; their presence must not be confused with a complete manual test of every path.

FIDO2/MFA, OAuth/OIDC, KMS/HSM, database RLS, advanced fraud scoring, production-grade distributed rate limiting, production TLS and tamper-evident external audit storage remain outside this release. Registration exists, but production email verification and account recovery are not implemented. Future work can be planned after submission without changing this tested baseline.
