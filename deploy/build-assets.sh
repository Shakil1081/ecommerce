#!/usr/bin/env bash
# Run on YOUR PC (Git Bash), not on the server: the cPanel host has no Node.js.
# Builds the production JS/CSS and packs it into deploy/build-assets.tar.gz for upload.
set -euo pipefail

cd "$(dirname "$0")/.."

rm -f public/hot   # a leftover hot file makes every page blank
npm ci
npm run build

tar -czf deploy/build-assets.tar.gz -C public build
echo
echo "Done: deploy/build-assets.tar.gz"
echo "Upload it to the app folder on the server, then run deploy/cpanel-deploy.sh there."
