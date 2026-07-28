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

forbid_pattern() {
    pattern=$1
    file=$2
    message=$3
    if rg -q -- "$pattern" "$file"; then
        echo "$message" >&2
        failures=$((failures + 1))
    fi
}

require_pattern "Form_Element_Text.*albumPageUrl" functions.php 'theme config must expose the album page URL'
require_pattern "Form_Element_Text.*albumTopImage" functions.php 'theme config must expose the album top image'
require_pattern "Form_Element_Radio.*showMomentsAlbum" functions.php 'theme config must expose the moments album visibility switch'
require_pattern "showMomentsAlbum.*'1'" functions.php 'the moments album visibility switch must default to enabled'
require_pattern "albumPageUrl.*'/albums'" functions.php 'album page URL must use the clean /albums path'
require_pattern "albumOnly" functions.php 'post fields must expose the album-only switch'
require_pattern 'class="tc-album"' components/head.php 'the top bar must include an album entry'
require_pattern "options->albumPageUrl" components/head.php 'the album entry must use the configured URL'
require_pattern 'album-primary-action' components/head.php 'the album top-right control must delegate to the active album view'
require_pattern 'getAlbums' components/album-gallery.php 'album page must load the album list from the plugin'
require_pattern 'getAlbum' components/album-gallery.php 'album detail must load its photos from the plugin'
require_pattern 'options->showMomentsAlbum' components/album-gallery.php 'album page must read the moments album visibility setting'
require_pattern "showMomentsAlbum \? 'true' : 'false'" components/album-gallery.php 'album page must pass the moments album visibility setting to the gallery manager'
require_pattern "request->get.*album" components/album-gallery.php 'album detail must read the slug from the Typecho route request'
require_pattern 'pathname.*album.slug' components/album-gallery.php 'album links must use the album slug as a path segment'
require_pattern 'album-primary-action.window="openPrimaryAction\(\)"' components/album-gallery.php 'the album gallery must handle the top-right control'
require_pattern 'openEditor\(this.album, true\)' components/album-gallery.php 'album detail must open the current album in upload mode'
require_pattern 'saveAlbum' components/modals/album-editor.php 'album editor must retain the plugin save contract'
require_pattern 'uploadOnly' components/modals/album-editor.php 'album editor must expose a photo-upload-only mode'
require_pattern "uploadOnly \\? '上传照片'" components/modals/album-editor.php 'album upload mode must have a dedicated title'
require_pattern 'remotePhotoUrls' components/modals/album-editor.php 'album editor must accept remote photo URLs'
require_pattern "formData.append\('remotePhotos'" components/modals/album-editor.php 'album editor must send remote photos to the plugin'
require_pattern 'album-editor-field textarea:focus' assets/css/icefox.css 'album editor text fields must define a focus style without the browser ring'
require_pattern 'box-shadow: none' assets/css/icefox.css 'album editor focus styling must remove the blue focus shadow'
require_pattern 'album-editor-field textarea::placeholder' assets/css/icefox.css 'album editor placeholders must match the publishing composer'
forbid_pattern 'background: var\(--modal-input-background\)' assets/css/icefox.css 'album editor fields must not use the light modal input background'
require_pattern 'class="album-grid"' components/album-gallery.php 'album photos must render in a dedicated grid'
require_pattern 'components/modals/album-editor.php' album-page.php 'album page must render its editor modal'
require_pattern 'isAlbumOnlyPost' components/post-list.php 'blog feed must filter album-only posts'
require_pattern 'isAlbumOnlyPost' archive.php 'archive feed must filter album-only posts'

if [ "$failures" -ne 0 ]; then
    echo "$failures album feature checks failed" >&2
    exit 1
fi

node tests/album-remote-urls.js
node tests/moments-album.js

echo 'Album page contracts are present'
