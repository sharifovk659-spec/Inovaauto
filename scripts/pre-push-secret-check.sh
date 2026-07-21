#!/usr/bin/env bash
# Pre-commit / pre-push secret scan (run locally before git push).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FAIL=0

check_not_tracked() {
  local path="$1"
  if git ls-files --error-unmatch "$path" >/dev/null 2>&1; then
    echo "FAIL: tracked secret file: $path"
    FAIL=1
  fi
}

for f in .env .env.local .env.production config/local.php config/database.production.php; do
  check_not_tracked "$f"
done

if git ls-files | grep -E '\.(sql\.gz|dump|backup)$' >/dev/null 2>&1; then
  echo "FAIL: database dump file tracked in git"
  FAIL=1
fi

PATTERNS='IA_DB_PASS=[^Y][^O]|IA_SUPABASE_DB_PASSWORD=[^Y]|Sh160204|postgresql://[^:]+:[^@]+@'
if git grep -E "$PATTERNS" -- ':!*.example' ':!.env.example' ':!.env.hostinger.example' ':!scripts/pre-push-secret-check.sh' 2>/dev/null; then
  echo "FAIL: possible secret in tracked files (see above)"
  FAIL=1
fi

if [[ "$FAIL" -eq 0 ]]; then
  echo "OK: no obvious secrets in git index"
  exit 0
fi
exit 1
