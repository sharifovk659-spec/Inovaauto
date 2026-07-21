# Database migrations

SQL migrations for **production MySQL** on Hostinger. Applied once via `tools/migrate.php`.

## Rules

- One file = one logical change
- Naming: `001_description.sql`, `002_description.sql`, …
- Safe only: `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE`, indexes
- **Never** commit: `DROP DATABASE`, `DROP TABLE`, `TRUNCATE`, `DELETE`/`UPDATE` without `WHERE`

## Tracking

Table `schema_migrations` records applied files (created by `001_initial_tracking.sql`).

## Usage

```bash
# Dry run
php tools/migrate.php --dry-run

# Apply pending
php tools/migrate.php
```

`deploy.sh` runs migrations automatically after rsync (after DB backup).
