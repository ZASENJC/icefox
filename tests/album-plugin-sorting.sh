#!/bin/sh

set -eu

plugin_action=${ICEFOX_PLUGIN_ACTION:-}
plugin_main=${ICEFOX_PLUGIN_MAIN:-}
if [ -z "$plugin_action" ] || [ -z "$plugin_main" ]; then
    echo 'Album plugin sorting checks skipped (ICEFOX_PLUGIN_ACTION or ICEFOX_PLUGIN_MAIN not set)'
    exit 0
fi

if [ ! -f "$plugin_action" ] || [ ! -f "$plugin_main" ]; then
    echo 'Album plugin sorting files were not found' >&2
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

require_pattern '`sort_order` (int|bigint).*DEFAULT' "$plugin_main" 'album schema must define sort_order'
require_pattern "in_array\('sort_order'" "$plugin_main" 'album migration must detect an existing sort_order field'
require_pattern 'ALTER TABLE.*sort_order' "$plugin_main" 'existing album tables must migrate sort_order'
require_pattern "request->get\('sortOrder'" "$plugin_action" 'saveAlbum must read sortOrder'
require_pattern "'sort_order'.*sortOrder" "$plugin_action" 'saveAlbum must persist sortOrder'
require_pattern "order\('is_moments'.*SORT_DESC\)" "$plugin_action" 'getAlbums must keep the moments album first'
require_pattern "order\('sort_order'.*SORT_ASC\)" "$plugin_action" 'getAlbums must sort lower album order values first'
require_pattern "'sortOrder'.*sort_order" "$plugin_action" 'album JSON must expose sortOrder'
require_pattern 'nextAlbumSortOrder' "$plugin_action" 'saveAlbum must assign a sort order when older clients omit it'
require_pattern 'MAX\(sort_order\)' "$plugin_action" 'new album sorting must continue after the largest regular album order'

if [ "$failures" -ne 0 ]; then
    echo "$failures album plugin sorting checks failed" >&2
    exit 1
fi

echo 'Album plugin sorting contracts are present'
