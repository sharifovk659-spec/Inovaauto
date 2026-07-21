#!/usr/bin/env bash
# Run on Hostinger via SSH to discover real paths (do not guess).
set -euo pipefail

echo "=== Hostinger path discovery ==="
echo "user: $(whoami)"
echo "home: ${HOME:-unknown}"
pwd
echo ""
echo "--- domains ---"
if [[ -d "${HOME}/domains" ]]; then
  ls -la "${HOME}/domains/"
else
  echo "No ~/domains directory"
fi
echo ""
echo "--- public_html directories ---"
find "${HOME}" -maxdepth 4 -type d -name public_html 2>/dev/null || true
echo ""
echo "--- inovaauto candidates ---"
find "${HOME}" -maxdepth 5 -type d \( -name 'inovaauto.com' -o -name 'inovaauto' \) 2>/dev/null || true
echo ""
echo "Suggested (verify before use):"
echo "  APP_DIR=${HOME}/apps/inovaauto"
echo "  PUBLIC_DIR=${HOME}/domains/inovaauto.com/public_html"
