# My Small App

Requires PHP 8.2+. MySQL and PDO MySQL are needed only for database features.

Start the application from this directory:

```sh
php -S localhost:8000 -t public public/router.php
```

Open http://localhost:8000. The starter works without a database.
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

Authentication is application-owned. Configure `check` and `can` callbacks in
`config/auth.php` before adding protected routes. Sessions and CSRF work without
users or roles tables. Supply a unique SESSION_NAME for apps sharing a host.

Set APP_DEBUG=false in production, configure HTTPS on your web server,
and keep the document root at `public/`.
