# FRANCO PAY API

Base URL: http://localhost:8080

## Health

GET /api/health

Returns server status.

## Authentication

### Register

POST /api/auth/register

Body:

```json
{
  "full_name": "Alice Sample",
  "email": "alice@example.test",
  "password": "secret123"
}
```

### Login

POST /api/auth/login

Body:

```json
{
  "email": "francis@example.test",
  "password": "password123"
}
```

Returns a token to use in the Authorization header.

## Wallet

### Get wallet

GET /api/wallet

Headers:

```text
Authorization: Bearer <token>
```

## Transactions

### Create transfer

POST /api/transactions

Headers:

```text
Authorization: Bearer <token>
Idempotency-Key: unique-request-key
Content-Type: application/json
```

Body:

```json
{
  "recipient": "WLT-000002",
  "amount": 100.00,
  "description": "School fees"
}
```

### List history

GET /api/transactions

Headers:

```text
Authorization: Bearer <token>
```

## Notes

- Duplicate requests with the same `Idempotency-Key` and same sender wallet are rejected as repeats.
- Transfers validate recipient existence, amount positivity, and sufficient funds.
- Ledger entries and audit entries are stored with each successful or failed transaction.
