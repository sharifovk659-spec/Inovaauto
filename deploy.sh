#!/usr/bin/env bash
#
# InovaAuto — safe deploy to Hostinger production.
# Run ON THE SERVER (SSH) after git clone into APP_DIR.
#
# Flow: backup → git pull → checks → rsync → migrate → health
# Does NOT use rsync --delete unless RSYNC_DELETE=1 (explicit approval).
#
set -euo pipefail

# --- Configure after running scripts/discover-paths.sh ---
APP_DIR="${APP_DIR:-/home/u417315406/apps/inovaauto}"
PUBLIC_DIR="${PUBLIC_DIR:-/home/u417315406/domains/inovaauto.com/public_html}"
GIT_BRANCH="${GIT_BRANCH:-main}"
BACKUP_ROOT="${BACKUP_ROOT:-${HOME}/backups/inovaauto}"
RSYNC_DELETE="${RSYNC_DELETE:-0}"

TIMESTAMP="$(date +%Y-%m-%d-%H-%M)"
FILES_BACKUP="${BACKUP_ROOT}/inovaauto-files-${TIMESTAMP}.tar.gz"
DB_BACKUP="${BACKUP_ROOT}/inovaauto-database-${TIMESTAMP}.sql"
ROLLBACK_DIR="${BACKUP_ROOT}/rollback-${TIMESTAMP}"

log() { printf '[deploy] %s\n' "$*"; }
fail() { log "ERROR: $*"; exit 1; }

verify_paths() {
  [[ -d "$APP_DIR" ]] || fail "APP_DIR not found: $APP_DIR (run: bash scripts/discover-paths.sh)"
  [[ -d "$PUBLIC_DIR" ]] || fail "PUBLIC_DIR not found: $PUBLIC_DIR"
  [[ -f "$APP_DIR/deploy.sh" ]] || fail "deploy.sh must live inside APP_DIR git root"
  [[ -w "$PUBLIC_DIR" ]] || fail "PUBLIC_DIR not writable: $PUBLIC_DIR"
  mkdir -p "$BACKUP_ROOT"
  case "$BACKUP_ROOT" in
    ${PUBLIC_DIR}/*) fail "BACKUP_ROOT must not be inside PUBLIC_DIR" ;;
  esac
}

load_db_name() {
  ENV_FILE="${PUBLIC_DIR}/.env"
  [[ -f "$ENV_FILE" ]] || ENV_FILE="${APP_DIR}/.env"
  [[ -f "$ENV_FILE" ]] || fail "No .env in PUBLIC_DIR or APP_DIR"

  DB_NAME="$(grep -E '^IA_DB_NAME=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\''"]//;s/["'\''"]$//')"
  DB_USER="$(grep -E '^IA_DB_USER=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\''"]//;s/["'\''"]$//')"
  DB_PASS="$(grep -E '^IA_DB_PASS=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '\r' | sed 's/^["'\''"]//;s/["'\''"]$//')"
  [[ -n "$DB_NAME" && -n "$DB_USER" ]] || fail "IA_DB_NAME / IA_DB_USER missing in .env"
}

backup_files() {
  log "Backing up public files → ${FILES_BACKUP}"
  tar -czf "$FILES_BACKUP" \
    -C "$(dirname "$PUBLIC_DIR")" "$(basename "$PUBLIC_DIR")" \
    --exclude='./backups' \
    --exclude='./uploads' \
    2>/dev/null || tar -czf "$FILES_BACKUP" -C "$PUBLIC_DIR" .
}

backup_database() {
  load_db_name
  log "Backing up MySQL → ${DB_BACKUP}"
  if [[ -n "$DB_PASS" ]]; then
    MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --routines --triggers \
      -u "$DB_USER" "$DB_NAME" > "$DB_BACKUP"
  else
    mysqldump --single-transaction --routines --triggers \
      -u "$DB_USER" "$DB_NAME" > "$DB_BACKUP"
  fi
  unset MYSQL_PWD DB_PASS
  [[ -s "$DB_BACKUP" ]] || fail "Database backup empty"
}

rollback() {
  if [[ ! -f "$FILES_BACKUP" ]]; then
    fail "Deploy aborted before backup completed — no rollback tarball"
  fi
  log "ROLLBACK: restoring from ${FILES_BACKUP}"
  mkdir -p "$ROLLBACK_DIR"
  tar -xzf "$FILES_BACKUP" -C "$ROLLBACK_DIR" || true
  if [[ -d "${ROLLBACK_DIR}/$(basename "$PUBLIC_DIR")" ]]; then
    rsync -a "${ROLLBACK_DIR}/$(basename "$PUBLIC_DIR")/" "$PUBLIC_DIR/"
  else
    rsync -a "${ROLLBACK_DIR}/" "$PUBLIC_DIR/"
  fi
  fail "Deploy aborted — site restored from backup (verify manually)"
}

git_update() {
  log "Updating git in ${APP_DIR} (branch ${GIT_BRANCH})"
  cd "$APP_DIR"
  git fetch origin
  git checkout "$GIT_BRANCH"
  git reset --hard "origin/${GIT_BRANCH}"
}

php_syntax_check() {
  log "PHP syntax check"
  cd "$APP_DIR"
  ERR=0
  while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null || ERR=1
  done < <(find . -name '*.php' -not -path './vendor/*' -print0)
  [[ "$ERR" -eq 0 ]] || fail "PHP syntax errors"
}

db_check() {
  log "Database connection check (SELECT 1)"
  cd "$APP_DIR"
  if [[ -f "${PUBLIC_DIR}/.env" && ! -f "${APP_DIR}/.env" ]]; then
    cp "${PUBLIC_DIR}/.env" "${APP_DIR}/.env"
  fi
  php tools/deploy_db_check.php
}

rsync_to_public() {
  log "Rsync APP_DIR → PUBLIC_DIR (no .git, no secrets, no uploads)"
  RSYNC_OPTS=(-av)
  if [[ "$RSYNC_DELETE" == "1" ]]; then
    log "WARNING: rsync --delete enabled"
    RSYNC_OPTS+=(--delete)
  fi

  rsync "${RSYNC_OPTS[@]}" \
    --exclude='.git/' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='uploads/' \
    --exclude='backups/' \
    --exclude='logs/' \
    --exclude='storage/logs/' \
    --exclude='storage/cache/*.json' \
    --exclude='config/local.php' \
    --exclude='config/database.production.php' \
    --exclude='config/database.local.php' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='.phpunit.cache/' \
    "$APP_DIR/" "$PUBLIC_DIR/"

  if [[ -f "${PUBLIC_DIR}/.env" ]]; then
    log "Production .env preserved in PUBLIC_DIR"
  else
    log "WARNING: no .env in PUBLIC_DIR — create from .env.hostinger.example"
  fi
}

run_migrations() {
  log "Running pending migrations (safe mode)"
  cd "$PUBLIC_DIR"
  php tools/migrate.php || fail "Migration failed"
}

composer_install_if_needed() {
  if [[ ! -f "${PUBLIC_DIR}/vendor/autoload.php" ]] && command -v composer >/dev/null 2>&1; then
    log "Installing composer dependencies (no-dev)"
    cd "$PUBLIC_DIR"
    composer install --no-dev --optimize-autoloader --no-interaction
  fi
}

health_check() {
  log "Post-deploy HTTP check"
  URL="${IA_HEALTH_URL:-https://inovaauto.com/health.php}"
  HTTP_CODE="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "$URL" || echo '000')"
  if [[ "$HTTP_CODE" != "200" ]]; then
    HTTP_CODE="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 'https://inovaauto.com/' || echo '000')"
  fi
  [[ "$HTTP_CODE" == "200" || "$HTTP_CODE" == "301" || "$HTTP_CODE" == "302" ]] \
    || fail "Site health check failed (HTTP ${HTTP_CODE})"
  log "Site responds HTTP ${HTTP_CODE}"
}

main() {
  verify_paths
  backup_files
  backup_database
  trap 'rollback' ERR
  git_update
  php_syntax_check
  db_check
  rsync_to_public
  composer_install_if_needed
  run_migrations
  health_check
  trap - ERR
  log "Deploy completed successfully at ${TIMESTAMP}"
}

main "$@"
