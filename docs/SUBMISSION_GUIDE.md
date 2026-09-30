# Submission and download guide

## What to submit

1. Secured source ZIP from main (secured has the same secured release).
2. Vulnerable comparison source ZIP from vulnerable-demo, clearly labelled local assessment only.
3. docs/SECURITY_REPORT.md, docs/TEST_RESULTS.md and docs/EVIDENCE.md, included in each source archive.
4. Your original test screenshots, with session/CSRF/secret values redacted where present. Keep the T1–T7 grouping in EVIDENCE.md.
5. This guide and README.md for installation and demonstration.

## Download from GitHub

Sign in to the GitHub account with access to https://github.com/Iqra2030/secure-fintech-wallet. Choose main in the branch selector, then Code > Download ZIP. Repeat with vulnerable-demo. You can download a fixed version using `https://github.com/Iqra2030/secure-fintech-wallet/archive/<full-commit-sha>.zip`; record that SHA with your submission. Branch downloads reflect their latest state and can change later.

The repository is private. An instructor needs repository access to open private GitHub links; alternatively submit the downloaded ZIPs through your course portal. A ZIP contains tracked source and documentation, not your local database, .env, vendor directory or screenshots. Run composer install to obtain dependencies. Never add your .env to the submission.

## Update your existing Windows installations

Stop the server in each terminal before updating. In the secured folder:

```powershell
cd C:\Users\IQRA\secure-fintech-wallet
git status --short
git pull --ff-only
php artisan config:clear
php -S 127.0.0.1:8085 -t public
```

In the vulnerable folder:

```powershell
cd C:\Users\IQRA\wallet-vulnerable
git status --short
git pull --ff-only
php artisan config:clear
php -S 127.0.0.1:8086 -t public
```

If Git reports conflicting local changes or a non-fast-forward state, preserve those changes and resolve that message before continuing. The review update changes application code but requires no database schema migration or reseeding. Run php artisan config:clear and log in again because stored sessions are now encrypted. Existing balances and test history remain in PostgreSQL. First-time setup is covered in README.md; beneficiary/database queries are in DATABASE_AND_BENEFICIARIES.md.

## Demonstration order

Show legitimate login, beneficiary lookup/confirmation and a normal transfer. Then demonstrate T1 ownership, T2 validation, T3 transfer replay, T4 signed webhook integrity/deduplication, T5 throttling, T6 rollback and T7 competing spending. State the security control name before each test. Use the recorded evidence when repeating a state-changing test would disturb your saved demonstration balances.

Use wallet_testing only for destructive automated test setup. Never point PHPUnit or migrate:fresh at wallet_secured or wallet_vulnerable if preserving the current evidence. T7's temporary servers used 8087/8088, separate from the demo servers 8085/8086.

## Release boundary

The review revision adds response minimization and failure auditing and completes visible transfer history. FIDO2 and other proposed extensions remain future work. Documented manual results reflect the recorded lab session; any later code change needs its own validation record.
