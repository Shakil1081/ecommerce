#!/usr/bin/env bash
# Run on YOUR PC (Git Bash), not on the server: the cPanel host has no Node.js.
# Builds the production JS/CSS into public/build, which is committed to git so the
# server receives it with `git pull`.
set -euo pipefail

cd "$(dirname "$0")/.."

rm -f public/hot   # a leftover hot file makes every page blank
npm ci
npm run build

echo
echo "Done: public/build is up to date. Now commit and push it:"
echo "  git add public/build && git commit -m \"build: production assets\" && git push origin main"
echo "Then on the server: git pull origin main && bash deploy/cpanel-deploy.sh"
