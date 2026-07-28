#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if rg -q 'class="tc-edit"[^>]*href=' components/head.php; then
    echo "the publish control must open an in-page modal instead of navigating" >&2
    exit 1
fi

if ! rg -q "editor-modal-open" components/head.php; then
    echo "the publish control must dispatch the editor modal event" >&2
    exit 1
fi

for template in index.php archive.php archive-page.php post.php page.php; do
    if ! rg -q "components/modals/editor\.php" "$template"; then
        echo "$template must render the editor modal" >&2
        exit 1
    fi
done

if ! rg -q 'class="editor-modal"' components/modals/editor.php; then
    echo "the editor modal component is missing" >&2
    exit 1
fi

if ! rg -q 'editor-modal-open\.window' components/modals/editor.php; then
    echo "the editor modal must listen for the open event" >&2
    exit 1
fi

if ! rg -q '\?do=createPost' components/modals/editor.php; then
    echo "the editor modal must retain the plugin createPost contract" >&2
    exit 1
fi

if ! rg -q "'homeUrl'" header.php; then
    echo "the browser config must expose the site homepage URL" >&2
    exit 1
fi

if ! rg -q 'result\.redirect.*ICEFOX_CONFIG\.homeUrl' components/modals/editor.php; then
    echo "publishing must navigate to the main page" >&2
    exit 1
fi

if ! rg -q 'icefox_published' components/modals/editor.php header.php; then
    echo "the main-page refresh must bypass stale browser caches" >&2
    exit 1
fi

if ! rg -q 'window\.location\.replace' components/modals/editor.php; then
    echo "publishing must replace the current page with the refreshed homepage" >&2
    exit 1
fi

if ! rg -q 'window\.location\.origin' components/modals/editor.php; then
    echo "the homepage refresh must preserve the host used by the visitor" >&2
    exit 1
fi

if ! rg -q '最多只能上传9张图片' components/modals/editor.php ||
   ! rg -q '只能上传1个视频' components/modals/editor.php; then
    echo "the editor modal must retain media upload limits" >&2
    exit 1
fi

node tests/publish-album-sync.js
node tests/editor-options-ui.js

echo "Publishing uses the in-page editor modal"
