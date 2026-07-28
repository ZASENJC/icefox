#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if ! rg -q 'setting-tags-mode-row' components/modals/setting.php; then
    echo "tags and theme mode must share one settings row" >&2
    exit 1
fi

if ! rg -q 'setting-mode-button' components/modals/setting.php; then
    echo "theme mode must be represented by one compact button" >&2
    exit 1
fi

if rg -q 'setting-tags-header' components/modals/setting.php; then
    echo "theme mode must align with the bottom of the tags content" >&2
    exit 1
fi

if ! rg -q -U '(?s)\.setting-tags-mode-row \{.*?display: flex;.*?align-items: flex-end;' assets/css/icefox.css; then
    echo "tags and theme mode must align at the bottom of one row" >&2
    exit 1
fi

if ! rg -q -U '(?s)\.setting-mode-button \{.*?height: 44px;.*?width: 44px;' assets/css/icefox.css; then
    echo "desktop theme mode button must match the search button size" >&2
    exit 1
fi

if rg -q 'setting-section-title">外观' components/modals/setting.php; then
    echo "theme mode must not render a full-width appearance section" >&2
    exit 1
fi

if ! rg -q 'setting-tags-mode-row' assets/css/icefox.css || ! rg -q 'setting-mode-button' assets/css/icefox.css; then
    echo "settings row layout styles are missing" >&2
    exit 1
fi

echo "Tags and theme mode share a compact settings row"
