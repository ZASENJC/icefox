#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if rg -n -i --glob '*.php' --glob '*.js' --glob '*.css' \
    'isAdvertise|isPostAd|sidebarAd|ad-badge|ad-sidebar|广告|赞助商' \
    .; then
    echo "advertising functionality remains in theme runtime files" >&2
    exit 1
fi

echo "No advertising content remains in the theme"
