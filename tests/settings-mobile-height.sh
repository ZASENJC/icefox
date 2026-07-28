#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if ! rg -q 'height: 100dvh;' assets/css/icefox.css; then
    echo "the settings overlay must follow the mobile dynamic viewport" >&2
    exit 1
fi

if ! rg -q 'max-height: min\(720px, calc\(100dvh - 24px\)\);' assets/css/icefox.css; then
    echo "the mobile settings panel must stay inside the dynamic viewport" >&2
    exit 1
fi

if ! rg -q 'box-sizing: border-box;' assets/css/icefox.css; then
    echo "settings height constraints must include internal padding" >&2
    exit 1
fi

echo "Mobile settings stays inside the dynamic viewport"
