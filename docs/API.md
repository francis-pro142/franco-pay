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
 
## Admin Audit (filtering + pagination)

GET /api/admin/audit

Query parameters (optional):

- `page`: page number (default 1)
- `page_size`: number per page (default 25, max 100)
- `q`: free-text search applied to `action`, `entity_type`, `entity_id`, `metadata`, and `ip_address`
- `action`: filter by action string (exact match)
- `user_id`: filter by numeric user id
- `entity_type`: filter by entity type (exact match)

- `from`: start date or datetime (ISO `YYYY-MM-DD` or `YYYY-MM-DD HH:MM:SS`) — inclusive
- `to`: end date or datetime (ISO `YYYY-MM-DD` or `YYYY-MM-DD HH:MM:SS`) — inclusive

- `sort_by`: column to sort by (`created_at`, `user_id`, `action`, `entity_type`) — default `created_at`
- `sort_order`: `ASC` or `DESC` — default `DESC`
- `export`: set to `csv` to download results as CSV

Response includes `items` (array of audit rows) and `pagination` with `page`, `page_size`, `total`, and `total_pages`.

