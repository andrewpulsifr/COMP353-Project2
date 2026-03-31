# Database Setup + Smoke Test

This repo assumes the remote MySQL host and schema used by the web UI:
- Host: `qwc353.encs.concordia.ca`
- Database: `qwc353_4`

## 1) Apply schema + seed data

WARNING: `sql/setup.sql` **drops and recreates** all project tables in `qwc353_4`.

Run (from repo root):

```bash
mysql -h qwc353.encs.concordia.ca -u <YOUR_ENCS_USER> -p qwc353_4 < sql/setup.sql
```

If you don’t have the `mysql` CLI installed on macOS, install it via Homebrew:

```bash
brew install mysql-client
```

Then ensure your shell can find it (Homebrew will print instructions).

## 2) Run the PHP smoke test

The smoke test connects using environment variables (it does not use the web login/session):

```bash
DB_USER=<YOUR_ENCS_USER> \
DB_PASSWORD=<YOUR_PASSWORD> \
php scripts/db_smoke_test.php
```

Optional overrides:

```bash
DB_HOST=qwc353.encs.concordia.ca DB_NAME=qwc353_4 DB_USER=... DB_PASSWORD=... php scripts/db_smoke_test.php
```

### What it checks

- Required tables and columns exist (as used by `queries.php` and `transactions.php`)
- Minimum row counts (strong tables >= 10, weak tables >= 5)
  - `VEHICLE_RATE` is treated as master-data (>= 3 rows: Tourism/Heavyweight/SuperHeavyweight)
- Queries 1–9 execute successfully (including group-by variants for queries 3 and 4)
- Stored procedures exist and basic behavior works:
  - `update_mission_details` updates mission fields
  - `cancel_mission` removes a disposable mission

## 3) Manual UI check

1. Start the app (however you normally serve PHP).
2. Login with your ENCS DB credentials.
3. Run Queries 1–9.
4. Run Transactions 10 and 11.

## Notes

- If your team’s report defines stored procedure behavior differently, update `sql/setup.sql` to match that spec (this repo currently implements the simplest behavior that matches the web UI).
