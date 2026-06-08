#!/bin/bash
# One-time fix + manual sync for Hostinger when "divergent branches" blocks deploy.
# Run on the SERVER inside public_html (Browser Terminal or SSH):
#   bash scripts/hostinger-git-sync.sh
#
# Safe: resets tracked files to GitHub main. Does NOT delete DB or gitignored uploads.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "ERROR: Not a git repository: $ROOT"
    exit 1
fi

echo "==> Fetching origin..."
git fetch origin

echo "==> Resetting to origin/main (fixes divergent branches)..."
git reset --hard origin/main

echo "==> Done. Current commit:"
git log -1 --oneline
