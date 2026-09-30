# Mini Secure Fintech Wallet — assessment report

## System overview
A local Laravel/PostgreSQL application supports registration, login, balance viewing, transfers, transaction records and logout. A local payment simulator supplies signed credit callbacks. All identities and money are synthetic. Two editions share routes and seed data, enabling controlled before/after comparisons.

## Architecture
```mermaid
flowchart TD
 B["Browser / Burp"] --> A["Laravel: sessions and CSRF"]
 A --> P["Ownership, validation and rate limits"]
 P --> T["Transfer service: idempotency and locks"]
 G["Local gateway simulator"] --> H["HMAC, freshness and event deduplication"]
 H --> D["PostgreSQL: wallets and ledger"]
 T --> D
 T --> L["Audit events"]
 H --> L
```

## Security design table
| ID | Asset | Weakness and impact | Implemented control | Location | Verification |
|---|---|---|---|---|---|
| W1 | Customer balances/history | User-controlled owner ID exposes another customer's information | Gate requires authenticated subject to own requested wallet | WalletController::show and AppServiceProvider | Change owner ID; compare 200 vs 403 |
| W2 | Balance integrity | Negative amount reverses transfer direction | Decimal-string validation, positive DB constraint; money in integer paisa | WalletController, TransferService, migration | Submit -100; verify balances |
| W3 | Transfer integrity | Retry executes transfer twice | User-bound key and payload hash; sender lock and unique constraint | TransferService, transfers table | Repeat identical key; one transfer only |
| W4 | Payment credits | Forged/replayed callback creates money | HMAC over timestamp and raw body, five-minute freshness, unique event ID in transaction | WebhookController | Unsigned, tampered, expired and repeated signed requests |
| W5 | Credentials/access | Unbounded guessing attempts | Five attempts per account/IP per minute; additional 30/IP cap | AuthController | Sixth failure returns 429 |
| W6 | Consistent balances | Crash between debit/credit leaves partial update; simultaneous spending can overdraw | Database transaction, ordered row locks, balance recheck and nonnegative constraint | TransferService | Inject failure; multi-worker concurrency test |

## Assets and trust boundaries
Credentials, session cookies, customer details, balances, transfer records, webhook secret and audit events are assets. Browser data is untrusted. The gateway callback crosses a separate trust boundary and does not use browser CSRF tokens; it instead proves message authenticity with a shared secret. The database enforces constraints in addition to application validation. The runtime role is not a PostgreSQL superuser; it owns lab tables, so this is not a fully separated production privilege design.

## Design principles
- Confidentiality: ownership checks restrict customer information; password hashes avoid plaintext password storage.
- Integrity: validation, idempotency, signatures, constraints and atomic ledger updates protect monetary state.
- Authentication and authorization: a session identifies the user; a separate ownership decision controls each wallet read.
- Defense in depth: application validation and database constraints independently reject invalid secured monetary states.
- Availability: basic throttling reduces repeated request abuse; it does not solve denial of service.
- Accountability: audit events record selected login, read-denial, transfer and webhook outcomes. They are not tamper-proof.
- Resilience: failed secured transfers roll back. Backup/restore and failover are not implemented.

## Test evidence
The user performed Tests 1–7 on 29–30 September 2026. See TEST_RESULTS.md for observed outcomes, control names, interpretation and limitations; EVIDENCE.md maps the original screenshot names. These are user-run lab results, not a new independent penetration test. Test 7 used two PHP processes on ports 8087 and 8088 against wallet_testing. It demonstrated the expected two-request result, but no database lock-wait trace was captured. A separate expired-timestamp manual result is not recorded.

## Individual contribution
Complete honestly: author name, application/design work, testing, report work and any AI assistance required by course rules. Do not invent team members. The brief lists groups of 2–3; document the approved arrangement if submitting individually.

## Limitations and improvements
Local assessment only. No real money, external payment gateway, FIDO2, OIDC, KMS, RLS, production TLS, advanced fraud engine or high availability. Loopback HTTP is not transport encryption. Audit tables can be changed by a database owner. Recipient names/emails are intentionally shown to authenticated lab customers; a real service would need a privacy-preserving beneficiary lookup. Registration uses basic throttling; no email verification/account recovery. Add strong transaction approval, separated DB roles, immutable external audit storage, HTTPS and tested recovery before considering production use.

## Release scope

The tested application baseline is secured commit `f41bc21293352566fe4c9a97110065ab5c5ff453` and vulnerable comparison commit `70db46f129267b2ceaf291991c1b0eac91aeeb20`. The final submission adds documentation to these baselines without changing application code. Registration, beneficiary lookup/add/remove and transfer history already exist. New accounts start at zero; beneficiaries are registered customers explicitly saved by the authenticated sender.

The seven scenarios support a bounded conclusion: the secured implementation resisted the specific exercised requests and maintained the tested monetary invariants. They do not establish universal security, production readiness or complete race-condition coverage. No additional controls were added after the tests.
