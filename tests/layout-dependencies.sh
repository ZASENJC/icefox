#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

bulma_css=assets/css/bulma.min.css

if [ ! -f "$bulma_css" ]; then
    echo "Bulma is required by the current template layout but is missing" >&2
    exit 1
fi

if ! rg -q "themeUrl\('assets/css/bulma\.min\.css'\)" header.php; then
    echo "header.php does not load the required Bulma stylesheet" >&2
    exit 1
fi

for selector in fixed-grid grid cell; do
    if ! rg -q "\.$selector" "$bulma_css"; then
        echo "Bulma stylesheet is missing required .$selector layout rules" >&2
        exit 1
    fi
done

echo "Layout dependencies are present"
