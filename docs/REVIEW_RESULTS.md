# Review revision validation

This revision changes application code after the original Windows demonstration. The original screenshots remain historical evidence. New manual screenshots have not been manufactured or inferred from automated output.

## Changes

- Visible transfer history joins sender and receiver names and shows amount, UTC time, status and full reference.
- Public wallet/transfer JSON uses an explicit column list and omits replay keys and request hashes.
- Invalid requests and service failures are audited outside the service transaction; completion auditing remains atomic with successful transfers.
- TransferService and WebhookController are formatted with comments explaining ordering, idempotency, transactions and signature checks.
- The ineffective different:sender_id request rule is removed; service/database checks enforce the secured self-transfer invariant.
- Stored session payload encryption is enabled. HTTPS Secure-cookie deployment remains outside the loopback HTTP demo.
- The login page no longer displays the seed password; seed credentials remain in lab setup documentation.

## Deliberate remaining limits

Registration reveals account existence through success versus rejection; beneficiary lookup reveals a registered name. Full mitigation needs an out-of-band identity verification/invitation flow, not just generic errors. Logs are not tamper-evident or separately durable against a full database outage. The audit fallback attempts a sanitized application warning; it does not guarantee delivery to an independent service.

## Validation status

Observed on 30 September 2026 in GitHub Actions with PHP 8.3.35 and PostgreSQL 16.15:

| Check | Secured | Vulnerable |
|---|---|---|
| PHPUnit | 28 tests, 132 assertions, passed | 28 tests, 127 assertions, one secured-only test skipped; passed expected edition behaviour |
| T7 two PHP processes | HTTP 422 + 200, one transfer, Ali PKR 40,000 | HTTP 200 + 200, two transfers, Ali PKR -20,000 |
| T3 exact replay | Same reference, one transfer, Ali PKR 99,900 | Distinct references, two transfers, Ali PKR 99,800 |
| T5 numbered attempts | Attempts 1–5: 422; attempt 6: 429, Retry-After 60 | Attempts 1–6: 422 |
| T4 correctly signed age 600s | 401; balance 9,990,000 paisa unchanged | 200 credited; 9,980,000 to 10,030,000 paisa |

Code revisions: secured/main `cb43e94c07e25469a7e6f4954dd2d4100d7409a6`; vulnerable `22cd03452c730a5b158192f7df994ea429a870b5`.

- [Secured main run 36698956126](https://github.com/Iqra2030/secure-fintech-wallet/actions/runs/36698956126)
- [Secured branch run 36698974366](https://github.com/Iqra2030/secure-fintech-wallet/actions/runs/36698974366)
- [Vulnerable run 36698993763](https://github.com/Iqra2030/secure-fintech-wallet/actions/runs/36698993763)
- Selected original output: [secured log](evidence/review-main.log) and [vulnerable log](evidence/review-vulnerable-demo.log).

These are real automated HTTP observations against two server processes, not recreated screenshots or a rerun on the student's Windows computer. No artificial delay was added to the service. A single observed race does not establish all schedules; later runs may report INCONCLUSIVE on the vulnerable edition. Manual T3 images, T4 expiry capture and a numbered T5 capture remain useful for the live demonstration. The Windows vulnerable T7 screenshot is still pending, but the missing vulnerable comparison now has recorded automated evidence.
