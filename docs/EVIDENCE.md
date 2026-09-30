# Recorded evidence index

Original screenshots were supplied during the user-run lab on 29–30 September 2026. Keep these files alongside the submission; raw screenshots are not committed here because request views can expose session cookies and CSRF values. See TEST_RESULTS.md for observations and limitations. Filenames are evidence references, not embedded images or proof that GitHub stores them.

| Test | Control | Original evidence filenames |
|---|---|---|
| T1 | Object-level authorization | t1.PNG; t1.2.PNG; t2.PNG; t2.1.PNG; t2.2.PNG |
| T2 | Positive amount validation | secure repeater result.PNG; T2.1 amount minor.PNG; wallet vul.PNG; wallet vul after repeater.PNG; wallet secure.PNG |
| T3 | Transfer idempotency | Twice repeater on secure wallet.PNG; wallet secure before repeater.PNG; Twice repeater send money.PNG; twise repeater on portal.PNG; wallet vul before repeater.PNG; wallet vul after twice repeater.PNG |
| T4 | HMAC verification / event deduplication | webhook attack on secure wallet.PNG; webhook attack on vulnerable wallet.PNG; image(20260930-063539).png; 401 when chnaged the amount against timestamp.PNG; already processed.PNG |
| T5 | Login rate limiting | 429 on secure wallet.PNG; 422 on vulnerable wallet.PNG |
| T6 | Transaction atomicity | secure third powershell.PNG; vul third powershell.PNG |
| T7 | Ordered locks / funds recheck | testing result.PNG; one created transfer.PNG |

T3 filenames refer to earlier conversation evidence; they are not all available in the current attachment directory. Preserve your original copies. The secured T3 final unchanged balance was user-confirmed; a separate final screenshot is absent. T7 recipient final balance and lock waits lack dedicated screenshots. Manual webhook expiry and direct database CHECK rejection are not marked as observed tests.

For submission, retain response codes, transfer/event references and relevant balances. Redact cookie headers, CSRF tokens, credentials and any real secrets. Do not alter response outcomes. Include the exact source commit from the GitHub download and record the selected database when reproducing a test.

## Review regression evidence

See REVIEW_RESULTS.md and evidence/review-main.log / evidence/review-vulnerable-demo.log for the actual GitHub HTTP runs. They add a vulnerable T7 negative balance, numbered T5 attempts, T3 replay balances and a correctly signed expired T4 message. They are automated logs, not new Windows screenshots.
