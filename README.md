# FRANCO PAY

FRANCO PAY is a mini financial platform prototype built to demonstrate how a secure, transaction-aware financial system differs from a normal CRUD app.

## Features

- User registration and login
- Password hashing
- Session-based auth using bearer tokens
- Wallet balance retrieval
- Money transfer processing
- Duplicate request protection via `Idempotency-Key`
- Ledger entries for debit/credit records
- Audit logging for important actions
- Simple dashboard and send-money frontend

## Project structure

- `public/` — API entry point and frontend pages
- `src/` — app logic and models
- `database/` — SQL migrations
- `docs/` — API documentation
- `tests/` — simple end-to-end verification script

## Quick start

1. Make sure PHP 8+ is installed.
2. Start the server:

```bash
php -S localhost:8080 -t public
```

3. Open the frontend:

```text
http://localhost:8080/frontend/index.html
```

4. If you want to use a MySQL database, create a `.env` file in the project root and set DB credentials.

Example `.env`:

```env
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=franco_pay
DB_USER=root
DB_PASS=
```

If MySQL is not available, the app falls back to SQLite and creates the local database automatically.

Real environment variables take priority over `.env`, so a deployed platform's
injected configuration is never shadowed by a checked-in file.

## Deploying with a managed MySQL database (Railway)

The app reads its database configuration from the environment in this order:

1. `MYSQL_URL` or `DATABASE_URL` — the `mysql://user:pass@host:port/database`
   connection string published by Railway and similar platforms.
2. `MYSQLHOST` / `MYSQLPORT` / `MYSQLUSER` / `MYSQLPASSWORD` / `MYSQLDATABASE` —
   the discrete variables Railway's MySQL service exposes.
3. `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS`.

When any of the first two are present the driver switches to MySQL
automatically, so attaching a Railway MySQL service is enough — no `DB_DRIVER`
is required. Setting `DB_DRIVER` explicitly still overrides the detection.

On Railway, reference the database from the app service's variables:

```env
MYSQL_URL=${{MySQL.MYSQL_URL}}
```

Then create the tables once against that database:

```bash
php bin/migrate.php
```

`bin/migrate.php` applies `database/migrations/001_create_tables.sql` for MySQL.
Alternatively, import `schema.sql` through your host's database console.

Without a MySQL service configured the app falls back to the SQLite file in
`database/`, which lives inside the container and is discarded on every deploy —
so a deployment that appears to work but loses its data between releases is a
sign the database variables are not reaching the app.

## Core API endpoints

- GET `/api/health`
- POST `/api/auth/register`
- POST `/api/auth/login`
- GET `/api/wallet`
- POST `/api/transactions`
- GET `/api/transactions`

See [docs/API.md](docs/API.md) for detailed usage.

## Demo users

Seeded account:

- Email: `francis@example.test`
- Password: `password123`

## Verification

Run:

```bash
php tests/flow_check.php
```

This checks the health endpoint, registration, login, wallet lookup, transfer, and idempotent duplicate protection.


Repository: https://github.com/francis-pro142/franco-pay
