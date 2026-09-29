# Beneficiaries and database inspection

Update an existing installation without clearing its data:

```powershell
git pull --ff-only
php artisan migrate
php artisan config:clear
php -S 127.0.0.1:8085 -t public
```

Do not use `migrate:fresh` or reseed an existing demonstration database. The new migration creates only the beneficiaries table. Existing users, balances, transfers and ledger records remain.

From the dashboard choose **Add / manage beneficiaries**. Enter `sara@wallet.test`, choose **Find customer**, verify the name and choose **Confirm and add beneficiary**. Lookup does not save anything. Return to the wallet and select Sara to transfer. Remove affects only your saved relationship; transaction history remains. New installations start with no saved beneficiaries. To add a new customer to the system, register a separate account first (its balance starts at zero), then save that customer's email as a beneficiary from your original account.

In pgAdmin select **wallet_secured**, open Query Tool and run each query separately:

```sql
SELECT current_database();
SELECT id, name, email, created_at FROM users ORDER BY id;
SELECT u.name, u.email, w.balance_minor / 100.0 AS balance_pkr
FROM wallets w JOIN users u ON u.id = w.user_id ORDER BY u.id;
SELECT id, sender_id, receiver_id, amount_minor / 100.0 AS amount_pkr, status, created_at
FROM transfers ORDER BY created_at DESC;
SELECT id, user_id, transfer_id, amount_minor / 100.0 AS amount_pkr, description, created_at
FROM ledger_entries ORDER BY id DESC;
SELECT b.id, owner.name AS owner, recipient.name AS beneficiary, recipient.email, b.created_at
FROM beneficiaries b JOIN users owner ON owner.id=b.user_id
JOIN users recipient ON recipient.id=b.beneficiary_user_id ORDER BY b.id;
SELECT id, actor_id, action, outcome, reference, created_at FROM audit_events ORDER BY id DESC;
```

Amounts ending in `_minor` are integer paisa. Divide by 100.0 to display PKR. Re-run the query after each transfer; existing result grids do not refresh automatically.

For the Burp transfer comparisons, manually add Sara as Ali's beneficiary in **both editions first**. Use separate databases and distinct session cookies. Beneficiary ownership checks are retained in both editions; the five intentional weaknesses remain the existing lab comparisons. No FIDO2, beneficiary-age risk rule or manual approval is implemented by this update.
