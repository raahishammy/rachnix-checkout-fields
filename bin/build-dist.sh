#!/usr/bin/env bash
# Copies the files that ship to dist/rachnix-checkout-fields (and zips them with --zip).
# Run `npm run build` first: the admin app in assets/build is part of the plugin.
set -euo pipefail
cd "$(dirname "$0")/.."

test -f assets/build/index.js || { echo "assets/build is missing: run npm run build first." >&2; exit 1; }

rm -rf dist
mkdir -p dist/rachnix-checkout-fields
rsync -a --exclude-from=.distignore ./ dist/rachnix-checkout-fields/

if [ "${1:-}" = "--zip" ]; then
	( cd dist && zip -qr rachnix-checkout-fields.zip rachnix-checkout-fields )
	echo "dist/rachnix-checkout-fields.zip"
fi
find dist/rachnix-checkout-fields -type f | sort
