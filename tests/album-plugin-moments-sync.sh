#!/bin/sh

set -eu

plugin_action=${ICEFOX_PLUGIN_ACTION:-}
plugin_main=${ICEFOX_PLUGIN_MAIN:-}
if [ -z "$plugin_action" ] || [ -z "$plugin_main" ]; then
    echo 'Album plugin moments sync checks skipped (ICEFOX_PLUGIN_ACTION or ICEFOX_PLUGIN_MAIN not set)'
    exit 0
fi

if [ ! -f "$plugin_action" ] || [ ! -f "$plugin_main" ]; then
    echo 'Album plugin moments sync files were not found' >&2
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

require_pattern "request->get\('syncToAlbum'.*=== '1'" "$plugin_action" 'createPost must read the enabled moments sync choice'
require_pattern 'extractPostImagePhotos' "$plugin_action" 'moments sync must extract Markdown and HTML images from the final post content'
require_pattern 'appendImagesToMomentsAlbum.*postContent' "$plugin_action" 'createPost must pass final post content into moments sync'
require_pattern "type.*=== 'image'" "$plugin_action" 'moments sync must exclude uploaded videos'
require_pattern 'findMomentsAlbum' "$plugin_action" 'moments sync must resolve the album by stable identity'
require_pattern "'is_moments'.*1" "$plugin_action" 'the moments album must persist its stable identity marker'
require_pattern "'isMoments'.*is_moments" "$plugin_action" 'album JSON must expose the stable moments identity'
require_pattern 'decodeAlbumPhotos' "$plugin_action" 'moments sync must preserve existing album photos'
require_pattern '`is_moments` tinyint\(1\).*DEFAULT' "$plugin_main" 'album schema must define the stable moments identity marker'
require_pattern "in_array\('is_moments'" "$plugin_main" 'existing album tables must migrate the stable moments identity marker'

if [ "$failures" -ne 0 ]; then
    echo "$failures album plugin moments sync checks failed" >&2
    exit 1
fi

echo 'Album plugin moments sync contracts are present'
