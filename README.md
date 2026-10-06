# Rice Ledger

Rice inventory, purchases, printable vouchers, warehouse transfers, production
deductions and purchase reports, with owner/staff permissions.

**Start here:** [Application setup and usage](README-rice.md).
The local database migration is applied. Set `ADMIN_NAME`, `ADMIN_EMAIL` and
`ADMIN_PASSWORD` in `.env`, run `php bin/console db:seed`, and sign in at
http://localhost/rice/public/login. You can also create the first owner through
`/setup`. Use the Warehouses, Rice types and Suppliers drawer pages to manage
business records. Supplier credit shows balances to pay and payment history.

Run `php tests/run.php` for isolated integration verification.

## Framework notes

Requires PHP 8.2+, MySQL and PDO MySQL.

Start the application from this directory:

```sh
php -S localhost:8000 -t public public/router.php
```

Open http://localhost:8000 after running `php bin/console migrate`.
For Apache, point the document root at `public/` and enable mod_rewrite.
On XAMPP, use `http://localhost/<folder>/public` with APP_URL left empty.

Define routes in `routes/web.php`, controllers in `app/Controllers`, views in
`app/Views`, and configuration in `config`. Edit `.env` for each installation.
Protect forms with `csrf_field()`. Escape output with `e()`.

Use `Mini\Database` for prepared queries. Add migration files to `migrations/`
returning `['up' => ['SQL...'], 'down' => ['SQL...']]`, then run
`php bin/console migrate`. The console also supports `migrate:status`,
`migrate:rollback`, and `db:seed` (defined in `database/seed.php`).
Migration commands target MySQL and may create the configured database.
MySQL DDL is not transactional; review migration SQL before running it.

Authentication callbacks in `config/auth.php` use the application's users and
permissions. Supply a unique SESSION_NAME for apps sharing a host.

Set APP_DEBUG=false in production, configure HTTPS on your web server,
and keep the document root at `public/`.
