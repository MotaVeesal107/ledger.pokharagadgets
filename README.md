# Ledger — Inventory & Ledger Management System

Plain PHP + MySQL (PDO) inventory, sales, purchases and party-ledger system
for Pokhara Gadgets. No framework, no build step, no Composer/CLI required —
deploy by uploading files and importing one SQL file via phpMyAdmin.

## What it does

- **Products** — CRUD with live, always-calculated stock (never a stored/editable field).
- **Purchases** — stock-in from suppliers, with per-unit serial/IMEI tracking for serialized products (phones, laptops).
- **Sales** — stock-out to customers or walk-ins, auto-numbered invoices (`INV-2026-0001`), split payments (Cash/eSewa/Khalti/Fonepay/Bank/Due), printable Bill No. / Tax Invoice.
- **Parties** — one shared supplier/customer model with a running balance and a chronological ledger.
- **Returns & Adjustments** — customer returns (restock or damaged) and independent damaged/lost write-offs, kept separate from normal sales/purchase totals.
- **Expenses** — simple category log feeding the profit estimate.
- **Dashboard** — today's sales by payment method, today's purchases, profit estimate, low stock, outstanding dues, recent activity.
- **Reports** — purchases, sales, stock ledger, IMEI/serial lookup with warranty status, profit & loss — all CSV-exportable.
- **Roles** — admin (full access) and staff (cost prices / financial reports hidden unless an admin turns that on in Settings).
- **VAT/PAN** — off by default (PAN-registered shop); a Settings toggle switches invoices from "Bill No." to "Tax Invoice" with VAT going forward.

## The one rule that keeps stock trustworthy

Current stock is **always** calculated by summing `stock_movements` (in − out)
for a product. It is never stored on the product row and never edited
directly anywhere in the UI. Every stock change — a purchase, a sale, a
customer-return restock, or a damaged/lost write-off — writes its own
`stock_movements` row through `includes/stock.php`, `includes/purchases.php`,
`includes/sales.php` or `includes/returns.php`. If a number ever looks wrong,
the `stock_movements` table for that product is the full audit trail.

## Deploying to ledger.pokharagadgets.com (cPanel)

### 1. Create the subdomain
In cPanel → **Domains** (or **Subdomains**), create `ledger` under
`pokharagadgets.com`. This gives you a document root such as
`/home/<cpanel-user>/ledger.pokharagadgets.com` (cPanel shows you the exact path).

### 2. Create the database
In cPanel → **MySQL Databases**:
1. Create a database (e.g. `pokhara_ledger`).
2. Create a database user with a strong password.
3. Add that user to the database with **All Privileges**.

Note the final database name, username and password — cPanel usually
prefixes both with your account name (e.g. `cpaneluser_ledger`).

### 3. Import the schema
Open **phpMyAdmin** from cPanel, select the new database, go to the **Import**
tab, choose `schema.sql` from this project, and run it. This creates every
table and seeds:
- Default settings (PAN/VAT off).
- One admin login: **admin@pokharagadgets.com / admin123**

### 4. Upload the files
Using **File Manager** (or FTP), upload the entire contents of this project
into the subdomain's document root — so `config.php`, `login.php`,
`schema.sql`, `/includes`, `/products`, etc. sit directly inside
`ledger.pokharagadgets.com/`, not inside an extra subfolder.

### 5. Configure the database connection
Edit `config.php` in File Manager and fill in the four values from step 2:

```php
$db_host = 'localhost';
$db_name = 'cpaneluser_ledger';
$db_user = 'cpaneluser_ledger';
$db_pass = 'the-password-you-set';
```

### 6. First login — change the admin password immediately
Visit `https://ledger.pokharagadgets.com/login.php`, sign in with
`admin@pokharagadgets.com` / `admin123`, then go to **Users** and set a new
password for the admin account (or create your own admin user and delete the
demo one).

### 7. Set up the shop
Go to **Shop Settings** and fill in the shop name, PAN number, address and
phone (these print on every invoice). Leave VAT off unless the shop becomes
VAT-registered later.

### 8. Add real data
1. **Products** — add each product. New products start at 0 stock; stock only
   comes in through a **Purchase**.
2. **Parties** — add your suppliers and customers. If you're migrating from
   an existing system, set each party's **opening balance** (positive =
   they owe the shop, negative = the shop owes them) so the ledger starts
   accurate.
3. Record a **Purchase** for existing inventory (e.g. from a generic
   "Opening Stock" supplier party) to bring your real stock levels in.

## Local structure

```
config.php              Database credentials (edit this)
schema.sql               Import via phpMyAdmin to create all tables
includes/                Shared PHP: auth, stock calc, party ledger, sales/purchase logic, CSV export, layout
dashboard/                Home page
products/, parties/, purchases/, sales/, returns/, expenses/, reports/, settings/, users/
assets/css/style.css      Custom styling on top of Bootstrap (CDN)
```

## Requirements

- PHP 7.4+ with PDO and the `pdo_mysql` extension (standard on any cPanel host).
- MySQL 5.7+ / MariaDB 10.2+.
- No SSH, Composer, or CLI access required.

## Security notes

- All database access goes through PDO prepared statements — no raw
  string-concatenated SQL.
- Passwords are hashed with `password_hash()` / verified with
  `password_verify()`; nothing is ever stored in plain text.
- Every state-changing form includes a CSRF token, checked on submit.
- Change the demo admin password before putting real data into the system.
