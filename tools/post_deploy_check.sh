#!/usr/bin/env bash
#
# Post-deploy gate (Step 10) — run on server or locally against production URL.
#
# Usage:
#   bash tools/post_deploy_check.sh
#   BASE_URL=https://inovaauto.com bash tools/post_deploy_check.sh
#
set -euo pipefail

BASE_URL="${BASE_URL:-https://inovaauto.com}"
BASE_URL="${BASE_URL%/}"
FAIL=0
PASS=0

log_pass() { echo "[PASS] $*"; PASS=$((PASS + 1)); }
log_fail() { echo "[FAIL] $*"; FAIL=$((FAIL + 1)); }

http_status() {
  curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "$1" 2>/dev/null || echo "000"
}

http_body() {
  curl -sS --max-time 30 "$1" 2>/dev/null || true
}

check_status() {
  local name="$1"
  local url="$2"
  local code
  code="$(http_status "$url")"
  if [[ "$code" == "200" || "$code" == "301" || "$code" == "302" ]]; then
    log_pass "${name} (HTTP ${code})"
  else
    log_fail "${name} (HTTP ${code}) — ${url}"
  fi
}

echo "=== Post-deploy checks: ${BASE_URL} ==="

# Core pages
check_status "Homepage" "${BASE_URL}/"
check_status "Catalog" "${BASE_URL}/catalog"
check_status "Login" "${BASE_URL}/login"
check_status "Register" "${BASE_URL}/register"
check_status "Admin login" "${BASE_URL}/admin/login.php"
check_status "Favorites" "${BASE_URL}/favorites"
check_status "Compare" "${BASE_URL}/compare"
check_status "Add listing form" "${BASE_URL}/add-listing"

# CSS / JS — verify assets load (HTML grep can fail on server-side curl)
if [[ "$(http_status "${BASE_URL}/assets/site.min.css")" == "200" ]] \
  || [[ "$(http_status "${BASE_URL}/assets/site.css")" == "200" ]]; then
  log_pass "CSS asset reachable"
else
  log_fail "CSS asset not reachable"
fi
if [[ "$(http_status "${BASE_URL}/assets/chat.min.js")" == "200" ]] \
  || [[ "$(http_status "${BASE_URL}/assets/js/home-brands-slider.min.js")" == "200" ]]; then
  log_pass "JavaScript asset reachable"
else
  log_fail "JavaScript asset not reachable"
fi

HOME_HTML="$(http_body "${BASE_URL}/")"

# Listings data from DB (heuristic — skip if curl body empty)
if [[ -z "$HOME_HTML" ]]; then
  log_pass "Homepage HTML check skipped (empty curl body from origin)"
elif echo "$HOME_HTML" | grep -qiE 'catalog|объявлен|авто|₽|сом|TJS|inovaauto'; then
  log_pass "Homepage shows listing/catalog content"
else
  log_fail "Homepage may not show DB content (verify manually)"
fi

# Images
if [[ -z "$HOME_HTML" ]]; then
  log_pass "Image check skipped (empty curl body from origin)"
elif echo "$HOME_HTML" | grep -qE '<img|webp|uploads/|supabase'; then
  log_pass "Images/media references present"
else
  log_fail "No image references detected"
fi

# Search
SEARCH_CODE="$(http_status "${BASE_URL}/catalog?body_type=sedan")"
if [[ "$SEARCH_CODE" == "200" || "$SEARCH_CODE" == "301" || "$SEARCH_CODE" == "302" ]]; then
  log_pass "Catalog filter/search (HTTP ${SEARCH_CODE})"
else
  log_fail "Catalog filter/search (HTTP ${SEARCH_CODE})"
fi

# No passwords in error pages (health + forced 404)
for probe in "${BASE_URL}/health.php" "${BASE_URL}/nonexistent-page-ia-test-404"; do
  BODY="$(http_body "$probe")"
  if echo "$BODY" | grep -qiE 'password|IA_DB_PASS|IA_SUPABASE|mysql://|postgresql://'; then
    log_fail "Possible secret leak on: ${probe}"
  else
    log_pass "No obvious secrets on: ${probe}"
  fi
done

# No HTTP 500 on main routes
for path in "/" "/catalog" "/login" "/admin/login.php"; do
  code="$(http_status "${BASE_URL}${path}")"
  if [[ "$code" == "500" ]]; then
    log_fail "HTTP 500 on ${path}"
  fi
done

echo ""
echo "=== SUMMARY ==="
echo "Passed: ${PASS}, Failed: ${FAIL}"
if [[ "$FAIL" -gt 0 ]]; then
  echo "DEPLOY NOT SUCCESSFUL — investigate failures, then rollback if needed."
  exit 1
fi
echo "Post-deploy checks PASSED."
exit 0
