#!/bin/sh

set -eu

plugin_action=${ICEFOX_PLUGIN_ACTION:-}
if [ -z "$plugin_action" ]; then
    echo 'Album plugin remote photo checks skipped (ICEFOX_PLUGIN_ACTION not set)'
    exit 0
fi

if [ ! -f "$plugin_action" ]; then
    echo "Album plugin action file not found: $plugin_action" >&2
    exit 1
fi

failures=0

require_pattern() {
    pattern=$1
    message=$2
    if ! rg -q -- "$pattern" "$plugin_action"; then
        echo "$message" >&2
        failures=$((failures + 1))
    fi
}

require_pattern "request->get\('remotePhotos'" 'saveAlbum must read remote photo URLs'
require_pattern 'parseAlbumRemotePhotos' 'the plugin must validate and normalize remote photo URLs'
require_pattern 'photos\[\].*src.*url.*alt.*name' 'remote photo URLs must be stored as album photos'
require_pattern "\['http', 'https'\]" 'remote album photos must only allow HTTP and HTTPS URLs'

if [ "$failures" -ne 0 ]; then
    echo "$failures album plugin remote photo checks failed" >&2
    exit 1
fi

echo 'Album plugin remote photo contracts are present'
