# FRANCO PAY — Release & Production Notes

This file gives minimal recommendations for running FRANCO PAY in a production-like environment.

1) Environment / `.env`

- Create a `.env` file in the project root and set these values:

```
APP_ENV=production
APP_KEY=change-me-super-secret
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=franco_pay
DB_USER=franco
DB_PASS=supersecret
SESSION_TTL=3600         # session expiration in seconds (recommended 3600)
CSV_EXPORT_RATE_LIMIT=60 # seconds between CSV exports per admin user
```

- Keep `APP_KEY` secret and generate a random 32+ character value.

2) HTTPS / TLS

- Terminate TLS at a reverse proxy (e.g. Nginx) or load balancer. Always serve the application over HTTPS in production.
- Use HSTS headers, secure cookies, and ensure `Authorization` tokens are transported only over HTTPS.

3) Sessions

- Use a persistent session store (Redis or database) rather than in-process or file-based sessions.
- Enforce `SESSION_TTL` on the server and rotate tokens on critical events.

4) Datastore

- Use MySQL or PostgreSQL for concurrency and `SELECT ... FOR UPDATE` behavior.
- Run migrations and backups routinely.

5) Security

- Do not commit `.env` to source control.
- Use a secret manager (Vault, AWS Secrets Manager) for production secrets.
- Rate-limit sensitive endpoints (login, export) at the proxy/API gateway.

6) Monitoring & Logging

- Centralize logs (ELK, CloudWatch) and monitor failed transactions and audit volumes.
- Alert on unusual rates of failed transactions or rejections.

7) CI/CD

- Use the provided GitHub Actions workflow as a starting point. Add integration tests and run them in CI.

8) Backup & Recovery

- Periodically backup your DB and test restore procedures.


Sample start (development):

```bash
# create sqlite DB and run app locally
php -S localhost:8080 -t public
```

For production, containerize and orchestrate the app behind a secure reverse proxy.``