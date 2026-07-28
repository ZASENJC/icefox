#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

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

require_pattern "Form_Element_Text.*albumPageUrl" functions.php 'theme config must expose the album page URL'
require_pattern "Form_Element_Text.*albumTopImage" functions.php 'theme config must expose the album top image'
require_pattern "albumOnly" functions.php 'post fields must expose the album-only switch'
require_pattern 'class="tc-album"' components/head.php 'the top bar must include an album entry'
require_pattern "options->albumPageUrl" components/head.php 'the album entry must use the configured URL'
require_pattern 'album-page.php' tests/album-feature.sh 'album page template must be covered by the feature contract'
require_pattern 'getAlbums' components/album-gallery.php 'album page must load the album list from the plugin'
require_pattern 'getAlbum' components/album-gallery.php 'album detail must load its photos from the plugin'
require_pattern 'saveAlbum' components/modals/album-editor.php 'album editor must retain the plugin save contract'
require_pattern 'class="album-grid"' components/album-gallery.php 'album photos must render in a dedicated grid'
require_pattern 'components/modals/album-editor.php' album-page.php 'album page must render its editor modal'
require_pattern 'isAlbumOnlyPost' components/post-list.php 'blog feed must filter album-only posts'
require_pattern 'isAlbumOnlyPost' archive.php 'archive feed must filter album-only posts'

if [ "$failures" -ne 0 ]; then
    echo "$failures album feature checks failed" >&2
    exit 1
fi

echo 'Album page contracts are present'
