# Secure Fintech Wallet — assessment lab

A Laravel 12 / PostgreSQL wallet with two comparable editions. Synthetic money only. The application rejects non-loopback HTTP clients and non-local/non-testing environments.

- `main` and `secured`: controls enabled.
- `vulnerable-demo`: five deliberate weaknesses plus non-atomic transfers.
- Same routes, UI and seed data in both. The fixed `config/lab.php` setting and database migration determine the edition. There is no browser toggle.

## Requirements
PHP 8.2+ with pdo_pgsql, mbstring, openssl, tokenizer, XML, ctype, fileinfo and curl; Composer 2; PostgreSQL 15+; Git. No Node.js, Redis or frontend build required. CSS is bundled in Blade.

## Windows / PowerShell quick start

```powershell
git clone https://github.com/Iqra2030/secure-fintech-wallet.git
cd secure-fintech-wallet
composer install
Copy-Item .env.example .env
php artisan key:generate
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copy the last generated value into `WEBHOOK_SECRET` in `.env`. It is a local secret; do not commit it. Configure DB_USERNAME and DB_PASSWORD to match your PostgreSQL login.

In pgAdmin Query Tool, connected as your PostgreSQL administrator, create a local application role (replace the sample password):

```sql
CREATE ROLE wallet LOGIN PASSWORD 'choose-your-own-local-password';
CREATE DATABASE wallet_secured OWNER wallet;
CREATE DATABASE wallet_vulnerable OWNER wallet;
CREATE DATABASE wallet_testing OWNER wallet;
```

Run each CREATE DATABASE separately with autocommit enabled. The application role is not a superuser; for this lab it owns its tables and migrations. **RLS is not implemented.**

```powershell
# First installation only; set DB_DATABASE=wallet_secured and SESSION_COOKIE=wallet_secured_session in .env
php artisan migrate --seed
php -S 127.0.0.1:8085 -t public
```

Open http://127.0.0.1:8085. Demo accounts: `ali@wallet.test`, `sara@wallet.test`, `ahmed@wallet.test`. Password for each: `WalletLab!2026`. Each starts with PKR 100,000. Newly registered accounts start at zero.

### Run the vulnerable edition beside it
In a second terminal, from the secured checkout:

```powershell
git worktree add ../wallet-vulnerable vulnerable-demo
cd ../wallet-vulnerable
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edit that `.env`: `DB_DATABASE=wallet_vulnerable`, `APP_URL=http://127.0.0.1:8086`, `SESSION_COOKIE=wallet_vulnerable_session`. Set DB credentials and its own WEBHOOK_SECRET. Then:

```powershell
php artisan migrate --seed
php -S 127.0.0.1:8086 -t public
```

Keep distinct database names and cookie names: cookies are not isolated by port. Never run both editions against one database. If Git cannot find the branch, run `git fetch origin` and use `git worktree add ../wallet-vulnerable -b vulnerable-demo origin/vulnerable-demo`.

## Tests

`wallet_testing` must exist. It is a disposable database and tests rebuild its tables.

```powershell
php vendor/bin/phpunit
```

Run in each worktree. The tests assert both the expected vulnerability and the corresponding secured outcome. HTTP CSRF is bypassed by Laravel's test environment; manually verify it with Burp as described in the guide.

For a real two-request concurrency check against a running secured server, install Python 3 and run `python scripts/concurrency_check.py http://127.0.0.1:8085` immediately after seeding. This uses two independently logged-in sessions. PHP's built-in server is single-process by default, so use multiple workers on Linux (`PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=127.0.0.1`) or a concurrent PHP server to exercise actual overlap. The script alone on a single-worker server checks results but does not prove overlapping execution.

## Submission materials

- [Burp walkthrough](docs/BURP_GUIDE.md): exact local before/after tests.
- [Security design report](docs/SECURITY_REPORT.md): architecture, assets, justification and limitations.
- [Evidence sheet](docs/EVIDENCE.md): recorded test results and screenshot index.

To reset **only a disposable demo database**, confirm DB_DATABASE in `.env`, then run `php artisan migrate:fresh --seed`. This erases that database's application tables. Do not run against valuable data.

## Security scope

Controls: session authentication, password hashing, CSRF, ownership policy, amount validation, DB constraints, transfer idempotency, atomic debit/credit with ordered locks, webhook HMAC/freshness/event deduplication, login and transfer throttling, and audit records.

Not implemented: FIDO2, OIDC/JWT, RLS, envelope encryption/KMS, sophisticated fraud detection, production TLS, tamper-evident audit logs, HA or production deployment. HTTP is loopback-only in this lab; do not claim network encryption. Basic rate limiting is not a production distributed abuse-control system.

## Saved beneficiaries and inspecting your data

See [the beneficiary and database walkthrough](docs/DATABASE_AND_BENEFICIARIES.md). Existing installations need only `php artisan migrate` after pulling. Add Sara manually in both editions before the Burp transfer tests.

## Final assessment submission — 30 September 2026

Application behavior is frozen at the tested beneficiary release. This submission updates documentation only.

- [Recorded Tests 1–7 and control names](docs/TEST_RESULTS.md)
- [Submission and code download guide](docs/SUBMISSION_GUIDE.md)
- [Screenshot evidence index](docs/EVIDENCE.md)

Keep the original screenshots with your submission. GitHub source archives do not include your local PostgreSQL data, .env secrets, or installed Composer dependencies. FIDO2 remains future work.
