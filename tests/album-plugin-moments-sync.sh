#!/bin/sh

set -eu

plugin_action=${ICEFOX_PLUGIN_ACTION:-}
if [ -z "$plugin_action" ]; then
    echo 'Album plugin moments sync checks skipped (ICEFOX_PLUGIN_ACTION not set)'
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

require_pattern "request->get\('syncToAlbum'.*=== '1'" 'createPost must read the enabled moments sync choice'
require_pattern 'appendImagesToMomentsAlbum' 'createPost must append uploaded images to the moments album'
require_pattern "type.*=== 'image'" 'moments sync must exclude uploaded videos'
require_pattern "where\('slug = \?', 'moments'\)" 'the moments album must use the stable moments slug'
require_pattern "'name'.*'朋友圈'" 'the plugin must create the default moments album with the expected name'
require_pattern 'decodeAlbumPhotos' 'moments sync must preserve existing album photos'
require_pattern "photos\[\].*src.*file\['path'\]" 'moments sync must append each uploaded image path'

if [ "$failures" -ne 0 ]; then
    echo "$failures album plugin moments sync checks failed" >&2
    exit 1
fi

echo 'Album plugin moments sync contracts are present'
