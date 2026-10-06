# Rice Ledger

A PHP 8.2+ / MySQL rice inventory application for XAMPP, built on the included Mini framework. No Composer or Node build step is needed.

## Start

1. Start Apache and MySQL in XAMPP.
2. Configure `.env` if your database connection differs. The default database is `rice_ledger`, with XAMPP's local root connection.
3. Run `php bin/console migrate` from this directory.
4. Set `ADMIN_NAME`, `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`, then run `php bin/console db:seed`. Sign in at `http://localhost/rice/public/login`. Alternatively, create the owner through `/setup`.
5. Add records through **Warehouses**, **Rice types** and **Suppliers** in the drawer menu, then record purchases.

The migration has already been applied to the local `rice_ledger` database. No demo business records are inserted.

Alternatively, run `php -S localhost:8000 -t public public/router.php` and open `http://localhost:8000/setup`. For deployment, serve `public/` as the document root, configure HTTPS, and keep `APP_DEBUG=false`.

## Business rules

- Quantities use **တင်း**, with up to three decimal places; currency uses **MMK**, with two decimal places.
- Each warehouse holds a separate balance for each rice type.
- One purchase has one supplier, receiving warehouse and date, with 1?50 rice items. **New purchase** uses three steps: purchase details, rice items, then payment and review. Add/remove searchable rice items; each item records its own quantity, optional weight and price. Back/Continue retains entered data, and invalid submissions reopen the relevant step with the rows retained. The server rounds each item amount before summing the voucher total and updates every rice balance atomically. Each rice type can be selected once per purchase. Selectors hide rice types used in other rows; changing or removing a row makes that rice type available again. Duplicate rice types are also rejected on the server.
- The purchase-items migration copies each existing purchase into a single item without changing historical amounts, payments, inventory or stock movements. Reports and supplier credit count the purchase total once. The register can search any included rice type and shows mixed prices for multiple items. All voucher paper sizes print every item. The migration intentionally blocks rollback because collapsing multi-item purchases would lose item data.
- Purchases can also record **Weight (ပေါင်)**, with up to three decimal places. Weight is optional per item, appears in the voucher (the register shows a total only when all items have weights), and does not change stock quantities or the price calculation. Older purchases have no recorded weight.
- Vouchers can be printed or saved as PDF through the browser print dialog.
- **Voucher settings** in the drawer configures the business name, voucher title, header/footer text, business address, phone, email, website and registration number. Choose A4, A5, 88 mm, 80 mm or 58 mm paper, and control whether weight, signatures and recorded-by details appear. Settings apply to existing and new vouchers when viewed or printed; original purchase records are unchanged. A saved-settings preview uses sample data without creating purchases. Receipt widths use a compact layout with an automatically calculated page length. Select matching paper in the printer driver, use 100% scale, and disable browser headers/footers; printer support determines whether custom receipt sizes are honored. Voucher settings use the existing business settings permission.
- **Stock transfers** also use the three-step wizard: source/destination warehouses, rice items, and review. Transfer up to 50 distinct rice types at once; the grid previews source availability, remaining stock, and the destination balance after transfer. Review shows both warehouses before and after. Transfers between the same warehouse are rejected; all movements commit together, shortages roll back both warehouses, and repeated submissions cannot move stock twice. Total inventory is conserved, and financial records are untouched. **Production** uses the same three-step wizard and compact item grid as purchases: source warehouse/date/notes, rice items, then review. Add up to 50 distinct rice types; each row shows available stock and the remaining balance. Quantity entry supports Tab, Enter and vertical arrow navigation. All items are deducted together, insufficient stock rolls back the entire submission, and repeated submissions cannot deduct stock twice. Production records stock movements only and creates no financial entries.
- Insufficient stock is rejected. Row locks serialize competing operations; duplicate form submissions use unique request keys.
- Reports use the purchase date, include both range endpoints, support daily/monthly/yearly grouping and one/all warehouses. Periods without purchases display zero. Transfers and production never count as purchases.
- The dashboard's monthly cards use the Asia/Rangoon calendar month. Historical operations immediately affect current inventory.
- Purchases, stock transfers and production records support detail views, editing and deletion. Open **Details**, **Edit** or **Delete** in the Purchase register or Stock movements action column. Detail pages show all rice items, warehouse information, dates, notes and the recorded user; purchase details also show supplier and payment amounts. Editing reuses the multi-step wizard with the saved items preloaded.
- Edits apply the net stock difference atomically; deletions reverse the saved record's stock effects. Changes are blocked if any affected warehouse would have negative stock, or if purchase changes would leave a supplier overpaid. Stale edits and repeated deletions cannot adjust balances twice. Owners have full access; staff need the separate edit/delete permissions for each operation.

## Directories and supplier credit

Warehouses, rice types and suppliers have separate drawer menu pages with search, create, edit and delete controls. Add and edit forms open in modals with Cancel, Escape and backdrop dismissal. Validation failures reopen the modal with entered values retained. Deletion requires confirmation. Records referenced by purchases, stock movements or payments cannot be deleted, and warehouses/rice types with stock are protected.

For a new purchase, **Paid now (MMK)** defaults to zero (credit). Enter a partial payment or use **Set fully paid**. Any unpaid amount adds to the supplier's credit. Purchase reports continue to show the full purchase amount regardless of payment status.

**Supplier credit** shows each supplier's purchase total, payments and balance to pay. Open **View / Pay** to record a later payment and review purchase/payment history. Payments settle the supplier's overall balance, without allocation to individual vouchers, and do not affect inventory. Overpayments and duplicate submissions are rejected or safely reused. Vouchers show the amount paid and credit at the time of purchase.

Purchases saved before the credit migration are treated as fully paid because they had no payment information. Supplier credit access and payment recording require the **View supplier credit and record payments** staff permission. Directory CRUD uses the existing directory management permission.

## Accounts

Configure the initial owner credentials in `.env`:

```dotenv
ADMIN_NAME="Super Admin"
ADMIN_EMAIL=admin@rice.local
ADMIN_PASSWORD="your-own-strong-password"
```

Run `php bin/console db:seed` after migrations to create the owner. Passwords must contain 10–72 characters and are hashed before storage. Re-running the seed leaves any existing owner and its credentials unchanged. Changing `.env` later does not reset an existing account's password. The local `.env` is ignored by Git; `.env.example` intentionally leaves the password empty.

The first account is the **Owner / Super admin** and has all permissions. Setup closes once this account exists. Only the owner manages staff accounts. Staff can be granted inventory, purchase viewing/vouchers, purchase entry, transfers, production, reports and business settings independently. Staff account editing supports password reset and disabling access; permissions are checked on the server and reflected in navigation.

Passwords are hashed. Sessions regenerate on sign in; cookies are HttpOnly with SameSite=Lax (Secure under HTTPS). POST routes require CSRF tokens. Sign in is limited to five failed attempts per IP/email in fifteen minutes.

**Profile settings** is available to every signed-in user. Users can edit their own name/email and change their password. Changing email or password requires verification of the current password. New passwords require 10–72 characters and matching confirmation. Password changes rotate the current session/CSRF token and revoke other sessions on their next authenticated request. Current-password verification is limited to five failed attempts per account/IP in fifteen minutes. Profile updates cannot change account roles or permissions. Password fields are never retained after validation errors.

## Languages

The sign-in screen and workspace header include an English / မြန်မာ selector. Select a language and press Apply. The choice is remembered in the session and a one-year HttpOnly preference cookie, including after sign-out. `APP_LOCALE=en` or `APP_LOCALE=my` in `.env` sets the default for visitors without a preference.

Menus, forms, wizard steps, tables, filters, reports, confirmation dialogs, validation messages, error pages, voucher labels, and units use the catalogs in `lang/en.json` and `lang/my.json`. English uses tin/lb; Myanmar uses တင်း/ပေါင်. Company names, configured voucher text, rice names, supplier details, and notes remain as entered.

Use `t('Message')` or `t('Message {count}', ['count' => $count])` for new interface text, then escape the result with `e()` in HTML. Keep routes, database enums, permission keys, dates used by forms, and submitted IDs independent of translated labels. JavaScript uses the same `window.t()` catalog and `textContent` for display.

Run `php tests/locales.php` for catalog and placeholder checks. `php tests/run.php` verifies language switching, persistence, localized pages/errors, CSRF, safe return paths, and unchanged user data. With the jsdom/PHP settings below, `node tests/locale-wizard.cjs` verifies Myanmar interactive messages, and `node tests/locale-pages.cjs` audits the 24 rendered Myanmar pages produced by the integration suite. These DOM checks do not verify browser layout.

## Verification

Run `php tests/run.php`. Tests create and remove an isolated random test database and launch a short-lived PHP server. They check setup, sign in, CSRF, purchases, exact currency, printable vouchers, replay protection, transfers, rollback, production, reports, directory CRUD, deletion protection, supplier payments, credit balances, account permissions and disabled sessions. The MySQL user needs create/drop database access for these tests. Run `php tests/admin-seed.php` to verify environment admin seeding.

Charts use the locally vendored Chart.js 4.4.8 distribution; see `public/assets/vendor/Chart.js-LICENSE.md`. [Chart.js documentation](https://www.chartjs.org/docs/latest/getting-started/index/) describes the bar chart integration. Fonts are loaded from Google Fonts with system fallbacks. Light/dark theme, compact/comfortable density, sidebar collapse and mobile navigation are included.

Client interaction checks can be run with `node tests/purchase-wizard.cjs` when the test-only `jsdom` module is available. `JSDOM_PATH` can point to its external installation and `PHP_BINARY` to the PHP executable. These checks exercise the actual PHP-rendered form and JavaScript; they do not verify browser rendering. `php tests/purchase-migration.php` checks preservation of existing business records during the multi-item migration.

Run `node tests/production-wizard.cjs` with the same test-only jsdom/PHP settings to verify production steps, live stock previews, shortage validation, retained rows and review/submission.

Run `node tests/transfer-wizard.cjs` with the same test-only jsdom/PHP settings to verify warehouse selection, item entry, both balance previews, shortage checks, Back navigation and transfer submission.
