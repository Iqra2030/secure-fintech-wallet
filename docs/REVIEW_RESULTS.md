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

Pending the review revision's GitHub workflow. ReviewFixTest adds regression assertions; the workflow runs two real PHP processes for concurrency and actual HTTP requests for replay, correctly signed expiry and numbered login attempts on both editions. Vulnerable scheduling may yield INCONCLUSIVE. Manual evidence still needed: T3 screenshots, T4 expiry screenshot, T5 counted sequence and Windows vulnerable T7 comparison.
