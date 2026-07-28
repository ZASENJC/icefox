#!/bin/sh

set -eu

plugin_action=${ICEFOX_PLUGIN_ACTION:-}
plugin_main=${ICEFOX_PLUGIN_MAIN:-}
if [ -z "$plugin_action" ] || [ -z "$plugin_main" ]; then
    echo 'Album plugin pinning checks skipped (ICEFOX_PLUGIN_ACTION or ICEFOX_PLUGIN_MAIN not set)'
    exit 0
fi

if [ ! -f "$plugin_action" ] || [ ! -f "$plugin_main" ]; then
    echo 'Album plugin pinning files were not found' >&2
    exit 1
fi

failures=0

require_pattern() {
    pattern=$1
    file=$2
    message=$3
    if ! rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        failures=$((failures + 1))
    fi
}

require_pattern '`is_pinned` tinyint\(1\).*DEFAULT' "$plugin_main" 'album schema must define is_pinned'
require_pattern "in_array\('is_pinned'" "$plugin_main" 'album migration must detect an existing is_pinned field'
require_pattern 'ALTER TABLE.*is_pinned' "$plugin_main" 'existing album tables must migrate is_pinned'
require_pattern "request->get\('isPinned'" "$plugin_action" 'saveAlbum must read isPinned'
require_pattern "'is_pinned'.*isPinned" "$plugin_action" 'saveAlbum must persist isPinned'
require_pattern "order\('is_pinned'.*SORT_DESC\)" "$plugin_action" 'getAlbums must sort pinned albums first'
require_pattern "'isPinned'.*is_pinned" "$plugin_action" 'album JSON must expose isPinned'

if [ "$failures" -ne 0 ]; then
    echo "$failures album plugin pinning checks failed" >&2
    exit 1
fi

echo 'Album plugin pinning contracts are present'
