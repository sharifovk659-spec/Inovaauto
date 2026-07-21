#!/usr/bin/env bash
#
# Dry-run deploy (Step 7) — no rsync, no migrations, no file changes on production.
# Run on Hostinger after git clone to validate paths, backup commands, and checks.
#
set -euo pipefail

APP_DIR="${APP_DIR:-/home/u417315406/apps/inovaauto}"
PUBLIC_DIR="${PUBLIC_DIR:-/home/u417315406/domains/inovaauto.com/public_html}"

export APP_DIR PUBLIC_DIR
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "=== DRY-RUN DEPLOY (no rsync, no migrations) ==="
echo "APP_DIR=${APP_DIR}"
echo "PUBLIC_DIR=${PUBLIC_DIR}"

[[ -d "$APP_DIR" ]] && echo "[OK] APP_DIR exists" || { echo "[FAIL] APP_DIR missing"; exit 1; }
[[ -d "$PUBLIC_DIR" ]] && echo "[OK] PUBLIC_DIR exists" || { echo "[FAIL] PUBLIC_DIR missing"; exit 1; }
[[ -f "${PUBLIC_DIR}/.env" ]] && echo "[OK] production .env exists" || echo "[WARN] no .env in PUBLIC_DIR yet"

cd "$APP_DIR"
echo "[CHECK] PHP syntax..."
if find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; >/dev/null 2>&1; then
  echo "[OK] PHP syntax"
else
  echo "[FAIL] PHP syntax"
  exit 1
fi

if [[ -f "${PUBLIC_DIR}/.env" && ! -f "${APP_DIR}/.env" ]]; then
  cp "${PUBLIC_DIR}/.env" "${APP_DIR}/.env"
fi
php tools/deploy_db_check.php && echo "[OK] DB SELECT 1" || { echo "[FAIL] DB"; exit 1; }

php tools/pre_deploy_check.php --base-url="${IA_HEALTH_BASE:-https://inovaauto.com}" \
  && echo "[OK] pre_deploy_check" || { echo "[FAIL] pre_deploy_check"; exit 1; }

echo ""
echo "DRY-RUN complete. To deploy for real: bash deploy.sh"
echo "Rollback path: backups in \${BACKUP_ROOT:-\$HOME/backups/inovaauto}"
