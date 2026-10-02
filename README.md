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

