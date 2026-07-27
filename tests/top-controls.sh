#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if ! rg -q '<section class="top-container"[^>]*x-data' components/head.php; then
    echo "top controls must live inside an Alpine data root" >&2
    exit 1
fi

for control in tc-links tc-setting; do
    if ! rg -q "class=\"$control\"[^>]*|class=\"$control\"" components/head.php; then
        echo "missing top control: $control" >&2
        exit 1
    fi
done

echo "Top controls have an Alpine initialization root"
