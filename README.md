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
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=franco_pay
DB_USER=root
DB_PASS=
```

If MySQL is not available, the app falls back to SQLite and creates the local database automatically.

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
