# FRANCO PAY — Architecture

This document contains a high-level architecture diagram for the FRANCO PAY prototype.

```mermaid
flowchart LR
  subgraph Client
    Browser["Browser / Frontend"]
  end

  subgraph Server
    API["PHP Router\n(public/index.php)"]
    Services["Services\n(TransactionService, DatabaseConnection)"]
    Models["Models\n(User, Wallet, TransactionModel, Session, Audit)"]
    DB[("Database\nSQLite / MySQL\n(users, wallets, sessions, transactions, ledger_entries, audit_logs)")]
  end

  Browser -->|HTTP JSON| API
  API --> Services
  Services --> Models
  Models --> DB

  Services -->|BEGIN/COMMIT| DB
  Browser -->|Idempotency-Key header| API
  Services -->|INSERT ledger entries| DB
  Services -->|INSERT audit logs (hashed metadata)| DB

  note right of Services
    Key financial behaviors:
    - Atomic DB transactions for transfers
    - Idempotency protection (Idempotency-Key)
    - Immutable ledger entries for debit/credit
    - Audit logs for traceability
  end

  classDef important fill:#f9f,stroke:#333,stroke-width:1px;
  Services:::important
```

Notes
- The frontend (static assets in `public/frontend`) calls the API endpoints implemented in `public/index.php`.
- `TransactionService` performs the transfer using DB transactions, inserts ledger entries, and writes audit logs.
- Idempotency is enforced via `transactions.idempotency_key` and `TransactionModel::findByIdempotency`.
- Audit metadata is hashed before storage to protect sensitive information.
