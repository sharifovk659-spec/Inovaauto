# InovaAuto — Deployment Guide

No passwords or secrets in this document. Credentials live only in server `.env` (never in Git).

---

## Architecture

```
Cursor (local)  →  GitHub (private)  →  SSH Hostinger
                                              │
                    ┌─────────────────────────┴─────────────────────────┐
                    │                                                     │
            APP_DIR (git)                          PUBLIC_DIR (web root)
     ~/apps/inovaauto                         ~/domains/inovaauto.com/public_html
                    │                                                     │
                    └──────── rsync deploy.sh ──────────────────────────┘
                              (excludes .env, uploads, backups)
```

---

## Paths (Hostinger)

Run on server first to confirm real paths:

```bash
bash scripts/discover-paths.sh
```

| Purpose | Default path |
|---------|----------------|
| **Production (web)** | `/home/u417315406/domains/inovaauto.com/public_html` |
| **Git / app code** | `/home/u417315406/apps/inovaauto` |
| **Config (production)** | `public_html/.env` (not in Git) |
| **Uploads** | `public_html/uploads/` |
| **Backups** | `/home/u417315406/backups/inovaauto/` |
| **Logs** | `public_html/storage/logs/` |

Override via environment or `scripts/deploy.env.example`.

---

## SSH connection

```bash
ssh -p 65002 u417315406@45.84.204.68
```

Enter SSH password when prompted (never store in code or Git).

---

## Local workflow (before push)

### 1. Pre-deploy checks (Step 9)

```powershell
cd "c:\xampp\htdocs\Test innovaauto"
php tools/pre_deploy_check.php --base-url=http://localhost/Test%20innovaauto
bash scripts/pre-push-secret-check.sh
```

Checks: PHP syntax, `.htaccess`, DB `SELECT 1`, listings query, uploads writable, no hardcoded local paths, key pages HTTP 200.

### 2. Commit & push

```powershell
git add .
git commit -m "Your message"
git push origin main
```

GitHub repository must be **private**.

---

## First-time server setup

```bash
mkdir -p ~/apps ~/backups/inovaauto
cd ~/apps
git clone <YOUR_PRIVATE_GITHUB_URL> inovaauto
cd inovaauto
bash scripts/discover-paths.sh
chmod +x deploy.sh tools/post_deploy_check.sh scripts/*.sh
```

Ensure production `.env` exists in `public_html` (copy from `.env.hostinger.example`, fill MySQL values from hPanel).

Production `.env` should include:

- `IA_DB_DRIVER=mysql`
- `IA_BASE_URL=https://inovaauto.com`
- `IA_SESSION_SECURE=true`
- No `APP_DEBUG=true`

---

## Deploy

### Dry-run (Step 7 — no production file changes)

```bash
cd ~/apps/inovaauto
git pull origin main
bash scripts/dry-run-deploy.sh
```

### Full deploy

```bash
cd ~/apps/inovaauto
bash deploy.sh
```

`deploy.sh` automatically:

1. Verifies paths  
2. Backs up files + MySQL (timestamped)  
3. `git fetch` + `git reset --hard origin/main`  
4. PHP syntax check  
5. DB `SELECT 1`  
6. Pre-deploy gate  
7. `rsync` to `public_html` (no `--delete` by default)  
8. Safe migrations (`tools/migrate.php`)  
9. Post-deploy checks (`tools/post_deploy_check.sh`)  
10. Rollback on any failure  

### What is never overwritten

- `.env`
- `uploads/`
- `backups/`
- `storage/logs/`
- `config/local.php`, `config/database.production.php`
- `.git/` (not copied to public_html)

---

## Rollback

If deploy fails, `deploy.sh` restores files from the backup tarball created at deploy start.

Manual rollback:

```bash
BACKUP_ROOT=~/backups/inovaauto
ls -lt "$BACKUP_ROOT"/inovaauto-files-*.tar.gz | head -3
# pick latest before failed deploy
tar -xzf "$BACKUP_ROOT/inovaauto-files-YYYY-MM-DD-HH-MM.tar.gz" -C /tmp/rollback
rsync -a /tmp/rollback/public_html/ ~/domains/inovaauto.com/public_html/
```

Database restore (only if needed and you have a `.sql` backup):

```bash
# Use credentials from .env on server — do not paste passwords in terminal history if shared
mysql -u DB_USER -p DB_NAME < ~/backups/inovaauto/inovaauto-database-YYYY-MM-DD-HH-MM.sql
```

---

## Migrations

Location: `database/migrations/`

```bash
php tools/migrate.php --dry-run   # preview
php tools/migrate.php             # apply pending once
```

Rules:

- Production DB is never reset or re-imported  
- No `DROP`, `TRUNCATE`, bulk `DELETE`/`UPDATE` without `WHERE`  
- Each file runs once (tracked in `schema_migrations`)  
- Backup runs before deploy (includes DB dump)  

---

## Database connection check

```bash
php tools/deploy_db_check.php
# or
php health.php   # remove from public after debugging if exposed
```

Expected: `DB_OK` / `STATUS: OK`.

---

## Post-deploy checks (Step 10)

```bash
BASE_URL=https://inovaauto.com bash tools/post_deploy_check.sh
```

Or from local Windows (production live):

```powershell
# homepage should be 200
Invoke-WebRequest -Uri "https://inovaauto.com/" -UseBasicParsing
```

If any check fails:

1. Do not mark deploy as successful  
2. Identify cause (see script output)  
3. Rollback from backup  
4. Fix locally → commit → push → redeploy  

---

## Execution phases (Step 12)

| Phase | Status | Action |
|-------|--------|--------|
| 1 Audit | Done | Project structure documented |
| 2 DB config | Done | `.env` / `.env.hostinger.example` |
| 3 SSH paths | **Pending** | Run `discover-paths.sh` on server |
| 4 Backup | In deploy.sh | Auto before each deploy |
| 5 Git | Done | `main` branch, initial commit |
| 6 Deploy script | Done | `deploy.sh` |
| 7 Dry-run | **Pending your SSH** | `scripts/dry-run-deploy.sh` |
| 8 Production deploy | **Blocked** | Awaiting your approval |
| 9 Post-deploy test | In deploy.sh | `post_deploy_check.sh` |

**Production deploy will not run until you confirm the audit.**

---

## Required end-state workflow

1. Develop in Cursor  
2. `php tools/pre_deploy_check.php` locally  
3. Git commit  
4. Push to private GitHub  
5. SSH to Hostinger  
6. `deploy.sh` → backup → pull → rsync → migrate → test  
7. `.env`, uploads, DB config untouched  
8. Rollback on failure  
